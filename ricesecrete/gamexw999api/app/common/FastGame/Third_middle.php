<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};


class Third_middle extends Third
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
        if (isset($data['sign'])) $data['sign']='{sign}';
        //签名步骤一：按字典序排序参数
        // ksort($data);
        $string = Http::ToUrlParams($data,false,['sign', 'CreateTime', 'password', 'language', 'backURL']);
        //签名步骤二：在string后加入KEY
        $string = $string .'&apikey='. $config['trdcfg']['tc_api_signkey'];
        //签名步骤三：MD5加密
        $sign = md5($string);
        Utils::L('32/http', ['testsign',$data, $string, $sign]);
        return compact('sign');
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
        $data = $data['param'];
        //签名步骤一：按字典序排序参数
        // ksort($data);
        $string = Http::ToUrlParams($data,false,['sign', 'CreateTime', 'password', 'language', 'backURL', 's', 'third', 'action_method']);
        //签名步骤二：在string后加入KEY
        $string = $string .'&apikey='. $config['trdcfg']['tc_api_signkey'];
        //签名步骤三：MD5加密
        $sign = md5($string);
        if( $sign == $data['sign'] ){
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
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        if( $kvs['runfunc'] == 'REGISTER' || $kvs['runfunc'] == 'GETUSERURL' ){
            $kvs['playername'] = $config['trdcfg']['tc_tag'] . '_' . Meta::initGetField('uname',$config['trdcfg']['tc_id'],$config['g_id'],$kvs['uid']);

            if( $kvs['runfunc'] == 'GETUSERURL' ){
                $kvs['backURL'] = getAppUrl();
            }
        }

    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if ($func == 'REGISTER') {
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

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIwLCJ0Y19uYW1lIjoiUFAgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAyLTE0IDE3OjU1OjE2IiwidGNfY29kZSI6IlBQIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGktYnIxLnByYWdtYXRpY3BsYXkubmV0XC9JbnRlZ3JhdGlvblNlcnZpY2VcL3YzXC9odHRwXC9DYXNpbm9HYW1lQVBJIiwidGNfYXBpX3ByaWtleSI6IlByYWdtYXRpY1BsYXkiLCJ0Y19hcGlfc2lnbmtleSI6Ijg3QjQwZEMxRTY0NTRlRTAiLCJ0Y190YWciOiJ6ZjM3OWRfdDY2IiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoicHQiLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjg4ODgsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvYTUzOWYyYTBkZmUwYzE4MWVmMGFhMTZjYWZiZDBkMmIucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjEsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MTFcLzNjOWE3YzQxNDMzMWM1NjU0MjZkNjMxMTc4ZjM2OTg5LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NTIsImdhX3RjaWQiOjIwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2dhbWVcL3VybCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6IntcInNlY3VyZUxvZ2luXCI6XCJ7dGN0YWd9XCIsXCJzeW1ib2xcIjpcIntndGFnfVwiLFwibGFuZ3VhZ2VcIjpcInt0Y2xhbmdtYXB9XCIsXCJ0b2tlblwiOlwie3VuYW1lfVwiLFwicGxhdGZvcm1cIjpcIntwbGF0Zm9ybX1cIixcImxvYmJ5VXJsXCI6XCJ7cmNDbG9zZVVybH1cIixcImNhc2hpZXJVcmxcIjpcIntjYXNoaWVyVXJsfVwiLFwiaGFzaFwiOlwie2hhc2h9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yXCIsXCJ2YWx1ZVwiOjB9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJnYW1lVVJMXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }

}