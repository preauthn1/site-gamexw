<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;
use DateTime;

class Third_ob extends Third
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

        $contentMD5 =  base64_encode(pack('H*', md5(json_encode($data))));
        if (isset($data['agent'])) {
            $path = $config['apis']["REGISTER"]['ga_route'];
        } else {
            $path = $config['apis']["GETUSERURL"]['ga_route'];
        }
        $httpMethod = "POST";
        $contentType = "application/json; charset=UTF-8";
        $requestTime = date('D, d M Y H:i:s').' UTC';
        $stringToSign = $httpMethod . "\n"
        . $contentMD5 . "\n"
        . $contentType . "\n"
        . $requestTime . "\n"
        . $path;
        $tc_tag = $config['trdcfg']["tc_tag"];
        $deKey = base64_decode($config['trdcfg']["tc_api_signkey"]);
        $hash_hmac = hash_hmac("sha1", $stringToSign, $deKey, true);
        $encrypted = base64_encode($hash_hmac);
        $authorization = "AB" . " " . $tc_tag . ":" . $encrypted;
        
        return compact('requestTime', 'authorization', 'contentMD5');
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
        $authorization = $data['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        $contentType = "application/json; charset=UTF-8";
        $requestTime = $data['header']['date'];
        if ($data['param']["action_method"] == 'GetBalance') {
            $httpMethod = "GET";
            $path_arr = explode( '/ob', $data['param']["s"]);
            $path = $path_arr[1];
            $stringToSign = $httpMethod . "\n"
            .  "\n"
            .  "\n"
            . $requestTime . "\n"
            . $path;
        } else {
            $path = '/'.$data['param']["action_method"];
            unset($data['param']['s'], $data['param']['third'], $data['param']['action_method']);
            $contentMD5 =  base64_encode(pack('H*', md5(file_get_contents('php://input', 'r'))));
            $httpMethod = "POST";
            $stringToSign = $httpMethod . "\n"
            . $contentMD5 . "\n"
            . $contentType . "\n"
            . $requestTime . "\n"
            . $path;
        }
        $deKey = base64_decode($config['trdcfg']["tc_api_prikey"]);
        $hash_hmac = hash_hmac("sha1", $stringToSign, $deKey, true);
        $encrypted = base64_encode($hash_hmac);
        if($encrypted == $authorization_arr[1]){
            return true;
        }
        return false;
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
        /*Utils::L('test',['TokenTimestampTranser',$in,time()+$in-600]);
        return time()+$in-600;*/
    }

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'REGISTER') {
            $kds['tcuserkey'] = substr($datas['data']['player'], 0, -3);
        }
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['data']['gameLoginUrl'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjE3LCJ0Y19uYW1lIjoiT0IgU3BvcnRzIiwidGNfaW50aW1lIjoiMjAyMy0wMS0wNiAxMjo1NjoxMyIsInRjX2NvZGUiOiJPQiIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvc3cyLmFwaWRlbW8ubmV0Ojg0NDMiLCJ0Y19hcGlfcHJpa2V5IjoiQkE4Z2d4NEhWY1NDTDlkazE3cEFRWDRITlpka3dVU1wvaENuM1haZmxNT1d2NmlQS2VPZXVmaE1QWXZoTHFtOWh6dDJIcmh2WThFVVM3MjlWbUU1RnF3PT0iLCJ0Y19hcGlfc2lnbmtleSI6IkFSQzNVME1mXC9DaVN3MXFib1V3UURsd2V2VEpMbjVsQWh2UTN0UGRvM2NheEJ5MnM3VTM3U1dHb3RoQk9MeUdcL2NLd01LU215cER5Rk5UT0x3eENDNVE9PSIsInRjX3RhZyI6IjgxOTI1MTAiLCJ0Y19jdXJyZW5jeSI6IlRXRCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjAuMDAxMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJDTiIsInRjX3N0YXR1cyI6MCwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjMwMTA2XC8wZjIyZWRjNmJhYWI4ZDA4ZTMwMGFjNzI5MTU2YmYyNy5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4wMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiIiLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjpudWxsLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjQ1LCJnYV90Y2lkIjoxNywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9Mb2dpbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb247IGNoYXJzZXQ9VVRGLThcIixcIkFjY2VwdFwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcIkRhdGVcIjpcIntyZXF1ZXN0VGltZX1cIixcIkF1dGhvcml6YXRpb25cIjpcInthdXRob3JpemF0aW9ufVwiLFwiQ29udGVudC1NRDVcIjpcIntjb250ZW50TUQ1fVwifSIsImdhX3BwYXJhbSI6IntcInBsYXllclwiOlwie3VuYW1lfWhtalwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmVzdWx0Q29kZVwiLFwidmFsdWVcIjpcIk9LXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJnYW1lTG9naW5VcmxcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjo0NCwiZ2FfdGNpZCI6MTcsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL0NoZWNrT3JDcmVhdGUiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uOyBjaGFyc2V0PVVURi04XCIsXCJBY2NlcHRcIjpcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJEYXRlXCI6XCJ7cmVxdWVzdFRpbWV9XCIsXCJBdXRob3JpemF0aW9uXCI6XCJ7YXV0aG9yaXphdGlvbn1cIixcIkNvbnRlbnQtTUQ1XCI6XCJ7Y29udGVudE1ENX1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJhZ2VudFwiOlwiazVmeWthXCIsIFwicGxheWVyXCI6XCJ7dW5hbWV9aG1qXCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmVzdWx0Q29kZVwiLFwidmFsdWVcIjpcIk9LXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH1dfQ==';
    }
}
