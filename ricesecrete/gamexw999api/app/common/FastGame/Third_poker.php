<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;

class Third_poker extends Third
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
        return true;
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
        if ($kvs['runfunc'] == 'GETUSERURL') {
            $uname = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $kvs['uid']);
            $data = [
                's' => 0,
                'account' => $uname,
                'money' => 0,
                'orderid' => $config['trdcfg']['tc_tag'] . date('YmdHiss', time()) . $uname,
                'ip' => $_SERVER["REMOTE_ADDR"],
                'lineCode' => $uname,
                'KindID' => $config['g_tag'],
            ];
            $param = http_build_query($data);
            $kvs['param'] = $this->desEncode($config['trdcfg']['tc_api_prikey'], $param);
            $timestamp = intval(microtime(1) * 1000);
            $kvs['timestamp'] = $timestamp;
            $kvs['key'] = md5($config['trdcfg']['tc_tag'] . $timestamp . $config['trdcfg']['tc_api_signkey']);
        }
        // TODO: Implement transOtherKvs() method.
    }

    function desEncode($key, $str)
    {
        $encrypt_str = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return urlencode(base64_encode($encrypt_str));
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $config = $this->apicfgs;
            $kds['tcgameurl'] = $datas['d']['url'].'&jumpType=1'.'&backUrl='.getAppUrl();
        }
    }

}
