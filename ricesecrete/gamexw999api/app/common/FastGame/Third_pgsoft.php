<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Meta, Third, Utils};

//PGSoft 游戏
class Third_pgsoft extends Third
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
        return true;
    }

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * @param  mixed  $in
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
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $password = substr(md5($kvs['uid']), 0, 12);
            $kvs['password'] = $password;
            // Signature = MD5(operatorcode + password + providercode + type + username + secret_key), then convert to uppercase 加密后转大写.
            $type = 'SL';
            $kvs['signature'] = strtoupper(md5($config['trdcfg']['tc_api_prikey'].$password.$config['trdcfg']['tc_tag'].$type.$uname.$config['trdcfg']['tc_api_signkey']));
            $kvs['gameid'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            // Signature = MD5(operatorcode + username +secret_key), then convert to uppercase 加密后转大写.
            $kvs['signature'] = strtoupper(md5($config['trdcfg']['tc_api_prikey'].$uname.$config['trdcfg']['tc_api_signkey']));

        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            // 截取md5生成的前16位字符串
            $kvs['referenceid'] = substr(date("YmdHis").Utils::random(16), 0, 16);
            $kvs['password'] = substr(md5($kvs['uid']), 0, 12);
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);

            // Signature = MD5(amount + operatorcode + password + providercode + referenceid + type + username + secret_key), then convert to uppercase 加密后转大写.
            $kvs['signature'] = strtoupper(md5($kvs['amount'] .$config['trdcfg']['tc_api_prikey'].$kvs['password'].$config['trdcfg']['tc_tag'].$config['trdcfg']['tc_api_signkey']));
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['password'] = substr(md5($kvs['uid']), 0, 12);
            // Signature = MD5(operatorcode + password + providercode + username + secret_key), then convert to uppercase 加密后转大写.
            $kvs['signature'] = strtoupper(md5($config['trdcfg']['tc_api_prikey'].$kvs['password'].$config['trdcfg']['tc_tag'].$uname.$config['trdcfg']['tc_api_signkey']));

        }
        Utils::L('PG_transOtherKvs', $kvs);
//        Utils::L('OGS_transOtherKvs', $kvs['runfunc']);
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        Utils::L('PG_transOtherKvsData', $kds);
//        Utils::L('OGS_transResultData', $datas);

//        if (in_array($func, ['REGISTER'])) {
//            $kds['tcuserkey'] = "";
//        }
    }
}
