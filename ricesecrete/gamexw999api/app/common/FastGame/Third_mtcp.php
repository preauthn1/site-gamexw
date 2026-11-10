<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_mtcp extends Third
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

//        if(isset($data['credit_amount'])){
//            $data['credit_amount'] = floatval($data['credit_amount']);
//        }
//
//        if(isset($data['debit_amount'])){
//            $data['debit_amount'] = floatval($data['debit_amount']);
//        }
//
//        if(isset($data['page_index'])){
//            $data['page_index'] = intval($data['page_index']);
//        }
//
//        if(isset($data['page_size'])){
//            $data['page_size'] = intval($data['page_size']);
//        }
        $IV = $config['trdcfg']['tc_api_signkey'];

        $signData = $data;

        $HashKey = $data['HashKey'];

        unset($signData['Data']);
        unset($signData['HashKey']);

        $jsign = json_encode($signData);

        $userdata = $this->AESKeyEncryption($jsign, $HashKey,$IV);

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
    public function AESKeyEncryption($RawData, $HashKey, $HashIv)
    {
        $EncryptedData = openssl_encrypt($RawData, 'aes-256-cbc', $HashKey, 0,
            base64_decode(base64_decode($HashIv)));
        return base64_encode($EncryptedData);

    }

    public function dec($RawData,$HashKey,$HashIv){
        $FinalData =openssl_decrypt(base64_decode($RawData), 'aes-256-cbc',
            $HashKey, 0, base64_decode(base64_decode($HashIv)));
        $FinalData = json_decode($FinalData,true);
        return $FinalData;
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
        $lang = $config['trdcfg']['tc_langmap'];
        $kvs['HashKey'] = $HashKey;


        if($kvs['runfunc'] == 'REGISTER'){
            //$kvs['site_code'] = $site_code;
        }

        if(in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW','GETBALANCE', 'GETUSERURL'])){

            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['account'] = $site_code.'-'.$uname;
            $kvs['langs'] = $lang;
            $kvs['gamecode'] = 'lottery';

        }

        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['traceId'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            if($tomeorthird == 0){
                $kvs['amount'] = $kvs['amount'] * -1;
            }
        }

    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($datas['status'] == 'true'){
            $config = $this->apicfgs;

            $IV = $config['trdcfg']['tc_api_signkey'];
            $HashKey = $config['trdcfg']['tc_api_prikey'];
            $ndata = $this->dec($datas['data'],$HashKey,$IV);

            if($func == 'GETUSERURL'){

                $kds['tcgameurl'] = $ndata['rows'][0]['url'];
            }

            if($func == 'DEPOSIT'){

                $kds['tcbalance'] = $ndata['rows']['TranPoint'];
            }
            if($func == 'WITHDRAW'){

                $kds['tcbalance'] = abs($ndata['rows']['TranPoint']);
            }
            if($func == 'GETBALANCE'){

                $kds['tcbalance'] = $ndata['rows']['MemberPoint'];
            }

        }

    }
}
