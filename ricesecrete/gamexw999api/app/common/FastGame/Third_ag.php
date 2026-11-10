<?php

namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games, GameWmSpecial, UserThirds};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_ag extends Third
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
        // $header = [
        // "alg" => "HS256"
        // ];
        // $header_str = base64_encode(json_encode($header));
        // $data_str = base64_encode(json_encode($data));
        // $data['head_sign'] = HMACSHA256($header_str.'.'.$data_str, $config['tc_api_prikey']);
        // $head_sign = 'Bearer '.hash('sha512', $header_str.'.'.$data_str, $config['trdcfg']['tc_api_prikey']);
        $head_sign = 'Bearer '.$config['trdcfg']['tc_api_prikey'];
        // hash('sha512', $data, $rawOutput)
        // dump($head_sign);
        return compact('head_sign');
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

        $header = $data['header'];
        $param = $data['param'];
        //token等于我方的token
        if($header['authorization'] == 'Bearer '.$config['trdcfg']['tc_api_prikey']){
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

    function test(){
        return 'KM CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $kvs['password'] = 'acf_pass_147258369';
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $config = $this->apicfgs;
            $name = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid);
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $name,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->ut_trdid =  $name;
                //TODO 错误处理
                $guser->save();
            }
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIyLCJ0Y19uYW1lIjoiQUcgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAyLTE2IDEzOjUxOjAyIiwidGNfY29kZSI6IkFHIiwidGNfc2V0X2NvZGUiOm51bGwsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvYXBpLmRzODhzYWJvbmcueHl6IiwidGNfYXBpX3ByaWtleSI6ImV5SmhiR2NpT2lKSVV6STFOaUo5LmV5SnBaQ0k2TVRNMU5EZ3dMQ0psYldGcGJDSTZJbUV6TVRZMmNHaHdRR1J6T0RoellXSnZibWN1ZUhsNkluMC5Bc2Vfd3FnX0otR3Yzb2VIWlpCOTBXTDI0cmZDVEpmWWNiaDBwbnFYSVhNIiwidGNfYXBpX3NpZ25rZXkiOiJQNTlHRnp4QlhIMzZraWlPM3FyN1pJZnYzMmxlR3k1OXZBbHJZZ3lCWENyQXB4S1AiLCJ0Y190YWciOiJBMzE2NlBIUCIsInRjX2N1cnJlbmN5IjoiUEhQIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6IkNOIiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2N5YmVycmFhY2Nvbi5zMy5hcC1zb3V0aGVhc3QtMS5hbWF6b25hd3MuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjMwMjIyXC9iOGRiMTc3NjY2M2NkMGIwNmMwMzIyZjZjODI4OTVkOS5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4xMCIsInRjX21heF9iZXQiOiIzMDAwMDAwMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6IiIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwiaXNfbmV3X3RhYiI6MCwidGNfZGlzYWJsZV9hZ2VudF9pZHMiOiIwIn0sImFwaXMiOlt7ImdhX2lkIjo1OSwiZ2FfdGNpZCI6MjIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvYXBpXC9tZXJjaGFudFwvcGxheWVyXC9sb2dpbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcIlgtUmVxdWVzdGVkLVdpdGhcIjpcIlhNTEh0dHBSZXF1ZXN0XCIsXCJBdXRob3JpemF0aW9uXCI6XCJ7aGVhZF9zaWdufVwifSIsImdhX3BwYXJhbSI6IntcImxvZ2luXCI6XCJ7dW5hbWV9XCIsXCJwYXNzd29yZFwiOlwie3Bhc3N3b3JkfVwiLFwibGFuZ1wiOlwie3RjbGFuZ21hcH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOlwiT0tcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImdhbWVfbGlua1wifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjU1LCJnYV90Y2lkIjoyMiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvYXBpXC9tZXJjaGFudFwvcGxheWVycyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcIlgtUmVxdWVzdGVkLVdpdGhcIjpcIlhNTEh0dHBSZXF1ZXN0XCIsXCJBdXRob3JpemF0aW9uXCI6XCJ7aGVhZF9zaWdufVwifSIsImdhX3BwYXJhbSI6IntcImFjY291bnRcIjpcInt1bmFtZX1cIixcInBhc3N3b3JkXCI6XCJ7cGFzc3dvcmR9XCIsXCJuYW1lXCI6XCJ7dW5hbWV9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCJPS1wifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }


}
