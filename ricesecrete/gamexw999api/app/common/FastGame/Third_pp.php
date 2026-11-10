<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;

class Third_pp extends Third
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
        if ($this->signDatas && $this->signDatas['runfunc']=="GIVEFREEBETS"){
            $params=parse_url($this->signDatas['get'])['query'];
            $otherkvs=$this->signDatas['otherkvs'];
            $params=Meta::parseMateData(Meta::cusParseMateData($this->signDatas['otherkvs'],$params,null),$otherkvs['tcid'],$otherkvs['uid'],$otherkvs['gid']);
            $newparams=[];
            parse_str($params,$newparams);
            unset($newparams['hash']);
            ksort($newparams);
            $hash = md5(urldecode(http_build_query(myArrayFilter($newparams))) . $config['trdcfg']['tc_api_signkey']);
            //print_r([myArrayFilter($newparams), http_build_query(myArrayFilter($newparams)), urldecode(http_build_query(myArrayFilter($newparams)))]);
            return compact('hash');
        }else {
            $da = $data;
            unset($da['hash'], $da['fg_posttype']);
            ksort($da);
            $hash = md5(urldecode(http_build_query(myArrayFilter($da))) . $config['trdcfg']['tc_api_signkey']);
            return compact('hash');
        }
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
        unset($data['action_method'],$data['third'],$data['s']);
        $sign = $this->MakeSign($config, $data);
        if($sign['hash'] != $data['hash']){
            Utils::L('pp', ['CheckSign','签名错误', ['sign'=>$sign['hash'],'third_sign'=>$data['hash']]]);
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

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        if( in_array($kvs['runfunc'], ['GETUSERURL', 'GETTRYURL']) ){
            $kvs['platform'] = $kvs['platform'] == 1 ? 'WEB' : 'MOBILE';
            $web_url = getAppUrl();
            $kvs['rcCloseUrl'] = $web_url;
            $kvs['cashierUrl'] = $web_url.'/#/transactions';
        }
        if($kvs['runfunc'] == 'GETBETDETAIL'){
            $kvs['roundId'] = explode('_',$kvs['gbsn'])[0];
            $kvs['gameId'] = Meta::initGetField('gtag',$kvs['betInfo']['gb_tcid'],$kvs['betInfo']['gb_gid'],$kvs['uid']); //游戏标识
        }

        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['gameURL'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIwLCJ0Y19uYW1lIjoiUFAgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAyLTE0IDE3OjU1OjE2IiwidGNfY29kZSI6IlBQIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGktYnIxLnByYWdtYXRpY3BsYXkubmV0XC9JbnRlZ3JhdGlvblNlcnZpY2VcL3YzXC9odHRwXC9DYXNpbm9HYW1lQVBJIiwidGNfYXBpX3ByaWtleSI6IlByYWdtYXRpY1BsYXkiLCJ0Y19hcGlfc2lnbmtleSI6Ijg3QjQwZEMxRTY0NTRlRTAiLCJ0Y190YWciOiJ6ZjM3OWRfdDY2IiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoicHQiLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjg4ODgsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvYTUzOWYyYTBkZmUwYzE4MWVmMGFhMTZjYWZiZDBkMmIucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjEsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MTFcLzNjOWE3YzQxNDMzMWM1NjU0MjZkNjMxMTc4ZjM2OTg5LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NTIsImdhX3RjaWQiOjIwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2dhbWVcL3VybCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6IntcInNlY3VyZUxvZ2luXCI6XCJ7dGN0YWd9XCIsXCJzeW1ib2xcIjpcIntndGFnfVwiLFwibGFuZ3VhZ2VcIjpcInt0Y2xhbmdtYXB9XCIsXCJ0b2tlblwiOlwie3VuYW1lfVwiLFwicGxhdGZvcm1cIjpcIntwbGF0Zm9ybX1cIixcImxvYmJ5VXJsXCI6XCJ7cmNDbG9zZVVybH1cIixcImNhc2hpZXJVcmxcIjpcIntjYXNoaWVyVXJsfVwiLFwiaGFzaFwiOlwie2hhc2h9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yXCIsXCJ2YWx1ZVwiOjB9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJnYW1lVVJMXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }

}
