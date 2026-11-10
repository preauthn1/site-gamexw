<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_fff extends Third
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

    /**
     * 生成签名
     *
     * @param string $agent_code - agent代码
     * @param string $agent_key - agent密钥
     * @param string $timestamp - 时间戳
     * @param string $secret_key - 密钥
     * @return string - 生成的签名
     */
    private function makeQueryString($params) {
        if (empty($params)) {
            return '';
        }
        ksort($params);
        $tmp = [];
        foreach ($params as $k => $param) {
            $tmp[] = $k . '=' . $param;
        }
        return implode('&', $tmp);
    }

    private function encrypt($queryString, $key) {
        $cipher = "AES-256-CBC";
        $iv_len = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_len);
        $ciphertext_raw = openssl_encrypt($queryString, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext_raw);
    }

    private function produceSign($queryString, $signature) {
        return md5($queryString . $signature);
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
        $agent_key = $config['trdcfg']['tc_api_prikey'];
        $signKey = $config['trdcfg']['tc_api_signkey'];

        $kvs['sip'] = getenv('SERVER_ADDR');
        if($kvs['runfunc'] != 'GETBETLIST'){
            $pass = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $name = 'fff_'.$pass;
        }

        $jsdata['_t'] = time();
        if($kvs['runfunc'] == 'GETUSERURL'){
            $jsdata['xusername'] = $name;
            $jsdata['xpassword'] = $pass;
            $jsdata['cry'] = $config['trdcfg']['tc_currency'];
            $jsdata['lang'] = $config['trdcfg']['tc_langmap'];

        }
        if($kvs['runfunc'] == 'GETBALANCE'){
            $jsdata['username'] = $name;

        }

        if($kvs['runfunc'] == 'GETBETLIST'){
            $jsdata['from_time'] = $kvs['start_time'];
            $jsdata['end_time'] = $kvs['end_time'];

            $jsdata['limit'] = $kvs['limit'];
            $jsdata['page'] = $kvs['page'];
        }

        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $jsdata['username'] = $name;
            $jsdata['order_no'] = date("YmdHis") . Utils::random(16);
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $jsdata['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }

        $queryString = $this->makeQueryString($jsdata);

        $encryptedString = $this->encrypt($queryString, $agent_key);

        $sign = $this->produceSign($queryString, $signKey);

        $kvs['params'] = $encryptedString;
        $kvs['sign'] = $sign;
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        //   if( $func == 'REGISTER' ){
        //         $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
        //         if( !$guser ){
        //             UserThirds::create([
        //                 'ut_uid' => $uid,
        //                 'ut_tcid' => $tcid,
        //                 'ut_token' => $datas['data']['token'],
        //                 'ut_trdid' => $datas['data']['user_id'],
        //                 'ut_intime' => time(),
        //                 'ut_trdname' => $datas['data']['player_id'],
        //                 'ut_state' => 0
        //             ]);
        //         }else{
        //             $guser->save([
        //                 'ut_token' => $datas['data']['token'],
        //                 'ut_trdid' => $datas['data']['user_id'],
        //                 'ut_trdname' => $datas['data']['player_id'],
        //             ]);
        //         }
        //     }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjU2LCJ0Y19uYW1lIjoiRkZGIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxOTozMzoyOSIsInRjX2NvZGUiOiJGRkYiLCJ0Y19zZXRfY29kZSI6IiIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvd3d3LmZmYWFwaS1od3c2NnUuY29tIiwidGNfYXBpX3ByaWtleSI6IjVhODIzZTE4NjRiNDZhYjYyY2M1MjY4OTVhYjE3ZGI2IiwidGNfYXBpX3NpZ25rZXkiOiJmZjgyYTAwODBkMzhjOWNlZTI1M2JjZDcxZjUxNjIwOSIsInRjX3RhZyI6IkZGRiIsInRjX2N1cnJlbmN5IjoidHdkIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InR3IiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwOTE0XC8zMGViZWU0ZjRkZjU5OGRiMWViMDUzODgxNzNlNWI1MC5qcGciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMTAuMDAwMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAwMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDkxNFwvNzBmNDViMDQ3YTU1YzFjMmNhY2RjNzJkY2FjN2RlZjguanBnIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJpc19uZXdfdGFiIjowLCJ0Y19kaXNhYmxlX2FnZW50X2lkcyI6IjAifSwiYXBpcyI6W3siZ2FfaWQiOjI1MCwiZ2FfdGNpZCI6NTYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQiLCJnYV9yb3V0ZSI6Ilwvb3BlblwvdHJhbnNmZXJcL2NoYXJnZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwicmVmZXJlclwiOlwie3NpcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwicGFyYW1zXCI6XCJ7cGFyYW1zfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjUxLCJnYV90Y2lkIjo1NiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9vcGVuXC90cmFuc2Zlclwvd2FsbGV0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCIsXCJyZWZlcmVyXCI6XCJ7c2lwfVwifSIsImdhX3BwYXJhbSI6IntcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCIsXCJwYXJhbXNcIjpcIntwYXJhbXN9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoyNTIsImdhX3RjaWQiOjU2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL29wZW5cL29yZGVyXC91cGRhdGVkIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCIsXCJyZWZlcmVyXCI6XCJ7c2lwfVwifSIsImdhX3BwYXJhbSI6IntcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCIsXCJwYXJhbXNcIjpcIntwYXJhbXN9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGEuZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI5NDUyOTcxLFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI5NDUyOTcxLFwic3RhcnRfdGltZVwiOlwiMjAyNC0xMC0yMSAwMzozMDoxMVwiLFwiZW5kX3RpbWVcIjpcIjIwMjQtMTAtMjEgMDM6MzU6MTFcIixcInBhZ2VcIjowLFwibGltaXRcIjo1MDAwfX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoyNTMsImdhX3RjaWQiOjU2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL29wZW5cL3VzZXJcL2xvZ2luIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCIsXCJyZWZlcmVyXCI6XCJ7c2lwfVwifSIsImdhX3BwYXJhbSI6IntcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCIsXCJwYXJhbXNcIjpcIntwYXJhbXN9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLmdhbWVfdXJsXCJ9fSIsImdhX3JhdGUiOjEwLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjI1NSwiZ2FfdGNpZCI6NTYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IldJVEhEUkFXIiwiZ2Ffcm91dGUiOiJcL29wZW5cL3RyYW5zZmVyXC93aXRoZHJhdyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwicmVmZXJlclwiOlwie3NpcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwicGFyYW1zXCI6XCJ7cGFyYW1zfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
