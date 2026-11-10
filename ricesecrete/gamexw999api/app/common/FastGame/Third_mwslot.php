<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;
use app\model\Games;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Redis;
use app\common\lib\Util;

class Third_mwslot extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:29
     */
    function MakeSign($config, &$data)
    {
        return [];
    }

    /**验证签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    function CheckSign($config, $data)
    {
//        return true;
        // 此三方站回复数据无签名
        $param = $data['param'];
        $header = $data['header'];
        $ip_list = explode(',', $config['trdcfg']['tc_api_signkey']);
        //渠道id 等于数据库中的渠道id 或者ping请求的时候为true  并且请求ip要跟数据库定义的ip相等
        if (in_array($header['x-forwarded-for'], $ip_list)) {
            if ($param['signature'] == $config['trdcfg']['tc_api_prikey']) {
                return true;
            }
        }
        return false;
    }

    function aes_encrypt($key, $str)
    {
        $encrypted = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($encrypted);
        return $data;
    }

    function aes_decrypt($key, $str)
    {
        $encryptedData = base64_decode($str);
        $decrypted = openssl_decrypt($encryptedData, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $decrypted;
    }

    function rsa_encrypt($aesKey, $pubkey)
    {
        openssl_public_encrypt($aesKey, $encryptKey, $pubkey);
        $encryptKey = base64_encode($encryptKey);
        return $encryptKey;
    }

    function rsa_decrypt($ciphertext, $prikey)
    {
        $receiveKey = base64_decode($ciphertext);
        $ecPrivateKey = openssl_pkey_get_private($prikey);
        openssl_private_decrypt($receiveKey, $decryptAesKey, $ecPrivateKey);
        return $decryptAesKey;
    }

    function encrypt_key_data($data, $MW_PUBLIC_KEY)
    {
        $aesKey = self::generate_aes_key(); // 生成AESKEY 長度16
        $encryptKey = self::rsa_encrypt($aesKey, $MW_PUBLIC_KEY); // MW平台公鑰 加密 AESKEY 獲得 EC加密KEY

        $jsonStr = json_encode($data); // DATA 轉成 JSON字串
        $encryptData = self::aes_encrypt($aesKey, $jsonStr); // AESKEY 加密 JSON字串 獲得 EC加密DATA

        return [$encryptKey, $encryptData]; // 發送 EC加密KEY、EC加密DATA 給 MW
    }

    function decrypt_response($response, $EC_PRIVATE_KEY)
    {

        $response_json = json_decode($response, true);
        $encryptKey = $response_json["key"]; // MW加密KEY
        $encryptData = $response_json["data"]; // MW加密DATA
        $aesKey = self::rsa_decrypt($encryptKey, $EC_PRIVATE_KEY); // EC方私鑰 解密 MW加密KEY 獲得 AESKEY
        $decryptData = self::aes_decrypt($aesKey, $encryptData); // AESKEY 解密 MW加密DATA 獲得 DATA
        //echo_log("DecryptData: $decryptData");
        return json_decode($decryptData, true);
    }

    function DecData($apicfgs, $response_json)
    {
        Utils::L("{$apicfgs['trdcfg']['tc_id']}/ResultData", ['开始解密']);
        $EC_PRIVATE_KEY = str_replace("\\n", "\n", $apicfgs['trdcfg']['tc_api_prikey']);
        $encryptKey = $response_json["key"]; // MW加密KEY
        $encryptData = $response_json["data"]; // MW加密DATA
        $aesKey = self::rsa_decrypt($encryptKey, $EC_PRIVATE_KEY); // EC方私鑰 解密 MW加密KEY 獲得 AESKEY
        $decryptData = self::aes_decrypt($aesKey, $encryptData); // AESKEY 解密 MW加密DATA 獲得 DATA
        //echo_log("DecryptData: $decryptData");
        return json_decode($decryptData, true);
    }

    function generate_aes_key()
    {
        $aes = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0; $i < 16; $i++) {
            $aes .= $strPol[rand(0, $max)];
        }

        return $aes;
    }

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * @param mixed $in
     * @return int
     * @author twt326
     * @date 2022-09-13 16:32
     */
    function TokenTimestampTranser($in)
    {
        return $in;
    }

    function test()
    {
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;

        //其它配置多的，合并在token这里进行分割
        list($siteId, $merchantId, $firmId) = explode('_', $config['trdcfg']["tc_tag"]);
        $kvs['sign'] = $siteId;
        // 注册
        if ($kvs['runfunc'] == 'GETUSERURL') {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $data = [
                'uid' => $uname,// 区分大小写，总长度必须小于32个字符 (uid、utoken、merchantId 接入方自行生成與管理)
                'utoken' => md5($uname), // ec平台用户授权码，一次授权之后不可变更，长度必须为32个字符
                'firmId' => $firmId,
//                'jumpType' => '0',
                'gameId' => Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']),
                'currency' => $config['trdcfg']['tc_currency'],
                'lang' => $config['trdcfg']['tc_langmap']
            ];
        }

        // 获取用户余额
        if ($kvs['runfunc'] == 'GETBALANCE') {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $data = [
                'uid' => $uname,// 区分大小写，总长度必须小于32个字符 (uid、utoken、merchantId 接入方自行生成與管理)
                'utoken' => md5($uname), // ec平台用户授权码，一次授权之后不可变更，长度必须为32个字符
                'firmId' => $firmId,
            ];
        }
        // 转入/转出
        if ($kvs['runfunc'] == 'DEPOSIT' || $kvs['runfunc'] == 'WITHDRAW') {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $data = [
                'uid' => $uname,// 区分大小写，总长度必须小于32个字符 (uid、utoken、merchantId 接入方自行生成與管理)
                'utoken' => md5($uname), // ec平台用户授权码，一次授权之后不可变更，长度必须为32个字符
                'firmId' => $firmId,
                'transferType' => $kvs['runfunc'] == 'DEPOSIT' ? 0 : 1,
                'transferOrderNo' => date('YmdHis') . Utils::random(16),
                'transferAmount' => floatval(Third::transExchangerate($kvs['o_amount'], $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0, 2)),
                'transferClientIp' => Util::get_ip(),
                'currency' => $config['trdcfg']['tc_currency'],
            ];

        }
        //获取游戏注单数据-老虎机类型
        if ($kvs['runfunc'] == 'GETBETLIST') {
            $data = [
                'merchantId' => $merchantId,
                'beginTime' => $kvs['beginTime'],
                'endTime' => $kvs['endTime'],
                'page' => 1
            ];
        }

        $MW_PUBLIC_KEY = str_replace("\\n", "\n", $config['trdcfg']['tc_token']);

        date_default_timezone_set('Asia/Shanghai'); // 请使用北京时区
        Utils::L("{$config['trdcfg']['tc_id']}/http_data", [$kvs['runfunc'], $data, $kvs]);
        $keyData = self::encrypt_key_data($data, $MW_PUBLIC_KEY);
        $key = $keyData[0];
        $udata = $keyData[1];
        //return compact('key','udata');

        //$sign_data = $this->MakeSign($config,$data);
        $kvs['key'] = $key;
        $kvs['udata'] = $udata;
        //echo json_encode($data);die;
        Utils::L("{$config['trdcfg']['tc_id']}/http_data", [$kvs['runfunc'], $data, $kvs]);
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        Utils::L("{$config['trdcfg']['tc_id']}/ResultData", ['开始解密']);
//        $EC_PRIVATE_KEY = str_replace("\\n", "\n", $config['trdcfg']['tc_api_prikey']);
//        $EC_PUBLIC_KEY = str_replace("\\n", "\n", $config['trdcfg']['tc_api_signkey']);
//        try {
//            $res_data = self::decrypt_response(json_encode($datas), $EC_PRIVATE_KEY);
//            $datas = $res_data;
//        } catch (\Exception $e) {
//            Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['解密失败', $func, $datas]);
//            return false;
//        }
//        if ($res_data) {
//
//            Utils::L("{$config['trdcfg']['tc_id']}/ResultData", ['解密数据', $func, $res_data]);
//            if ($func == 'REGISTER') {
//                if ($res_data['ret'] == '0000') {
//                    $kds['tcuserkey'] = $uid;
//                } else {
//                    $kds['tcuserkey'] = '';
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//
//            if ($func == 'GETUSERURL') {
//                if ($res_data['ret'] == '0000') {
//                    $kds['tcgameurl'] = $res_data['interface2'];
//                } else {
//                    $kds['tcgameurl'] = '';
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//
//            if ($func == 'GETBALANCE') {
//
//                if ($res_data['ret'] == '0000') {
//
//                    $kds['tcbalance'] = $res_data['balance'];
//                } else {
//                    $kds['tcbalance'] = 0;
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//            if ($func == 'DEPOSIT' || $func == 'WITHDRAW') {
//
//                if ($res_data['ret'] == '0000') {
//                    $kds['tcbalance'] = $res_data['endMoney'];
//                } else {
//                    $kds['tcbalance'] = 0;
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//
//
//        } else {
//            Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['解密失败', $func, $datas]);
//        }
    }


