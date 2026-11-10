<?php


namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_fbapp extends Third
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
        //签名步骤一：按字典序排序参数
        ksort($data);
        $timestamp = strval(time())."000";
        $body_str = json_encode($data);
        $head_sign = md5("{$body_str}.{$config['trdcfg']['tc_tag']}.{$timestamp}.{$config['trdcfg']['tc_api_prikey']}");
        return compact('head_sign',  'timestamp');
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
        $param = $data['param'];
        $header = $data['header'];
        $ip_list = explode(',', $config['trdcfg']['tc_api_signkey']);
        //渠道id 等于数据库中的渠道id 或者ping请求的时候为true  并且请求ip要跟数据库定义的ip相等
        if(in_array($header['x-forwarded-for'], $ip_list)){
            if($param['callback'] == 'health'){
                return true;
            }
            if(($param['merchantId'] ?? $param[0]['merchantId']) == $config['trdcfg']['tc_tag']){
                return true;
            }
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
    }

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $platform = $kvs['platform'] ?? 0;
        $kvs['platForm'] = $platform == 1 ? 'pc' : 'mobile'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            $info = $datas['data'];
            $arr = ['url' => $info['serverInfo']['apiServerAddress'], 'token' => $info['token'], 'currency' => Meta::initGetField('tccurrency', $tcid, $gid, $uid)];
            $kds['tcgameurl'] = json_encode($arr);
        }
        if($func == 'REGISTER'){
            $kds['tcusername'] = Meta::initGetField('uname', $tcid, $gid, $uid);
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjE1LCJ0Y19uYW1lIjoiRkIgU3BvcnRzIiwidGNfaW50aW1lIjoiMjAyMi0xMi0yNSAxOTo0MDo0NCIsInRjX2NvZGUiOiJGQkFQUCIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvYXBpZGF0YTQuZmJzNjY2OC5jb21cL2ZiXC9kYXRhXC9hcGlcL3YyIiwidGNfYXBpX3ByaWtleSI6IjlkUGx5NVQwdGhvM2hGaDM0bXhVREhOT21SNWhHOXNKIiwidGNfYXBpX3NpZ25rZXkiOiI1NC45NS45LjYxIiwidGNfdGFnIjoiMTY4OTg0ODg5NzU2NDM5NzU3MCIsInRjX2N1cnJlbmN5IjoiMjIiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoiRU5HIiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyMzA5MDNcLzIzMDc4NWEyYzYyYmUyZTEyMjY0OGI3MWRkYjA4MDRiLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIxLjAwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjMwOTAzXC85NzU0M2MzM2FjY2ZiMmFmNTQ2YjYxMjY5MGZiYzhhYS5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjpudWxsLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjQwLCJnYV90Y2lkIjoxNSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC90b2tlblwvZ2V0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwiLFwic2lnblwiOlwie2hlYWRfc2lnbn1cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIixcIm1lcmNoYW50SWRcIjpcInt0Y3RhZ31cIn0iLCJnYV9wcGFyYW0iOiJ7XCJtZXJjaGFudFVzZXJJZFwiOlwie3VuYW1lfVwiLFwiaXBcIjpcInt1aXB9XCIsXCJwbGF0Rm9ybVwiOlwie3BsYXRGb3JtfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEudXJsXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjozOSwiZ2FfdGNpZCI6MTUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL25ld1wvdXNlclwvY3JlYXRlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwiLFwic2lnblwiOlwie2hlYWRfc2lnbn1cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIixcIm1lcmNoYW50SWRcIjpcInt0Y3RhZ31cIn0iLCJnYV9wcGFyYW0iOiJ7XCJtZXJjaGFudFVzZXJJZFwiOlwie3VuYW1lfVwiLFwiY3VycmVuY3lJZHNcIjpbXCJ7dGNjdXJyZW5jeX1cIl19IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN1Y2Nlc3NcIixcInZhbHVlXCI6dHJ1ZX0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcImRhdGFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9XX0=';
    }



}
