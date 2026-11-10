<?php


namespace app\common\FastGame;


/**
 * 平台导出代码
 * eyJjb25maWciOnsidGNfaWQiOjM3LCJ0Y19uYW1lIjoiXHU5OGRlXHU1ZjY5QVBJXHU1MzU1XHU5NGIxXHU1MzA1XHU2YTIxXHU1ZjBmIiwidGNfaW50aW1lIjoiMjAyMi0xMC0xMyAyMjoxNTo1MCIsInRjX2NvZGUiOiJKU0xHQU1FUyIsInRjX3NldF9jb2RlIjpudWxsLCJ0Y19hcGlfdXJscHJlIjoiaHR0cDpcL1wvYXBpLnRoaXJkLnRlc3QiLCJ0Y19hcGlfcHJpa2V5IjoiZmE4MjBjYzFhZDM5YTRlOTkyODNlOWZhNTU1MDM1ZWMiLCJ0Y19hcGlfc2lnbmtleSI6IiIsInRjX3RhZyI6InRlc3QwMDEiLCJ0Y19jdXJyZW5jeSI6IkJSTCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJ7XCJDTlwiOlwiemhcIixcIkVOXCI6XCJlblwiLFwiQlJcIjpcInB0XCJ9IiwidGNfc3RhdHVzIjoxLCJzb3J0IjoxMDAwLCJpY29uIjoiaHR0cHM6XC9cL2QxMWZibGhxNDE3NTduLmNsb3VkZnJvbnQubmV0XC9zdG9yYWdlXC9pbWdcLzIwMjQwNzI1XC9lM2U1ODIyOTVjNzc1NzA4MzA4YzA2NTA0YWY0Zjg2OC5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4xMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvZDExZmJsaHE0MTc1N24uY2xvdWRmcm9udC5uZXRcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MjVcL2MzNzIzY2E0MzM0MmZkZmNhMDYzMzg1N2UwZWQ2YzVmLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo5NiwiZ2FfdGNpZCI6MzcsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVERFVEFJTCIsImdhX3JvdXRlIjoiXC90cmFuc2FjdGlvblwvZGV0YWlsIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiYmV0SWRcIjpcIntnYnNufVwiLFwibWVyY2hhbnRcIjpcInt0Y3RhZ31cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOlwiMVwifSxcInRjZ2FtZWRldGFpbHVybFwiOntcImtleVwiOlwiZGF0YVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjgzLCJnYV90Y2lkIjozNywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9nYW1lXC9sb2dpbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJnYW1lQ29kZVwiOlwie2d0YWd9XCIsXCJjdXJyZW5jeVwiOlwie3RjY3VycmVuY3l9XCIsXCJtZXJjaGFudFwiOlwie3RjdGFnfVwiLFwibGFuZ1wiOlwie2xhbmd9XCIsXCJ0aW1lc3RhbXBcIjpcInt0aW1lc3RhbXB9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjoxfSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZGF0YS51cmxcIn19IiwiZ2FfcmF0ZSI6MjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==
 */
use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Meta;

class Third_jslgames extends Third
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
