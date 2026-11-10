<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Meta, Third, Utils};
use app\common\FastGame\Base\Redis;

//RGLIVE
class Third_rglive extends Third
{
    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     */
    function MakeSign($config, &$data)
    {
        //其它配置多的，合并在signkey这里进行分割
        $signkey = explode('|', $config['trdcfg']["tc_api_signkey"]);
        $client = explode('|', $config['trdcfg']["tc_api_prikey"]);
        $req_base64 = $this->Encrypt($signkey[0], json_encode($data), $signkey[1]);
        $timestamp = time();
        $signature_data = $client[0] . $client[1] . $timestamp . $req_base64;
        $signature = base64_encode(hex2bin(md5($signature_data)));
        $data_temp['req_base64'] = urlencode($req_base64);
        $data_temp['client_id'] = $client[0];
        $data_temp['signature'] = $signature;
        $data_temp['timestamp'] = $timestamp;
        return $data_temp;
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
        // 此三方站回复数据无签名
        return true;
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

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;

        //其它配置多的，合并在token这里进行分割
        $token = explode('|', $config['trdcfg']["tc_tag"]);
        $data = [];
        if (in_array($kvs['runfunc'], ['REGISTER', 'GETUSERURL', 'GETBALANCE', 'DEPOSIT', 'WITHDRAW'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $tccurrency = Meta::initGetField('tccurrency', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 货币
            // 注册
            if ($kvs['runfunc'] == "REGISTER") {
                $lang = Meta::initGetField('tclangmap', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 语言
                $data = [
                    'SystemCode' => $token[0],
                    'WebId' => $token[1],
                    'MemberAccount' => $uname,
                    'MemberName' => $uname,
                    "StopBalance" => -1,
                    "BetLimitGroup" => "1,2,3",
                    'Currency' => $tccurrency,
                    "Language" => $lang,
                    "OpenGameList" => "ALL",
                ];
            }
            // 获取游戏URL
            if ($kvs['runfunc'] == 'GETUSERURL') {
                $lang = Meta::initGetField('tclangmap', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 语言
//                $gtag = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 游戏代码
                $data = [
                    'SystemCode' => $token[0],
                    'WebId' => $token[1],
                    "MemberAccount" => $uname,
                    "ItemNo" => "1",
                    "BackUrl" => "#",
//                    "GameDeskID" => '',
                    "GroupLimitID" => "1,2,3",
                    "Lang" => $lang,
                    "ClubType" => "classicBacc"
                ];
            }
            // 获取用户余额
            if ($kvs['runfunc'] == 'GETBALANCE') {
                $data = [
                    'SystemCode' => $token[0],
                    'WebId' => $token[1],
                    "MemberAccount" => $uname
                ];
            }
            // 转入/转出
            if ($kvs['runfunc'] == 'DEPOSIT' || $kvs['runfunc'] == 'WITHDRAW') {
                $ran = Utils::random(20);
                $key = "RG_rand_" . $uname;
                $rand = Redis::get($key);
                if (!$rand) {
                    Redis::setex($key, $ran, 3);
                    $rand = $ran;
                }
                $data = [
                    'SystemCode' => $token[0],
                    'WebId' => $token[0],
                    'MemberAccount' => $uname,
                    'TransactionId' => $rand,
                    'TransactionAmount' => floatval(Third::transExchangerate($kvs['o_amount'], $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0, 2)),
                ];
            }
            $data = $this->MakeSign($config, $data);
            foreach ($data as $k => $v) {
                $kvs[$k] = $v;
            }
            Utils::L("{$config['trdcfg']['tc_id']}/http_data", [$kvs['runfunc'], $data, $kvs]);
        }
        // TODO: Implement transOtherKvs() method.
    }

    // 转换第三方返回数据为本平台标准数据格式

    /**
     * @throws \Exception
     */
    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
//        $config = $this->apicfgs;
//        Utils::L("{$config['trdcfg']['tc_id']}/ResultDataNo", ['未数据', $func, $datas]);
//        //其它配置多的，合并在signkey这里进行分割
//        $res_data = $datas;
//        if ($res_data) {
//            $res_data = json_decode($res_data, true);
//            Utils::L("{$config['trdcfg']['tc_id']}/ResultData", ['解密数据', $func, $res_data]);
//
//            if ($func == 'GETBETLIST') {
//                if ($res_data['msgId'] == 0) {
//                    $kds['r_list'] = $res_data['data']['dataList'];
//                } else {
//                    $kds['r_list'] = [];
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//            if ($func == 'REGISTER') {
//                if ($res_data['msgId'] == 0) {
//                    $kds['tcuserkey'] = $res_data['data']['memberAccount'];
//                } else {
//                    $kds['tcuserkey'] = '';
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//
//            if ($func == 'GETUSERURL') {
//                if ($res_data['msgId'] == 0) {
//                    $kds['tcgameurl'] = $res_data['data']['url'];
//                } else {
//                    $kds['tcgameurl'] = '';
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data]);
//                }
//            }
//
//            if ($func == 'GETBALANCE' || $func == 'DEPOSIT' || $func == 'WITHDRAW') {
//                if ($res_data['msgId'] == 0) {
//                    $kds['tcbalance'] = $res_data['data']['balance'];
//                } else {
//                    $kds['tcbalance'] = 0;
//                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['数据错误', $func, $res_data], true, true);
//                    //TODO 这里必须强行阻断，否则会导致三方未成功，但是我们平台成功了
//                    throw new \Exception("三方转入/转出失败{$func}");
//                }
//            }
//        } else {
//            Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error", ['解密失败', $func, $datas], true, true);
//        }
    }


    function transFinallyPostdata($func, &$kvs, $data)
    {
        return $kvs['body'];
    }

    // 加密
    function Encrypt($DesKey, $data, $DesIV)
    {
        $encrypt_data = openssl_encrypt($data, 'DES-CBC', $DesKey, OPENSSL_RAW_DATA, $DesIV);
        $req_base64 = base64_encode($encrypt_data);
        return $req_base64;
    }

    // 解密
    function Decrypt($DesKey, $data, $DesIV)
    {
        return openssl_decrypt(base64_decode($data), 'DES-CBC', $DesKey, OPENSSL_RAW_DATA, $DesIV);
    }

    /**
     * 解密数据
     * @param $config
     * @param $data
     * @return false|string
     */
    function DecData($config, $data)
    {
        $signkey = explode('|', $config['trdcfg']["tc_api_signkey"]);
        return $this->Decrypt($signkey[0], $data, $signkey[1]);
    }
}