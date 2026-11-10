<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_gr extends Third
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

        if(isset($data['credit_amount'])){
            $data['credit_amount'] = floatval($data['credit_amount']);
        }

        if(isset($data['debit_amount'])){
            $data['debit_amount'] = floatval($data['debit_amount']);
        }

        if(isset($data['page_index'])){
            $data['page_index'] = intval($data['page_index']);
        }

        if(isset($data['page_size'])){
            $data['page_size'] = intval($data['page_size']);
        }

        $signData = $data;

        $HashKey = $data['HashKey'];

        unset($signData['data']);
        unset($signData['HashKey']);

        $jsign = json_encode($signData);

        $userdata = $this->AESKeyEncryption($jsign, $HashKey);

        return compact('HashKey', 'userdata');
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
     * 生成签名
     *
     * @param string $agent_code - agent代码
     * @param string $agent_key - agent密钥
     * @param string $timestamp - 时间戳
     * @param string $secret_key - 密钥
     * @return string - 生成的签名
     */
    public function AESKeyEncryption($d, $a)
    {
        $salt = openssl_random_pseudo_bytes(8);
        $salted = $dx = '';
        while (strlen($salted) < 48) {
            $dx = md5($dx . $a . $salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);
        return base64_encode('Salted__' . $salt . openssl_encrypt($d . '', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
    }

    public function SignKeyEncryption($a,$signKey)
    {
        return md5($a.$signKey, false);
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
        $HashKey = $config['trdcfg']['tc_api_prikey'];
        $site_code =  $config['trdcfg']['tc_tag'];

        $kvs['Cookie'] = 'secret_key='.$HashKey;
        $kvs['HashKey'] = $HashKey;


        if($kvs['runfunc'] == 'REGISTER'){
            $kvs['site_code'] = $site_code;
        }

        if(in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW','GETBALANCE', 'GETUSERURL'])){

            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['account'] = $uname.'@'.$site_code;

        }

        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['traceId'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }

    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            $tag = Meta::initGetField('gtag',$tcid,$gid,$uid) ;

            if($tag > 500){
                $kds['tcgameurl'] = $datas['data']['game_url'].'?sid='.$datas['data']['sid'].'&classify=2';
            }else{
                $kds['tcgameurl'] = $datas['data']['game_url'].'?sid='.$datas['data']['sid'].'&classify=3,4';
            }

        }
    }
}
