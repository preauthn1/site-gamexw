<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};

class Third_pb extends Third
{

    private $method = 'AES-128-CBC';
    private $iv = 'RandomInitVector';
    private $config = [];

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
        $this->config = $config;
        return $this->get_sign();
    }

    private function encryptAES($secretKey, $tokenPayLoad)
    {
        $encrypt = openssl_encrypt($tokenPayLoad, $this->method, $secretKey, OPENSSL_RAW_DATA, $this->iv);
        return base64_encode($encrypt);
    }


    private function get_sign($time = ''){
        $timestamp = $time ? $time  : time() * 1000;
        $userCode = $this->config['trdcfg']['tc_tag'];
        $hashToken = md5($userCode. $timestamp . $this->config['trdcfg']['tc_api_prikey']);
        $tokenPayLoad = $userCode . '|' . $timestamp . '|' . $hashToken;
        $token = $this->encryptAES($this->config['trdcfg']['tc_api_signkey'], $tokenPayLoad);
        return compact('token', 'userCode');
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
        $this->config = $config;
        $time = $data['Timestamp'];
        $sign_arr = $this->get_sign($time);
        $str = str_replace(' ', '+', rawurldecode($data['Signature']));
        if($sign_arr['token'] != $str){
            return false;
        }
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
//        $platform = $kvs['platform'] ?? 0;
//        $kvs['platForm'] = $platform == 1 ? 'pc' : 'mobile'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL' && $ohterkvs['platform'] == 2 && isset($ohterkvs['back_url'])){
            $back = urlencode($ohterkvs['back_url']);
            $kds['tcgameurl'] .= "&externalUrl={$back}";
        }
    }
}
