<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_afhot extends Third
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
        return true;
    }

    private function rsaSign($data, $privateKey)
    {
        // 使用 OpenSSL 签名
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
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
        $kvs['trace_id'] = time().round(10000,99999);
        $uid = $kvs['uid'];
        $apiKey = $config['trdcfg']['tc_api_prikey'];

        $apiSecret = $config['trdcfg']['tc_api_signkey'];
        $threePubKey =  $config['trdcfg']['tc_tag'];
        $kvs['playerName'] = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid);

        $kvs['api_key'] = $apiKey;
        $kvs['api_secret'] = $apiSecret;

        $kvs['apiKey'] = $apiKey;
        $kvs['timestamp'] = time() * 1000;

        $kvs['nickName'] = $kvs['playerName'];

        if($kvs['runfunc'] == 'REGISTER') {
            $kvs['ip'] = $_SERVER['SERVER_ADDR'];//Utils::get_ip();
            $kvs['currency'] = $config['trdcfg']['tc_currency'];

            $params = [
                'apiKey' =>$kvs['apiKey'],
                'ip' => $kvs['ip'],
                'playerName' => $kvs['playerName'],
                'nickName' => $kvs['nickName'],
                'currency' => $kvs['currency'],
                'timestamp' => (int)$kvs['timestamp']
            ];

            // $params = [
            //     'apiKey' =>'00000191f6243a570000000045ff9aba',
            //     'ip' => '192.168.1.1',
            //     'timestamp' => '1725958722000'
            // ];

            // 1. 按照键进行排序
            ksort($params);

            // 2. 生成待加密串
            $stringToEncrypt = http_build_query($params);
            // Signing
            $myprikey = self::prikey();
            $mypubkey = self::pubkey();
            $sign = self::signature($myprikey,$stringToEncrypt);

            //echo $sign;die;
            //"Signature: $signature\n";

            // Verifying
            //$verificationResult = self::verifySignature($mypubkey, $stringToEncrypt, $signature);
            //echo "Verification Result: " . ($verificationResult ? "true" : "false") . "\n";die;

        }

        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['ip'] = Utils::get_ip();
            $kvs['gamePlat'] = $kvs['platform']==2?'mobile':'web';
            $kvs['gameKey'] = $config['g_tag'];
            $kvs['languages'] = $config['trdcfg']['tc_langmap'];

            $params = [
                'apiKey' =>$kvs['apiKey'],
                'ip' => $kvs['ip'],
                'playerName' => $kvs['playerName'],
                'gameKey' => $kvs['gameKey'],
                'timestamp' => (int)$kvs['timestamp']
            ];
            // 1. 按照键进行排序
            ksort($params);

            // 2. 生成待加密串
            $stringToEncrypt = http_build_query($params);

            // Signing
            $myprikey = self::prikey();
            $mypubkey = self::pubkey();
            $sign = self::signature($myprikey,$stringToEncrypt);
        }
        $kvs['timestampss'] = $kvs['timestamp'];
        $kvs['ip'] = urlencode($kvs['ip']);
        $kvs['sign'] = $sign;

        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        // if( $func == 'GETUSERURL' ){
        //   $dataes = json_decode($datas,true);
        //   $kds['tcgameurl'] = $dataes['data']['data'];
        // }
    }

    function signature($priKey, $str) {

        $privateKeyResource = openssl_pkey_get_private($priKey);

        if (!$privateKeyResource) {
            throw new Exception('Invalid private key');
        }


        openssl_sign($str, $signature, $privateKeyResource, OPENSSL_ALGO_SHA1);


        openssl_free_key($privateKeyResource);


        $sign = base64_encode($signature);

        return str_replace(['+', '/'], ['-', '_'], $sign);
    }

    function verifySignature($pubKey, $content, $sign) {
        if (empty($pubKey) || empty($content) || empty($sign)) {

            return false;
        }


        //$publicKey = base64_decode($pubKey);


        $publicKeyResource = openssl_pkey_get_public($pubKey);

        if (!$publicKeyResource) {
            error_log('Invalid public key');
            return false;
        }


        $decodedSignature = base64_decode(str_replace(' ', '+', $sign));


        $result = openssl_verify($content, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA1);


        openssl_free_key($publicKeyResource);

        return $result === 1;
    }
    function prikey(){

        return 'REDACTED';
    }
    function pubkey(){

        return '-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAtprXb7HtgG4jv7UWRN1p
hjARt2FvHX34QcueDfCbAz4i3B3WQ/GpuFKZpPslGrYP9S5Kui9n2Th2FE5V/O7l
qvp8WvytoZ9wgWV8Wkv2DwRZaZlzdx5vhtSp/3oKqk+hpl59sIK42H7jXAczAKJY
yKbmp6yWgqgbXpxwHvpFLsVz1cl9RHbY8HKs9/NBIcMraEVqmog5Oy0263MXivp9
UzDdVSJ9+hCc9Fz60TGW24mWZak3C4XVqE0GK6Y01DVPckSjVQj3WF1m5u9xNgCi
3mtPMss9VGVYaQj0xp1UtaaLHp2no2fHe9IFYU8N6CJTWFuxCLPKJXUeUh1LJFP6
MFLJxTn0Z5/2DyRXz1/lRVhU1ZwqN/WlHtRSvi68+map2W2OKTM6Wll3M1tmhz3G
RcSaGmUkCiPAqW/us5Bk4AhLL/tBWQFfu3X9HJhLPmc3JBNxNn2z0OOMKE4C8o2u
NEExYLpyaPGZ4f5IVRBJ68L9uvtzOUhTWR0XlzKNM/SsNs1M3Z8Wd3B4baneqn2J
8ISDqmBiEoXnE/YiL+3kK9h47+GV2rmkTJl7UZSaW+gZ/2MILz4n0wpUP/OOSurZ
7lHYXwsK4vDE2fb1bG9ZehHHo3F4l43qZ06+5KYe3Bz80etN+lMvsRbzd/L5j/zt
yo1V2YSLk4L5OS7GmZbO2q8CAwEAAQ==
-----END PUBLIC KEY-----';
    }


}