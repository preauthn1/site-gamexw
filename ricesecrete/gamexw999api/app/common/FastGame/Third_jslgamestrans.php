<?php


namespace app\common\FastGame;
// JSL集成系统转账模式对接

/**
 * 平台导出代码
 * eyJjb25maWciOnsidGNfaWQiOjM4LCJ0Y19uYW1lIjoiXHU5OGRlXHU1ZjY5QVBJIiwidGNfaW50aW1lIjoiMjAyNC0wNy0xNCAxOTozMTowMiIsInRjX2NvZGUiOiJKU0xHQU1FU1RSQU5TIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cDpcL1wvYXBpLnRoaXJkLnRlc3QiLCJ0Y19hcGlfcHJpa2V5IjoiNmRhMWU5MjMwM2E2YjZmMWEyM2JkOWY5ZDMxYWU5Y2YiLCJ0Y19hcGlfc2lnbmtleSI6IiIsInRjX3RhZyI6InNwZHRlc3QiLCJ0Y19jdXJyZW5jeSI6ImJybCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6NjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoie1wiQ05cIjpcInpoXCIsXCJFTlwiOlwiZW5cIixcIkJSXCI6XCJwdFwifSIsInRjX3N0YXR1cyI6MSwic29ydCI6MSwiaWNvbiI6Imh0dHBzOlwvXC9kMTFmYmxocTQxNzU3bi5jbG91ZGZyb250Lm5ldFwvc3RvcmFnZVwvaW1nXC8yMDI0MDcyNVwvZTNlNTgyMjk1Yzc3NTcwODMwOGMwNjUwNGFmNGY4NjgucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMDAiLCJ0Y19tYXhfYmV0IjoiMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9kMTFmYmxocTQxNzU3bi5jbG91ZGZyb250Lm5ldFwvc3RvcmFnZVwvaW1nXC8yMDI0MDcyNVwvYzM3MjNjYTQzMzQyZmRmY2EwNjMzODU3ZTBlZDZjNWYucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjkyLCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC9jYXNoXC9kZXBvc2l0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1widXNlcm5hbWVcIjogXCJ7dW5hbWV9XCIsXCJjdXJyZW5jeVwiOiBcInt0Y2N1cnJlbmN5fVwiLFwidGltZXN0YW1wXCI6IFwie3RpbWVzdGFtcH1cIixcIm1lcmNoYW50XCI6IFwie3RjdGFnfVwiLFwic2lnblwiOiBcIntzaWdufVwiLFwidHJhbnNmZXJBbW91bnRcIjogXCJ7b19hbW91bnR9XCIsXCJyZWZlcmVuY2VJZFwiOlwie29fdHJhbnNpZH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmNvZGVcIixcInZhbHVlXCI6XCIxXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo5NCwiZ2FfdGNpZCI6MzgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvY2FzaFwvYmFsYW5jZSIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1widXNlcm5hbWVcIjogXCJ7dW5hbWV9XCIsXCJjdXJyZW5jeVwiOiBcInt0Y2N1cnJlbmN5fVwiLFwidGltZXN0YW1wXCI6IFwie3RpbWVzdGFtcH1cIixcIm1lcmNoYW50XCI6IFwie3RjdGFnfVwiLFwic2lnblwiOiBcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCIxXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo5NSwiZ2FfdGNpZCI6MzgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVERFVEFJTCIsImdhX3JvdXRlIjoiXC90cmFuc2FjdGlvblwvZGV0YWlsIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJiZXRJZD17Z2Jzbn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOlwiMVwifSxcInRjZ2FtZWRldGFpbHVybFwiOntcImtleVwiOlwiZGF0YVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjg0LCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC90cmFuc2FjdGlvblwvbGlzdCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wicGFnZVwiOiBcIntwYWdlfVwiLFwic2l6ZVwiOiBcIntzaXplfVwiLFwidGltZXN0YW1wXCI6IFwie3RpbWVzdGFtcH1cIixcIm1lcmNoYW50XCI6IFwie3RjdGFnfVwiLFwic2lnblwiOiBcIntzaWdufVwiLFwic3RhcnRfdGltZVwiOiBcIntzdGFydHRpbWV9XCIsXCJlbmRfdGltZVwiOiBcIntlbmR0aW1lfVwifSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjoxfSxcInJfbGlzdFwiOntcImtleVwiOlwiZGF0YVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzIxODE4Njg5LFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzIxODE4Njg5LFwic3RhcnR0aW1lXCI6MCxcImVuZHRpbWVcIjoxNzIxODE4Mzg5LFwicGFnZVwiOjAsXCJzaXplXCI6MjAwMH19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6ODUsImdhX3RjaWQiOjM4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2dhbWVcL2xvZ2luIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1widXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImdhbWVDb2RlXCI6XCJ7Z3RhZ31cIixcImN1cnJlbmN5XCI6XCJ7dGNjdXJyZW5jeX1cIixcIm1lcmNoYW50XCI6XCJ7dGN0YWd9XCIsXCJsYW5nXCI6XCJ7bGFuZ31cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjF9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLnVybFwifX0iLCJnYV9yYXRlIjozNjAwLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjkzLCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvY2FzaFwvd2l0aGRyYXciLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJ1c2VybmFtZVwiOiBcInt1bmFtZX1cIixcImN1cnJlbmN5XCI6IFwie3RjY3VycmVuY3l9XCIsXCJ0aW1lc3RhbXBcIjogXCJ7dGltZXN0YW1wfVwiLFwibWVyY2hhbnRcIjogXCJ7dGN0YWd9XCIsXCJzaWduXCI6IFwie3NpZ259XCIsXCJ0cmFuc2ZlckFtb3VudFwiOiBcIntvX2Ftb3VudH1cIixcInJlZmVyZW5jZUlkXCI6XCJ7b190cmFuc2lkfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCIxXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19
 */
use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Meta;

class Third_jslgamestrans extends Third
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
        unset($data['sign']);
        ksort($data);
        $string = urldecode(http_build_query($data));
//        $string = Http::ToUrlParams($data,false,['sign']);
        //签名步骤二：在string后加入KEY
        $string = $string .'&key='. $config['trdcfg']['tc_api_prikey'];
        //签名步骤三：MD5加密
        $string = md5($string);
        $data['sign']=$string;

        return $string;
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
        unset($data['sign'], $data['s'], $data['action_method'], $data['third']);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = http_build_query($data);
//        $string = Http::ToUrlParams($data,false,['sign', 'action_method', 's', 'third']);
        //签名步骤二：在string后加入KEY
        $string = $string .'&key='.  $config['trdcfg']['tc_api_prikey'];
        //签名步骤三：MD5加密
        return md5($string);
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

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETBETDETAIL') {
            $kds['tcgamedetailurl'] = $kds['tcgamedetailurl']['betDetailOrigin'] ?? $kds['tcgamedetailurl']['betDetailUrl'] ?? $kds['tcgamedetailurl']['betDetailHtml'] ?? '';
        }
    }
}
