<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_sa extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
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
        $time = date('YmdHis',time());
        if(in_array($kvs['runfunc'],['GETUSERURL','GETBALANCE','DEPOSIT','WITHDRAW'])){
            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $tccurrency = Meta::initGetField('tccurrency', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 货币
        }
        // 获取游戏URL
        if($kvs['runfunc'] == 'GETUSERURL') {
            $data = [
                'method'=>'LoginRequest',
                'Key'=>$config['trdcfg']['tc_tag'],
                'Time'=>$time,
                'Username'=>$uname,
                'CurrencyType'=>$tccurrency,
            ];
        }
        // 获取用户余额
        if($kvs['runfunc'] == 'GETBALANCE') {
            $data = [
                'method'=>'GetUserStatusDV',
                'Key'=>$config['trdcfg']['tc_tag'],
                'Time'=>$time,
                'Username'=>$uname
            ];
        }
        // 转入
        if($kvs['runfunc'] == 'DEPOSIT') {
            $data = [
                'method'=>'CreditBalanceDV',
                'Key'=>$config['trdcfg']['tc_tag'],
                'Time'=>$time,
                'Username'=>$uname,
                'OrderId'=>'IN'.$time.$uname,
                'CreditAmount'=>floatval(Third::transExchangerate($kvs['o_amount'], 1, 2)),
                'CurrencyType'=>$tccurrency,
            ];
        }
        //转出
        if($kvs['runfunc'] == 'WITHDRAW') {
            $data = [
                'method'=>'DebitAllBalanceDV',
                'Key'=>$config['trdcfg']['tc_tag'],
                'Time'=>$time,
                'Username'=>$uname,
                'OrderId'=>'OUT'.$time.$uname,
            ];
        }
        //获取游戏注单数据-老虎机类型
        if($kvs['runfunc'] == 'GETBETLIST') {
            $data = [
                'method'=>'GetAllBetDetailsForTimeIntervalDV',
                'Key'=>$config['trdcfg']['tc_tag'],
                'Time'=>$time,
                'FromTime'=>$kvs['FromTime'],
                'ToTime'=>$kvs['ToTime'],
            ];
        }
        $kvs['q'] = $this->Encrypt($data,$config['trdcfg']['tc_api_prikey'],$config['trdcfg']['tc_api_prikey']);
        $kvs['s'] = $this->md5sign($data,$config['trdcfg']['tc_api_signkey'],$time,$config['trdcfg']['tc_tag']);
        Utils::L("{$config['trdcfg']['tc_id']}/http_data",[$kvs['runfunc'], $data]);
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        $res_data = Http::FromXml($datas);
        if($res_data){
            Utils::L("{$config['trdcfg']['tc_id']}/ResultData",['返回数据',$func, $res_data]);
            if($func == 'GETUSERURL'){
                if($res_data['ErrorMsgId'] == 0) {
                    $url = $config['trdcfg']['tc_token'];
                    $lobby = 'A9935';
                    $u = Meta::initGetField('uname',$tcid,$gid,$uid);
                    $is_mobile = $ohterkvs['platform'] == 1 ? 'false' : 'true';
                    $lang = $config['trdcfg']['tc_langmap'];
                    $returnurl = getAppUrl();
                    $kds['tcgameurl'] = $url.'?token='.$res_data['Token'].'&username='.$u.'&lobby='.$lobby.'&lang='.$lang.'&returnurl='.$returnurl.'&mobile='.$is_mobile;
                }else{
                    $kds['tcgameurl'] = '';
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['状态异常',$func, $res_data]);
                }
            }

            if($func == 'GETBALANCE' || $func == 'DEPOSIT'){
                if($res_data['ErrorMsgId'] == 0) {
                    $kds['tcbalance'] = $res_data['Balance'];
                }else{
                    $kds['tcbalance'] = 0;
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['状态异常',$func, $res_data]);
                }
            }

            if($func == 'WITHDRAW'){
                if($res_data['ErrorMsgId'] == 0) {
                    $kds['tcbalance'] = 0;
                }else{
                    $kds['tcbalance'] = 0;
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['状态异常',$func, $res_data]);
                }
            }
            if( $res_data['ErrorMsgId'] != 0 ){
                throw new \Exception('状态异常', 500);
            }
        }else{
            Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['数据异常', $func,$datas]);
        }
    }

    // DES加密
    function Encrypt($data, $DesKey, $DesIV)
    {
        $encrypt_data = base64_encode(openssl_encrypt(http_build_query($data),'DES-CBC',$DesKey,OPENSSL_RAW_DATA ,$DesIV));
        return $encrypt_data;
    }

    //MD5加密
    function md5sign($data,$md5key, $Time, $SecretKey)
    {
        $PreMD5Str = http_build_query($data) . $md5key . $Time . $SecretKey;
        return md5($PreMD5Str);
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQ1LCJ0Y19uYW1lIjoiU0FcdTc3MWZcdTRlYmEiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTI5IDE4OjE3OjQxIiwidGNfY29kZSI6IlNBIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5zYS1hcGlzdnIuY29tXC9hcGlcL2FwaS5hc3B4IiwidGNfYXBpX3ByaWtleSI6Imc5RzE2blRzIiwidGNfYXBpX3NpZ25rZXkiOiJHZ2FJTWFpTk50ZyIsInRjX3RhZyI6IjVBNzE2NDQ4Mzc2MzRGQkM4NTc5OTc2N0VCNEMzRjFFIiwidGNfY3VycmVuY3kiOiJUV0QiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiJodHRwczpcL1wvd2ViLnNhLWdsb2JhbHhucy5jb21cL2FwcC5hc3B4IiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InpoLWhhbnQiLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzkwOWZlNzU2MmIwYWQ2NTE0YmIzMGU4OTM5MWMzMTg4LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC83YmQwYzYyYmE3MDQ3NzExZGU2YzkwZjUwNWJmNzhiZS5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTgzLCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC9DcmVkaXRCYWxhbmNlRFYiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJxXCI6XCJ7cX1cIixcInNcIjpcIntzfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTgyLCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9HZXRVc2VyU3RhdHVzRFYiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJxXCI6XCJ7cX1cIixcInNcIjpcIntzfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTgxLCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC9HZXRBbGxCZXREZXRhaWxzRm9yVGltZUludGVydmFsRFYiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJxXCI6XCJ7cX1cIixcInNcIjpcIntzfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImh0dHBjb2RlXCIsXCJ2YWx1ZVwiOjIwMH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcInJhd2RhdGFcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTU1ODkyOCxcImN1cnRpbWVcIjowLFwiRnJvbVRpbWVcIjpcIjIwMjQtMDktMDYgMDE6NDk6MTlcIixcIlRvVGltZVwiOlwiMjAyNC0wOS0wNiAwMTo1NDoxOVwiLFwiZW5kXCI6MTcyNTU1ODkyOH19IiwiZ2FfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5zYS1ycHQuY29tXC9hcGlcL2FwaS5hc3B4In0seyJnYV9pZCI6MTgwLCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9Mb2dpblJlcXVlc3QiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJxXCI6XCJ7cX1cIixcInNcIjpcIntzfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTc5LCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvRGViaXRBbGxCYWxhbmNlRFYiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJxXCI6XCJ7cX1cIixcInNcIjpcIntzfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}