<?php


namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games, GameWmSpecial, UserThirds};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_gbs extends Third
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
//
//        $head_sign = $config['trdcfg']["tc_token"];
//        return compact('head_sign');
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
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
//        $config['trdcfg']['tc_api_signkey']
        $uname = '';
        $data = [];
        if( $kvs['runfunc'] != 'GETBETLIST' ){
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
        }
        if( $kvs['runfunc'] == 'REGISTER' ){
            //注册
            $data = [
                'action' => 'register',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'uid' => $uname,
                'name' => $uname,
            ];
        }elseif( $kvs['runfunc'] == 'GETUSERURL' ){
            //获取游戏地址
            $data = [
                'action' => 'login',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'lang' => $config['trdcfg']['tc_api_prikey'],
                'uid' => $uname,
                'is_https' => 1,
            ];
            if( $config['g_tag'] ){
                //有游戏标签则为游戏登录
                $data['gameCode'] = $config['g_tag'];
            }else{
                //没有则为大厅登录
                $data['action'] = 'lobbyLogin';
            }
        }elseif( $kvs['runfunc'] == 'GETBALANCE' ){
            //获取用户余额
            $data = [
                'action' => 'getMoney',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'uid' => $uname,
            ];
        }elseif( $kvs['runfunc'] == 'DEPOSIT' ){
            //充值
            $data = [
                'action' => 'transfer',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'uid' => $uname,
                'orderNo' => date('YmdHis'),
                'amount' => rtrim(rtrim(sprintf('%.2f', Third::transExchangerate($kvs['o_amount'], 1, 2)), '0'), '.'),
                'isForce' => 1,
            ];
        }elseif( $kvs['runfunc'] == 'WITHDRAW' ){
            //提现
            $data = [
                'action' => 'transfer',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'uid' => $uname,
                'orderNo' => date('YmdHis'),
                'amount' => -rtrim(rtrim(sprintf('%.2f', Third::transExchangerate($kvs['o_amount'], 0, 2)), '0'), '.'),
                'isForce' => 1,
            ];
        }elseif( $kvs['runfunc'] == 'GETBETLIST' ){
            //获取游戏注单
            $data = [
                'action' => 'getDetailed',
                'appID' => $config['trdcfg']['tc_tag'],
                'appSecret' => $config['trdcfg']['tc_api_prikey'],
                'starttime' => date('Y-m-d H:i:s', $kvs['starttime']),
                'endtime' => date('Y-m-d H:i:s', $kvs['endtime']),
                'page' => $kvs['page']??1,
                'pernumber' => $kvs['pernumber']??5000,
            ];
        }
        $kvs['x'] = $this->encryptData($data, $config['trdcfg']['tc_api_signkey'], $config['trdcfg']['tc_token']);
        // TODO: Implement transOtherKvs() method.
    }

    //加密参数
    private function encryptData($data, $signKey, $encryptKey){
        $data['sign_key'] = $this->generateSign($data, $signKey);
        return $this->des_ecb_encrypt(json_encode($data), $encryptKey);
    }

    //签名
    private function generateSign(&$data,$signKey){
        ksort($data);
        $string = Http::ToUrlParams($data);
        $string = $string .'&sign_key='. $signKey;
        return strtoupper(md5($string));
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }
        }
    }

    public function des_ecb_encrypt($data, $key) {
        // 使用 DES-ECB 模式加密数据
        $encryptedData = openssl_encrypt($data, 'DES-ECB', $key, OPENSSL_RAW_DATA);
        // 将加密后的数据编码为 Base64 以便于存储或传输
        return base64_encode($encryptedData);
    }

    public function des_ecb_decrypt($encryptedData, $key) {
        // 将 Base64 编码的数据解码
        $encryptedData = base64_decode($encryptedData);
        // 使用 DES-ECB 模式解密数据
        return openssl_decrypt($encryptedData, 'DES-ECB', $key, OPENSSL_RAW_DATA);
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjM2LCJ0Y19uYW1lIjoiR0JcdTk2ZmJcdTViNTAiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIwIDIzOjQ0OjQyIiwidGNfY29kZSI6IkdCUyIsInRjX3NldF9jb2RlIjoiIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkuamF2ODg4OS5jb21cL2diUmVxdWVzdCIsInRjX2FwaV9wcmlrZXkiOiJoaDFmTTV6RlM5cFF0WHdFOERyejU0R1cydm9LVENtWSIsInRjX2FwaV9zaWdua2V5IjoiMzU3ZDRjZjU1NWQ2YjRhMThkZDE2MTc0ODdiZjZiYWQiLCJ0Y190YWciOiJtQTRCckFIRzNSU01GT0hqeGgiLCJ0Y19jdXJyZW5jeSI6IiIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6ImViZmM4Y2M5ZTNiNDExMTE0MjA0OWJlNzA4YzNiMDdjIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InR3IiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC8zYjE5ZDk5NDViMWQ2YjE0ODRiMWI2NTM1NTZkZWVkYi5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMS4wMDAwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC8wM2M2MzUzNTk5NjZiYTAwN2Y2YTNkOWQ5OGYxYzM0NC5wbmciLCJ0Y190aW1lem9uZSI6OCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTI0LCJnYV90Y2lkIjozNiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcInhcIjpcInt4fVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJldHVybkNvZGVcIixcInZhbHVlXCI6XCIwMDAwXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMjUsImdhX3RjaWQiOjM2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJsYW5nXCI6XCJ7dGNsYW5nbWFwfVwiLFwieFwiOlwie3h9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmV0dXJuQ29kZVwiLFwidmFsdWVcIjpcIjAwMDBcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuQW1vdW50XCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTI2LCJnYV90Y2lkIjozNiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcInhcIjpcInt4fVwifSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmV0dXJuQ29kZVwiLFwidmFsdWVcIjpcIjAwMDBcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGEubGlzdFwifX0iLCJnYV9yYXRlIjozLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1NTU5OTM2LFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI1NTU5OTM2LFwic3RhcnR0aW1lXCI6MTcyNTU1OTU4MyxcImVuZHRpbWVcIjoxNzI1NTU5ODgzLFwicGFnZVwiOjAsXCJwZXJudW1iZXJcIjo1MDAwfX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMjcsImdhX3RjaWQiOjM2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJsYW5nXCI6XCJ7dGNsYW5nbWFwfVwiLFwieFwiOlwie3h9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmV0dXJuQ29kZVwiLFwidmFsdWVcIjpcIjAwMDBcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEucGF0aFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjEyOCwiZ2FfdGNpZCI6MzYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJsYW5nXCI6XCJ7dGNsYW5nbWFwfVwiLFwieFwiOlwie3h9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJldHVybkNvZGVcIixcInZhbHVlXCI6XCIwMDAwLDEwMDFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMjksImdhX3RjaWQiOjM2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcInhcIjpcInt4fVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJldHVybkNvZGVcIixcInZhbHVlXCI6XCIwMDAwXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }
}
