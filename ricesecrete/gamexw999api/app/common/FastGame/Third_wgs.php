<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_wgs extends Third
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
        $da = $data;
        unset($da['sign']);
        $string = urldecode(http_build_query(myArrayFilter($da)));
        $string = str_replace("+", " ", $string).'&md5key='. $config['trdcfg']['tc_api_signkey'];
        $sign = md5($string);
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
        if($kvs['runfunc'] == 'DEPOSIT') {
            $tcbalance = floatval(Meta::initGetField('tcbalance',$config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']));
            $o_amount = intval(Third::transExchangerate($kvs['o_amount'],1, 0));
            $kvs['apoints'] = round($tcbalance + $o_amount, 4);
        }
        if($kvs['runfunc'] == 'WITHDRAW') {
            $tcbalance = floatval(Meta::initGetField('tcbalance',$config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']));
            $o_amount = intval(Third::transExchangerate($kvs['o_amount'],0, 0));
            $kvs['apoints'] = round($tcbalance - $o_amount, 4);
        }

        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            $kds['tcgameurl'] = 'https:'.$datas['url'];
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjM4LCJ0Y19uYW1lIjoiV0dcdTlhZDRcdTgwYjIiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE4OjMxOjUxIiwidGNfY29kZSI6IldHUyIsInRjX3NldF9jb2RlIjoiIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkud25zcDE2OC5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiWFciLCJ0Y19hcGlfc2lnbmtleSI6ImNmMWM3NTUzYjQzZWM2Y2I5ZWRhZGY5YmI0ODJkYzdjIiwidGNfdGFnIjoiQVVSNTYyOCIsInRjX2N1cnJlbmN5IjoiTlQiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiemgtdHciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcL2RmMjY0ZmUyOWY2MzM5YThjMzA4NzM3ZjRiYTYyYjliLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC9hNGMwOGNlZDYyYjZkNWNiZmU5MDhiMmFmNTQ3OTJiOC5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTM5LCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC9zcGJldF9BcGlcL1RyYW5zZmVySW4iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJwcmVmaXhcIjpcInt0Y2FwaWtleX1cIixcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJicG9pbnRzXCI6XCJ7bnx0Y2JhbGFuY2V9XCIsXCJwb2ludHNcIjpcIntpfG9fYW1vdW50fVwiLFwiYXBvaW50c1wiOlwie258YXBvaW50c31cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTM4LCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9zcGJldF9BcGlcL1BvaW50VXNlciIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInByZWZpeFwiOlwie3RjYXBpa2V5fVwiLFwidXNlcm5hbWVcIjpcInt1bmFtZX1cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwicG9pbnRzXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxNDEsImdhX3RjaWQiOjM4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL3NwYmV0X0FwaVwvQnV5TGlzdEdldCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInByZWZpeFwiOlwie3RjYXBpa2V5fVwiLFwidHlwZVwiOjMsXCJzZGF0ZVwiOlwie3NkYXRlfVwiLFwiZWRhdGVcIjpcIntlZGF0ZX1cIixcInByaV90eXBlXCI6XCIxXCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInJfbGlzdFwiOntcImtleVwiOlwiZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU1NTk2OTksXCJjdXJ0aW1lXCI6MCxcInNkYXRlXCI6XCIyMDI0LTA5LTA2IDAyOjAyOjE5XCIsXCJlZGF0ZVwiOlwiMjAyNC0wOS0wNiAwMjowNzoxOVwiLFwiZW5kXCI6MTcyNTU1OTY5OX19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTM3LCJnYV90Y2lkIjozOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9zcGJldF9BcGlcL0ZvcndhcmRHYW1lIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wicHJlZml4XCI6XCJ7dGNhcGlrZXl9XCIsXCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwidXJsXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxMzYsImdhX3RjaWQiOjM4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJSRUdJU1RFUiIsImdhX3JvdXRlIjoiXC9zcGJldF9BcGlcL0NyZWF0ZVVzZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJwcmVmaXhcIjpcInt0Y2FwaWtleX1cIixcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJuaWNrbmFtZVwiOlwie3VuYW1lfVwiLFwidXB1c2VybmFtZVwiOlwie3RjdGFnfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjdXNlcmtleVwiOntcImtleVwiOlwidXNlcm5hbWVcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNDAsImdhX3RjaWQiOjM4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiXC9zcGJldF9BcGlcL1RyYW5zZmVyT3V0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wicHJlZml4XCI6XCJ7dGNhcGlrZXl9XCIsXCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwiYnBvaW50c1wiOlwie258dGNiYWxhbmNlfVwiLFwicG9pbnRzXCI6XCJ7aXxvX2Ftb3VudH1cIixcImFwb2ludHNcIjpcIntufGFwb2ludHN9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiT0tcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6bnVsbH1dfQ==';
    }

}