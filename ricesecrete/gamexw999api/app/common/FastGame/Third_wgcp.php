<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_wgcp extends Third
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
        if(isset($data['sign']))$data['sign']='{sign}';

        $da = $data;
        unset($da['sign']);
        $string = http_build_query(myArrayFilter($da));
        $string = $string.'&md5key='. $config['trdcfg']['tc_api_signkey'];
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

        //所有用户名前面加
        if($kvs['runfunc'] == 'GETBETLIST'){

        }else if($kvs['runfunc'] == 'REGISTER'){


            $kvs['username'] = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
        }else{

            $firstTwoChars = substr($config['trdcfg']['tc_api_prikey'], 0, 2);

            $kvs['username'] = $firstTwoChars.'_'.Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
        }

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

            $kds['tcgameurl'] = 'https://'.$datas['url'];
        }
        if($func == 'REGISTER'){
            $kds['username'] = $uid;
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQ4LCJ0Y19uYW1lIjoiV0dcdTVmNjlcdTc5NjgiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE4OjMxOjUxIiwidGNfY29kZSI6IldHQ1AiLCJ0Y19zZXRfY29kZSI6IiIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvbGF0LnBpbmphNzc3LmNvbSIsInRjX2FwaV9wcmlrZXkiOiJqbTAwMTAxIiwidGNfYXBpX3NpZ25rZXkiOiJocmpZdmhUdTZOZjN6UjR3UkxtWSIsInRjX3RhZyI6ImptX3h3MDAxIiwidGNfY3VycmVuY3kiOiJOVCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ0dyIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvZGYyNjRmZTI5ZjYzMzlhOGMzMDg3MzdmNGJhNjJiOWIucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMDAwMCIsInRjX21heF9iZXQiOiIwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcL2E0YzA4Y2VkNjJiNmQ1Y2JmZTkwOGIyYWY1NDc5MmI4LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxOTYsImdhX3RjaWQiOjQ4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2xvdF9hcGlcL1RyYW5zZmVySW4iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJhcGlfaWRcIjpcInt0Y2FwaWtleX1cIixcInVzZXJuYW1lXCI6XCJ7dXNlcm5hbWV9XCIsXCJicG9pbnRzXCI6XCJ7bnx0Y2JhbGFuY2V9XCIsXCJwb2ludHNcIjpcIntpfG9fYW1vdW50fVwiLFwiYXBvaW50c1wiOlwie258YXBvaW50c31cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwicG9pbnRzXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTk3LCJnYV90Y2lkIjo0OCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9sb3RfYXBpXC9Qb2ludFVzZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJhcGlfaWRcIjpcInt0Y2FwaWtleX1cIixcInVzZXJuYW1lXCI6XCJ7dXNlcm5hbWV9XCIsXCJzaWduXCI6XCJ7c2lnbn1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiT0tcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcInBvaW50c1wifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE5OCwiZ2FfdGNpZCI6NDgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvbG90X2FwaVwvQnV5TGlzdEdldEFwaSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcImFwaV9pZFwiOlwie3RjYXBpa2V5fVwiLFwiZGF0ZVwiOlwie2RhdGV9XCIsXCJwcm94eW5hbWVcIjpcInt0Y3RhZ31cIixcInR5cGVcIjo0LFwicHJpX3R5cGVcIjpcIjFcIixcInBhZ2VcIjpcIntwYWdlfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiT0tcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGFcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTU1ODY4MixcImN1cnRpbWVcIjowLFwiZW5kXCI6MTcyNTU1ODY4MixcImRhdGVcIjpcIjIwMjQtMDktMDZcIixcInBhZ2VcIjowLFwic2l6ZVwiOjEwMDAwfX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxOTksImdhX3RjaWQiOjQ4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2xvdF9hcGlcL0ZvcndhcmRHYW1lIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiYXBpX2lkXCI6XCJ7dGNhcGlrZXl9XCIsXCJ1c2VybmFtZVwiOlwie3VzZXJuYW1lfVwiLFwibG9jYWxlXCI6XCJ7dGNsYW5nbWFwfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JfY29kZVwiLFwidmFsdWVcIjpcIk9LXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJ1cmxcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoyMDAsImdhX3RjaWQiOjQ4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJSRUdJU1RFUiIsImdhX3JvdXRlIjoiXC9sb3RfYXBpXC9DcmVhdGVVc2VyQXBpIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiYXBpX2lkXCI6XCJ7dGNhcGlrZXl9XCIsXCJ1c2VybmFtZVwiOlwie3VzZXJuYW1lfVwiLFwibmlja25hbWVcIjpcInt1bmFtZX1cIixcInByb3h5bmFtZVwiOlwie3RjdGFnfVwiLFwic2lnblwiOlwie3NpZ259XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPSyxDTElFTlRfRVhJU1RcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcInVzZXJuYW1lXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjAxLCJnYV90Y2lkIjo0OCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvbG90X2FwaVwvVHJhbnNmZXJPdXQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJhcGlfaWRcIjpcInt0Y2FwaWtleX1cIixcInVzZXJuYW1lXCI6XCJ7dXNlcm5hbWV9XCIsXCJicG9pbnRzXCI6XCJ7bnx0Y2JhbGFuY2V9XCIsXCJwb2ludHNcIjpcIntpfG9fYW1vdW50fVwiLFwiYXBvaW50c1wiOlwie258YXBvaW50c31cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCJPS1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwicG9pbnRzXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }

}