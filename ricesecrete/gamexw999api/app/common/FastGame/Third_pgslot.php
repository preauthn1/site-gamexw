<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\Games;

class Third_pgslot extends Third
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
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = json_encode($data);
        $trace_id = time() . rand(100000, 999999);
        $data['trace_id'] = $trace_id;
        $data['sign'] = md5("trace_id=$trace_id{$string}{$config['trdcfg']['tc_api_signkey']}");
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
        return time() + 3600*24*5; // 5天过期
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
        $kvs['app_id'] = $config['trdcfg']['tc_tag'];
        $kvs['timestamp'] = time();
        if (in_array($kvs['runfunc'], ['GETAUTH'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","currency":"{currency}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['nickname'] = "pgslot_" . $uname;
            $kvs['uname'] = $uname;
            $kvs['cid'] = 0;
        }
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","game_code":"{game_code},"language":"{language}"}
            $kvs['gameid'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
            $kvs['lang'] = $config['trdcfg']['tc_langmap'];
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","currency":"{currency}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['nickname'] = "pgslot_" . $uname;
            $kvs['uname'] = $uname;
            $kvs['cid'] = 0;
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['tx_id'] = "s" . time() . rand(100000, 999999);
            if ($tomeorthird == 1) {
                $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            } else {
                $kvs['amount'] = 0 - Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            }
        }

    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        Utils::L('OGS_transResultData_list', $datas);
        if ($func == 'REGISTER') {
            $guser = UserThirds::where('ut_tcid', $tcid)->where('ut_uid', $uid)->find();
            if (!$guser) {
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_token' => $datas['data']['token'],
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            } else {
                $guser->ut_token = $datas['data']['token'];
                //TODO 错误处理
                $guser->save();
            }
        }
    }
}
