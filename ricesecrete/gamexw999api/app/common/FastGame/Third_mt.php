<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_mt extends Third
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
        unset($da['s'],$da['third'],$da['action_method'],$da['trace_id']);
        ksort($da);
        $trace_id = $config['trdcfg']['tc_api_signkey'];
        foreach ($da as $v) {
            $trace_id .= $v;
        }
        $trace_id = md5($trace_id);
        return compact('trace_id');
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
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['Token'] = Utils::uToken($kvs['uid'].$config['trdcfg']['tc_id']);  //生成token
        }
        if($kvs['runfunc'] == 'GETBETDETAIL') {
            $kvs['OrderID'] = $kvs['gbsn'];
        }
        $kvs['time'] = time();
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMzLCJ0Y19uYW1lIjoiTVQiLCJ0Y19pbnRpbWUiOiIyMDIzLTEyLTA1IDE3OjU4OjA3IiwidGNfY29kZSI6Ik1UIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkubXRzb2Z0Ny5jb21cL3YyIiwidGNfYXBpX3ByaWtleSI6IkMrMFcrQ1BhM045MD0iLCJ0Y19hcGlfc2lnbmtleSI6IndkWkl6ZXYiLCJ0Y190YWciOiJicmxtZXJjaGFudDAxIiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoicHQiLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDEyMFwvYTQ2OGM5N2ZmMjNmZmUwMjMxNDYwODFmYmIyOTIxZjAuanBnIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMDAiLCJ0Y19tYXhfYmV0IjoiMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjQwMTIwXC9iNzVkMTYxMTM3NTZmNjc2NmNlMjRhNGQ4OTc4M2NkZC5qcGciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjoiIiwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo3NywiZ2FfdGNpZCI6MzMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6Ilwvb3BcL3Nsb3RcL2dhbWVcL3VybCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImtleVwiOlwie3RjYXBpa2V5fVwiLFwiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcIk9wc1wiOlwie3RjdGFnfVwiLFwiR2FtZVR5cGVcIjpcIntndGFnfVwiLFwiVG9rZW5cIjpcIntUb2tlbn1cIixcIk9waWRcIjpcInt1bmFtZX1cIixcInRpbWVcIjpcInt0aW1lfVwiLFwidHJhY2VfaWRcIjpcInt0cmFjZV9pZH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjoxfSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZGF0YS51cmxcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }

}