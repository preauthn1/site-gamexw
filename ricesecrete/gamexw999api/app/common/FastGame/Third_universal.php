<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;

class Third_universal extends Third
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
        //$postData = http_build_query($data);
        $postData = json_encode($data);
        $str = hash_hmac('sha256', $postData, $config['trdcfg']['tc_api_signkey']);
        return $str;
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
        Utils::L('test',['checksign','data'=>$data]);
        if (!isset($data['return_headers'])){
            return false;
        }
        $header=$data['return_headers'];
        unset($data['s'],$data['third'], $data['game_sys_sign'], $data['action_method'], $data['gid'], $data['uid'], $data['return_headers']);
        Utils::L('test',['checksign:1','data'=>$data,'header:'=>$header]);

        $postData = json_encode($data);
        $str = hash_hmac('sha256', $postData, $config['trdcfg']['tc_api_signkey']);
        Utils::L('test',['checksign:2',$header['x-request-sign'],$str]);
        if($str != $header['x-request-sign']){
            Utils::L('test',['body'=>$data,'local' => $header['x-request-sign'], 'header' => $str, 'msg' => '签名错误']);
            return false;
        }
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

    function test(){
        return 'universal CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQsInRjX25hbWUiOiJVbml2ZXJzYWwgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIyLTA5LTMwIDA5OjU5OjA1IiwidGNfY29kZSI6IlVOSVZFUlNBTCIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvaW50LmJnYW1pbmctc3lzdGVtLmNvbVwvYThyXC9ibHVlbGFiZWwtaW50IiwidGNfYXBpX3ByaWtleSI6IkVTRkpHTlloTTNNaDc3Y2RtMzI0aDMzOSIsInRjX2FwaV9zaWdua2V5IjoiRVNGSkdOWWhNM01oNzdjZG0zMjRoMzM5IiwidGNfdGFnIjoiYmx1ZWxhYmVsLWludCIsInRjX2N1cnJlbmN5IjoiVVNEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjpudWxsLCJ0Y190b2tlbnRpbWUiOm51bGwsInRjX3Byb3h5Y29uZmlnIjpudWxsLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJ7XCJ6aC1DTlwiOid6aCd9IiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyMzAxMjJcLzFmMTMyODQ2Y2ZiNzQ0OTBjN2U3OGI3NWNkZmVkODBjLmpwZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjEwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6IiIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTUsImdhX3RjaWQiOjQsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6Ilwvc2Vzc2lvbnMiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJYLVJFUVVFU1QtU0lHTlwiOlwie2hlYWRfc2lnbn1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJjYXNpbm9faWRcIjpcInt0Y3RhZ31cIixcImdhbWVcIjpcIntndGFnfVwiLFwiY3VycmVuY3lcIjpcInt0Y2N1cnJlbmN5fVwiLFwibG9jYWxlXCI6XCJ7bGFuZ31cIixcImlwXCI6XCJ7dWxhc3RpcH1cIixcImNsaWVudF90eXBlXCI6XCJ7Y2xpZW50X3R5cGV9XCIsXCJiYWxhbmNlXCI6XCJ7dWJhbGFuY2V9XCIsXCJ1cmxzXCI6e1wicmV0dXJuX3VybFwiOlwiXCJ9LFwidXNlclwiOntcImlkXCI6XCJ7dW5hbWV9XCIsXCJuaWNrbmFtZVwiOlwie3VuYW1lfVwifX0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImxhdW5jaF9vcHRpb25zLmdhbWVfdXJsXCJ9fSIsImdhX3JhdGUiOjM2MDAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH1dfQ==';
    }
}