//    function get_game_logs($apicfgs, $otherkvs)
//    {
//        /** 外部调用方法
//         * $apicfgs=Games::getConfigs(tcid,gid);
//         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
//         */
//        // $rlt = Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
//        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
//        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
//        //如果是封装中有的方法 可以调用，比如：
//        $cls = getc('ThirdTask_wm');
//        if (!$cls) return false;
//        /** @var ThirdTask_fb $ttobj */
//        $ttobj = new $cls();
//        $ttobj->init($apicfgs['trdcfg']['tc_id'], $apicfgs['trdcfg']['tc_code']);
//        $rlt = Http::http($apicfgs, 'GETBETLIST', $this, $otherkvs);
//
//        // Utils::L('wm_test', ['游戏参数', $rlt]);
//        if ($rlt) {
//            //处理API配置值
//            $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST']['ga_keyfield'], true);
//            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
////            Utils::L('wm_test',['游戏列表',$bds]);
//            $wds = [];
////            Utils::L('task_fb', ['游戏参数2', $bds]);
//            $bds = $ttobj->procBetDataPre($apicfgs, $bds, $rlt);
////            Utils::L('task_fb',['处理后的游戏列表',$bds]);
//            foreach ($bds as $bdk => $bdv) {
//                //Utils::L('test',['处理',$bdv]);
//                $bd = $ttobj->procBetData('all', $bdv);
////                Utils::L('test',['处理结果',$bdv]);
//                $bd = array_merge($bd, [
//                    'gb_tcid' => $apicfgs['trdcfg']['tc_id'],
//                    'gb_intime' => time(),
//                    'gb_betinfo' => json_encode($bdv)
//                ]);
//                array_push($wds, $bd);
//            }
//            //记录数据
////            Utils::L('test_fb',['合并结果',$wds]);
//            (new GameBetsTemp())->saveAll($wds);
//            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
//            ThirdTask::procBetTempData($apicfgs['trdcfg']['tc_useridentify'], 'tcid', $apicfgs['trdcfg']['tc_id'], 0);
//            $ttobj->transferBets($apicfgs['trdcfg']['tc_id'], 'all');
//
//        }
//    }
//
//    function get_special_logs($apicfgs, $otherkvs)
//    {
//        /** 外部调用方法
//         * $apicfgs=Games::getConfigs(tcid,gid);
//         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
//         */
//        // $rlt = Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
//        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
//        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
//        //如果是封装中有的方法 可以调用，比如：
//        $cls = getc('ThirdTask_wm');
//        if (!$cls) return false;
//        /** @var ThirdTask_wm $ttobj */
//        $ttobj = new $cls();
//        $ttobj->init($apicfgs['trdcfg']['tc_id'], $apicfgs['trdcfg']['tc_code']);
//        $rlt = Http::http($apicfgs, 'GETBETLISTSPEC', $this, $otherkvs);
//
//        // Utils::L('wm_test', ['游戏参数', $rlt]);
//        if ($rlt) {
//            //处理API配置值
//            $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST']['ga_keyfield'], true);
//            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
////            Utils::L('wm_test',['游戏列表',$bds]);
//            $wds = [];
////            Utils::L('task_fb', ['游戏参数2', $bds]);
//            $bds = $ttobj->procBetDataPre($apicfgs, $bds, $rlt);
////            Utils::L('task_fb',['处理后的游戏列表',$bds]);
//            foreach ($bds as $bdk => $bdv) {
//                //Utils::L('test',['处理',$bdv]);
//                $bd = $ttobj->procSpecialData($bdv);
////                Utils::L('test',['处理结果',$bdv]);
//                $bd = array_merge($bd, [
//                    'gs_tcid' => $apicfgs['trdcfg']['tc_id'],
//                    'gs_create_time' => time(),
//                    'gs_info' => json_encode($bdv)
//                ]);
//                $wds[] = $bd;
//            }
//            //记录数据
////            Utils::L('test_fb',['合并结果',$wds]);
//            (new GameWmSpecial())->replace()->saveAll($wds);
//            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
////            ThirdTask::procBetTempData($apicfgs['trdcfg']['tc_useridentify'], 'tcid', $apicfgs['trdcfg']['tc_id'], 0);
////            $ttobj->transferBets($apicfgs['trdcfg']['tc_id'], 'all');
//        }
//
//
//    }
}
