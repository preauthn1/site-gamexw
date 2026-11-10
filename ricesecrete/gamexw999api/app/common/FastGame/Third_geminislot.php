<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third};
use app\model\UserThirds;

class Third_geminislot extends Third
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
        $body_str = json_encode($data);
        return ['els_access_key' => md5("{$config['trdcfg']['tc_api_prikey']}{$body_str}")];
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
        $kvs['product_id'] = $config['trdcfg']['tc_tag'];
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","currency":"{currency}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['username'] = $uname;
            $kvs['seq'] = time() . rand(1000, 9999);
            $kvs['default_currency'] = $config['trdcfg']['tc_currency'];//货币
        }
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","game_code":"{game_code},"language":"{language}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['gametype'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
            $kvs['game_lang'] = $config['trdcfg']['tc_langmap'];
            $kvs['username'] = $uname;
            $kvs['seq'] = time() . rand(1000, 9999);
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['username'] = $uname;
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['seq'] = time() . rand(1000, 9999);
            $kvs['transfer_id'] = "S".date("YmdHis") . rand(1000, 9999);
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['username'] = $uname;
            $kvs['seq'] = time() . rand(1000, 9999);
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

    }
}
