<?php


namespace app\common\FastGame;

use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_sabasw extends Third
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
        return [];
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

        $kvs['vendor_id'] = $config['trdcfg']['tc_api_signkey'];
        //token字段放配置参数，比如：盘口_钱包编号,token[0]为盘口，token[1]为钱包编号
        $token = explode('_',$config['trdcfg']['tc_token']);
        $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
        $kvs['vendor_member_id'] = $config['trdcfg']['tc_tag'].'_'.$uname;

        if($kvs['runfunc'] == 'REGISTER'){

            $kvs['operatorId'] = $config['trdcfg']['tc_tag'];
            $kvs['username'] = $config['trdcfg']['tc_tag'].'_'.$uname;
            $kvs['oddstype'] = $token[0];
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
            $kvs['maxtransfer'] = $config['trdcfg']['tc_max_bet'];
            $kvs['mintransfer'] = $config['trdcfg']['tc_min_bet'];

        }

    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        //需要的时候开启
        //$config = $this->apicfgs;
        if($func == 'GETUSERURL'){
            $kds['tcgameurl'] = $datas['Data'].'&OType=1';
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjk3LCJ0Y19uYW1lIjoiXHU2Yzk5XHU1ZGY0XHU0ZjUzXHU4MGIyIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxODozMTo1MSIsInRjX2NvZGUiOiJTQUJBU1ciLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2c2bTVhcGkuYnc2Njg4LmNvbVwvYXBpIiwidGNfYXBpX3ByaWtleSI6IlJTM1Q2MTYiLCJ0Y19hcGlfc2lnbmtleSI6Ijdya3JlcDVxMzciLCJ0Y190YWciOiJSUzNUNjE2IiwidGNfY3VycmVuY3kiOiI2MSIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IjNfMSIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJlbiIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuZmcuZ2FtZVwvc3RvcmFnZVwvaW1nXC8yMDI0MDcyNVwvZjI0MDg5YTIzZWE0YWI4NTcwNjY5MDlhZmJmNmJlMTIuc3ZnIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMDAwMCIsInRjX21heF9iZXQiOiI5OTk5OTk5OTkuMDAwMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcuZmcuZ2FtZVwvc3RvcmFnZVwvaW1nXC8yMDI0MDcyNVwvNjUyNDc4ZTE4YjBmNmI3YjJkNmY1ZjFkN2E4YzhhNGIuc3ZnIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6IiIsImlzX25ld190YWIiOjAsInRjX2Rpc2FibGVfYWdlbnRfaWRzIjoiMCJ9LCJhcGlzIjpbeyJnYV9pZCI6MjE0LCJnYV90Y2lkIjo5NywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9HZXRTYWJhVXJsIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRcIjpcInt2ZW5kb3JfbWVtYmVyX2lkfVwiLFwicGxhdGZvcm1cIjpcIntwbGF0Zm9ybX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJEYXRhXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjE1LCJnYV90Y2lkIjo5NywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvQ3JlYXRlTWVtYmVyIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRcIjpcInt2ZW5kb3JfbWVtYmVyX2lkfVwiLFwib3BlcmF0b3JJZFwiOlwie29wZXJhdG9ySWR9XCIsXCJ1c2VybmFtZVwiOlwie3VzZXJuYW1lfVwiLFwib2Rkc3R5cGVcIjpcIntvZGRzdHlwZX1cIixcImN1cnJlbmN5XCI6XCJ7Y3VycmVuY3l9XCIsXCJtYXh0cmFuc2ZlclwiOlwie21heHRyYW5zZmVyfVwiLFwibWludHJhbnNmZXJcIjpcInttaW50cmFuc2Zlcn1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JfY29kZVwiLFwidmFsdWVcIjowfSxcInRjdXNlcmtleVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}