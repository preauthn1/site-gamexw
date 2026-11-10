<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\Games;

class Third_pgfslot extends Third
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
        unset($data['sign']);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $bodyStr = http_build_query(myArrayFilter($data));
        $data['sign'] = md5($bodyStr . "&key={$config['trdcfg']['tc_api_signkey']}");
//        Utils::L('OGS_transOtherKvs_Config', $config['trdcfg']);
//        Utils::L('OGS_transOtherKvs_MakeSign', $data);
        return $data;
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
        return time() + 3600 * 24 * 365; // 5天过期
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
        $kvs['operator_token'] = $config['trdcfg']['tc_token'];
        $kvs['ts'] = time();
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            //{"operator_token":"{operator_token}","user_id":"{user_id}","user_name":"{user_name}","ts":"{i|ts}","sign":"{sign}","currency":"{currency}","init_amount":"{init_amount}","init_amount_order_no":"{init_amount_order_no}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['user_id'] = $uname;
            $kvs['user_name'] = $uname;
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
        }
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            //{"operator_token":"{operator_token}","user_id":"{user_id}","user_token":"{uttoken}","game_code":"{game_code}","language":"{language}","ts":"{i|ts}","sign":"{sign}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['user_id'] = $uname;
            $kvs['game_code'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
            $kvs['language'] = $config['trdcfg']['tc_langmap'];
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            //{"operator_token":"{operator_token}","user_id":"{user_id}","ts":"{i|ts}","sign":"{sign}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['user_id'] = $uname;
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['operator_order_no'] = "s" . time() . rand(100000, 999999);
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['user_id'] = $uname;
            if ($kvs['runfunc'] === 'WITHDRAW') {
                $kvs['all_amount'] = 1;
            }
        }

    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
//        Utils::L('OGS_transResultData_list', $datas);
        if ($func == 'REGISTER') {
            $guser = UserThirds::where('ut_tcid', $tcid)->where('ut_uid', $uid)->find();
            if (!$guser) {
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_token' => $datas['data']['token'],
                    'ut_intime' => time(),
                    'ut_tokentime' => time() + 3600 * 24 * 365 * 10,
                    'ut_state' => 0
                ]);
            } else {
                $guser->ut_token = $datas['data']['token'];
                $guser->ut_tokentime = time() + 3600 * 24 * 365 * 10;
                //TODO 错误处理
                $guser->save();
            }
        }
    }
}
