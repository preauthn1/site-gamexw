<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;
use DateTime;

class Third_evo extends Third
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
        $data['player']['session']['id'] = $sid = md5($data['player']['id']);
        return $sid;
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
        $sign = md5(json_encode($data,JSON_UNESCAPED_SLASHES));
        return $sign;
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
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['entry'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjI0LCJ0Y19uYW1lIjoiRVZPIiwidGNfaW50aW1lIjoiMjAyMy0wMi0xNiAxOToyODo1MiIsInRjX2NvZGUiOiJFVk8iLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5sdWNreWxpdmVnYW1lcy5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiZjc5OTU1M2M2NjkxYjM2YmE3MzAwNDdjM2I1MjA4MDEiLCJ0Y19hcGlfc2lnbmtleSI6IiIsInRjX3RhZyI6Im81dXVzdG5rYWdvYTR5ZWciLCJ0Y19jdXJyZW5jeSI6IkJSTCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJDTiIsInRjX3N0YXR1cyI6MSwic29ydCI6OTcsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvNmViNzdhNWJlZTViNjdlYzk3ZTE2NjVmOGEyOGQwMzQucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MTFcL2IzZjQ3MWU2MTI5YmQ2ZWYzOGNiNDZlNGI2ZTVmMWVlLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NjcsImdhX3RjaWQiOjI0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL3VhXC92MVwve3RjdGFnfVwve3RjYXBpa2V5fSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInV1aWRcIjpcIntyYW5kb219XCIsXCJwbGF5ZXJcIjp7XCJpZFwiOlwie3VuYW1lfVwiLFwibGFuZ3VhZ2VcIjpcInB0LUJSXCIsXCJ1cGRhdGVcIjp0cnVlLFwiZmlyc3ROYW1lXCI6XCJ7dW5hbWV9XCIsXCJsYXN0TmFtZVwiOlwie3VuYW1lfVwiLFwiY291bnRyeVwiOlwiQlJcIixcImN1cnJlbmN5XCI6XCJCUkxcIixcInNlc3Npb25cIjp7XCJpZFwiOlwie3NpZH1cIixcImlwXCI6XCJ7dWxhc3RpcH1cIn19LFwiY29uZmlnXCI6e1wiY2hhbm5lbFwiOntcIndyYXBwZWRcIjpmYWxzZSxcIm1vYmlsZVwiOmZhbHNlfSxcImdhbWVcIjp7XCJpbnRlcmZhY2VcIjpcInZpZXcyXCIsXCJ0YWJsZVwiOntcImlkXCI6XCJ7Z3RhZ31cIn19fX0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1widGNnYW1ldXJsXCI6e1wia2V5XCI6XCJlbnRyeVwifX0iLCJnYV9yYXRlIjo2MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }
}
