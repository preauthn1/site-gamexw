<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_eg extends Third
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
        return [];
    }

    //使用 HMAC-SHA256 生成加密哈希
    function hmac_sha256($key, $data){
        return hash_hmac('sha256', $data, $key);
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
        $agent = $agent_code = $config['trdcfg']['tc_tag'];
        $token = $config['trdcfg']['tc_api_prikey'];
        $currency = $config['trdcfg']['tc_currency'];
        $kvs['agent'] = $agent;

        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {


            $kvs['uname'] = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名

            $kvs['gtag'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 游戏代码
            $kvs['token'] = $token;
            $message = 'Token='.$token.'&Username='.$kvs['uname'].'&GameID='.$kvs['gtag'].'&AgentName='.$agent;
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {


            $kvs['uname'] = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $kvs['currency'] = $currency;
            $message='{"Username":"'.$kvs['uname'].'","Currency":"'.$currency.'","AgentName":"'.$agent.'"}';

        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {

            $kvs['uname'] = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            if($kvs['runfunc'] == 'WITHDRAW'){
                $kvs['amount'] = -$kvs['amount'];
            }
            $message ='{"Amount":"'.$kvs['amount'].'","AgentName":"'.$agent.'","Username":"'.$kvs['uname'].'","ReferenceCode":"testReferenceCode","TakeAll":false}';


        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {

            $kvs['uname'] = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $message = 'AgentName='.$agent.'&Username='.$kvs['uname'];
        }
        if (in_array($kvs['runfunc'], ['GETBETLIST'])) {

            $message = 'StartTime='.$kvs['start_time'].'&EndTime='.$kvs['end_time'].'&AgentName='.$agent.'&Status=1&Page='.$kvs['page'].'&PageSize='.$kvs['page_size'];
        }
        $hash = self::hmac_sha256($token, $message);
        $kvs['hash'] = $hash;

    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'REGISTER') {

            $kds['uid'] = $uid;
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQ3LCJ0Y19uYW1lIjoiRUdcdTk2ZmJcdTViNTAiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE5OjMzOjI5IiwidGNfY29kZSI6IkVHIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3R4LmFwaS5lZ3Nsb3QuY2NcL2FwaVwvdjFcL3h3IiwidGNfYXBpX3ByaWtleSI6Im1EUWlwMzNXRTM0WndjQXo1akNWWDZZIiwidGNfYXBpX3NpZ25rZXkiOiJ4dyIsInRjX3RhZyI6Inh3X3R3ZCIsInRjX2N1cnJlbmN5IjoiVFdEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InR3IiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNzA5XC82YjU5MDg5ODhlMjBiZmM1YjkzOGYzY2E4OGEzMjU3Ny5qcGciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMTAuMDAwMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAwMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDcwOVwvMGJiMGYwMzdiODhlMjNhNjM5Y2RhOTQ1ZWMzNDg3YmEuanBnIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjoxLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjE5MCwiZ2FfdGNpZCI6NDcsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQiLCJnYV9yb3V0ZSI6IlwvdHJhbnNmZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJIYXNoPXtoYXNofSIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJBbW91bnRcIjpcInthbW91bnR9XCIsXCJBZ2VudE5hbWVcIjpcInthZ2VudH1cIixcIlVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJSZWZlcmVuY2VDb2RlXCI6IFwidGVzdFJlZmVyZW5jZUNvZGVcIixcIlRha2VBbGxcIjogZmFsc2V9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiQW1vdW50XCIsXCJ2YWx1ZVwiOlwiVlwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiQW1vdW50XCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTkxLCJnYV90Y2lkIjo0NywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9wbGF5ZXJzXC9zdGF0dXMiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6Ikhhc2g9e2hhc2h9JkFnZW50TmFtZT17YWdlbnR9JlVzZXJuYW1lPXt1bmFtZX0iLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoiIiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiQmFsYW5jZVwiLFwidmFsdWVcIjpcIlZcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcIkJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxOTIsImdhX3RjaWQiOjQ3LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL2hpc3RvcnlcL3RyYW5zYWN0aW9uIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJIYXNoPXtoYXNofSZTdGFydFRpbWU9e3N0YXJ0X3RpbWV9JkVuZFRpbWU9e2VuZF90aW1lfSZBZ2VudE5hbWU9e2FnZW50fSZTdGF0dXM9MSZQYWdlPTEmUGFnZVNpemU9MjAwMCIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIkRhdGFcIixcInZhbHVlXCI6XCJVXCJ9LFwicl9saXN0XCI6e1wia2V5XCI6XCJEYXRhXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU1NTg4MDQsXCJjdXJ0aW1lXCI6MCxcImVuZFwiOjE3MjU1NTg4MDQsXCJzdGFydF90aW1lXCI6MTcyNTU1ODQ0NDAwMCxcImVuZF90aW1lXCI6MTcyNTU1ODc0NDAwMCxcInBhZ2VcIjowLFwicGFnZV9zaXplXCI6MjAwMH19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTkzLCJnYV90Y2lkIjo0NywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9sb2dpbiIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiSGFzaD17aGFzaH0mVG9rZW49e3Rva2VufSZVc2VybmFtZT17dW5hbWV9JkdhbWVJRD17Z3RhZ30mQWdlbnROYW1lPXthZ2VudH0iLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoiIiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiVVJMXCIsXCJ2YWx1ZVwiOlwiVlwifSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiVVJMXCJ9fSIsImdhX3JhdGUiOjEwLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE5NCwiZ2FfdGNpZCI6NDcsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL3BsYXllcnMiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJIYXNoPXtoYXNofSIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJVc2VybmFtZVwiOlwie3VuYW1lfVwiLFwiQ3VycmVuY3lcIjpcIntjdXJyZW5jeX1cIixcIkFnZW50TmFtZVwiOlwie2FnZW50fVwifSIsImdhX3R5cGUiOiJyZWciLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJ1aWRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxOTUsImdhX3RjaWQiOjQ3LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiXC90cmFuc2ZlciIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6Ikhhc2g9e2hhc2h9IiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcIkFtb3VudFwiOlwie2Ftb3VudH1cIixcIkFnZW50TmFtZVwiOlwie2FnZW50fVwiLFwiVXNlcm5hbWVcIjpcInt1bmFtZX1cIixcIlJlZmVyZW5jZUNvZGVcIjogXCJ0ZXN0UmVmZXJlbmNlQ29kZVwiLFwiVGFrZUFsbFwiOiBmYWxzZX0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJCZWZvcmVCYWxhbmNlXCIsXCJ2YWx1ZVwiOlwiVlwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiQmVmb3JlQmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
