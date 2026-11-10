<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_zg extends Third
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

        $agent_code = $config['trdcfg']['tc_tag'];
        $agent_key = $config['trdcfg']['tc_api_prikey'];
        $signKey = $config['trdcfg']['tc_api_signkey'];

        $channel = $config['trdcfg']['tc_token'];

        $signData = $data;

        unset($signData['data']);
        unset($signData['sign']);
        unset($signData['channel']);
        $signData['agent'] = $agent_code;

        $jsign = json_encode($signData);

        $userdata = $this->AESKeyEncryption($jsign, $agent_key);
        $sign = $this->SignKeyEncryption($userdata,$signKey);

        return compact('channel', 'sign','userdata');
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
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['traceId'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }

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
        return 'eyJjb25maWciOnsidGNfaWQiOjQ0LCJ0Y19uYW1lIjoiWkdcdTk2ZmJcdTViNTAiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE5OjMzOjI5IiwidGNfY29kZSI6IlpHIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5mYWZhZmE4OTlhcGkuY29tIiwidGNfYXBpX3ByaWtleSI6IjVKc3hMN1hjR3RhZGUyYlN2UFlsSUVSSG5nbUZXeTZ1IiwidGNfYXBpX3NpZ25rZXkiOiJMdFdhSks0UzdNemdJeWpUY0h2Nm5kazBDVlFvTkVSdyIsInRjX3RhZyI6IlhXMDAwMVRUVCIsInRjX2N1cnJlbmN5IjoiVFdEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiMzg0Nzk4MzIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiemgtVFciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzIyMWUyNGZjYjQ3MTJhNWVhOTMzZTI3ZDRiZmE5YjIyLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIxMC4wMDAwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC8yMDM2MTdkMTBlZTkzNWM0YWIzZDE3ZGM4NjhkYjM4ZS5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTcyLCJnYV90Y2lkIjo0NCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC92MVwvdHJhbnNcL3RyYW5zZmVyIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcImNoYW5uZWxcIjpcIntjaGFubmVsfVwiLFwiZGF0YVwiOlwie3VzZXJkYXRhfVwiLFwic2lnblwiOlwie3NpZ259XCIsXCJhZ2VudFwiOlwie2FnZW50fVwiLFwiYW1vdW50XCI6XCJ7YW1vdW50fVwiLFwic2VyaWFsXCI6XCJ7dHJhY2VJZH1cIixcImFjY291bnRcIjpcInt1bmFtZX1cIixcIm9wZXJfdHlwZVwiOjF9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmVzdWx0Lm1zZ1wiLFwidmFsdWVcIjpcIlNVQ0NFU1NcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNzMsImdhX3RjaWQiOjQ0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiJcL3YxXC90cmFuc1wvY2hlY2tfYmFsYW5jZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJjaGFubmVsXCI6XCJ7Y2hhbm5lbH1cIixcInNpZ25cIjpcIntzaWdufVwiLFwiZGF0YVwiOlwie3VzZXJkYXRhfVwiLFwiYWNjb3VudFwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJlc3VsdC5tc2dcIixcInZhbHVlXCI6XCJTVUNDRVNTXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJiYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTc0LCJnYV90Y2lkIjo0NCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC92MVwvcmVjb3JkXC9nZXRfYmV0X3JlY29yZHMiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wiY2hhbm5lbFwiOlwie2NoYW5uZWx9XCIsXCJkYXRhXCI6XCJ7dXNlcmRhdGF9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIixcImZpbmlzaF90aW1lXCI6XCJ7ZmluaXNoX3RpbWV9XCIsXCJpbmRleFwiOlwie2luZGV4fVwiLFwibGltaXRcIjo1MDAwfSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmVzdWx0LmNvZGVcIixcInZhbHVlXCI6MX0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcInJvd3NcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTU1OTA0MyxcImN1cnRpbWVcIjowLFwiaW5kZXhcIjowLFwiZW5kXCI6MTcyNTU1OTA0MyxcInN0YXJ0X3RpbWVcIjoxNzI1NTU4Njg2LFwiZW5kX3RpbWVcIjoxNzI1NTU4OTg2LFwibGltaXRcIjo1MDAwLFwidXNlckNvZGVcIjpcIlhXMDAwMVRUVFwiLFwiY2hhbm5lbFwiOlwiMzg0Nzk4MzJcIixcImZpbmlzaF90aW1lXCI6XCJ7XFxcInN0YXJ0X3RpbWVcXFwiOlxcXCIyMDI0LTA5LTA2VDAxOjUxOjI2KzA4OjAwXFxcIixcXFwiZW5kX3RpbWVcXFwiOlxcXCIyMDI0LTA5LTA2VDAxOjU2OjI2KzA4OjAwXFxcIn1cIixcInVzZXJkYXRhXCI6XCJVMkZzZEdWa1gxXFxcL2dvMU1ycnJLYnBoOVJLNnRYZXBqNnV4dGVlS0ZzMldNOFI1Qk5PMUJDdTZFZTVjNkg0QkNYQVE1bVpYQlJKR0tXaGl1Q2Rrak5FWHduU2lKcENVME41Y1NyQXh6c3M5NGZ2VXNqeEJMZ3JaTWpLa05uaVRWNWJyYzVpclc1Q05mQURFZGpqeFd3ZGRTMnJpNjNqRDV1c05mZ2dqYTN3NlpNWnJTV25FdVNqR040VWNLVmxwbU1cIixcInNpZ25cIjpcImIyNGQ3MzM4NDIxNDZjNmQxZWMzMzkxMzZmM2MzODcwXCJ9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE3NSwiZ2FfdGNpZCI6NDQsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvdjFcL21lbWJlclwvbG9naW5fZ2FtZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJjaGFubmVsXCI6XCJ7Y2hhbm5lbH1cIixcImRhdGFcIjpcInt1c2VyZGF0YX1cIixcInNpZ25cIjpcIntzaWdufVwiLFwiYWdlbnRcIjpcInthZ2VudH1cIixcImFjY291bnRcIjpcInt1bmFtZX1cIixcImdhbWVfaWRcIjpcIntndGFnfVwiLFwibGFuZ1wiOlwiemhfY25cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyZXN1bHQubXNnXCIsXCJ2YWx1ZVwiOlwiU1VDQ0VTU1wifSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwidXJsXCJ9fSIsImdhX3JhdGUiOjEwLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE3NiwiZ2FfdGNpZCI6NDQsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL3YxXC9tZW1iZXJcL2NyZWF0ZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJjaGFubmVsXCI6XCJ7Y2hhbm5lbH1cIixcImRhdGFcIjpcInt1c2VyZGF0YX1cIixcInNpZ25cIjpcIntzaWdufVwiLFwiYWNjb3VudFwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJyZWciLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyZXN1bHQubXNnXCIsXCJ2YWx1ZVwiOlwiU1VDQ0VTU1wifSxcInRjdXNlcmtleVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTc3LCJnYV90Y2lkIjo0NCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvdjFcL3RyYW5zXC90cmFuc2ZlciIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJjaGFubmVsXCI6XCJ7Y2hhbm5lbH1cIixcImRhdGFcIjpcInt1c2VyZGF0YX1cIixcInNpZ25cIjpcIntzaWdufVwiLFwiYWdlbnRcIjpcInthZ2VudH1cIixcImFtb3VudFwiOlwie2Ftb3VudH1cIixcInNlcmlhbFwiOlwie3RyYWNlSWR9XCIsXCJhY2NvdW50XCI6XCJ7dW5hbWV9XCIsXCJvcGVyX3R5cGVcIjowfSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJlc3VsdC5tc2dcIixcInZhbHVlXCI6XCJTVUNDRVNTXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJiYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
