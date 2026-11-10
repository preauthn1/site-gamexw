<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_mtzr extends Third
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
        if(isset($data['msg'])) unset($data['msg']);
        if(isset($data['msg'])) unset($data['fg_posttype']);
        $clientID = $config['trdcfg']['tc_tag'];
        $clientSecret = $config['trdcfg']['tc_api_prikey'];
        $DesKey = $config['trdcfg']['tc_api_signkey'];

        //token保存系统代码_站台代码_加密字符串
        $DesIV = $config['trdcfg']['tc_token'];
        $DesIV = explode('_',$DesIV);
        $DesIV = $DesIV[2];

        $timestamp = time();
        $APICI = $clientID;
        $APITS = $timestamp;
        $s_json = json_encode($data);

        $msg = $this->encryptionData($s_json,$DesKey,$DesIV);
        $data['msg'] = $msg;
        $APISI = $this->signatureData($s_json, $clientID,$clientSecret,$DesKey,$DesIV,$timestamp);

        return compact('APICI', 'APISI','APITS');
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
    public function encryptionData($data,$DesKey,$DesIV)
    {

        $encrypt_data = openssl_encrypt($data, 'DES-CBC', $DesKey, OPENSSL_RAW_DATA, $DesIV);
        $reqStr = base64_encode($encrypt_data);

        return $reqStr;
    }

    public function signatureData($data, $clientID,$clientSecret,$DesKey,$DesIV,$timestamp)
    {
        $encrypt_data = $this->encryptionData($data,$DesKey,$DesIV);

        $xdata =  $timestamp. $clientSecret . $clientID  . $encrypt_data;

        $signature = md5($xdata);
        return $signature;
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
        $kvs['currency'] = $config['trdcfg']['tc_currency'];
        $kvs['language'] = $config['trdcfg']['tc_langmap'];
        //token保存系统代码_站台代码_加密字符串
        $DesIV = $config['trdcfg']['tc_token'];
        $DesIV = explode('_',$DesIV);
        $kvs['system_code'] = $DesIV[0];
        $kvs['web_id'] = $DesIV[1];

        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['transfer_id'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['balance'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }

    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if( $func == 'GETBETLIST' ){
            //echo '1'; die;
        }
    }
}
