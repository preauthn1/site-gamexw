<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Meta, Third, Utils};


class Third_t9 extends Third
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
        return true;
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
        date_default_timezone_set('UTC');//t9必须时区为UTC+0
        $config = $this->apicfgs;
        $kvs['AgentId'] = $config['trdcfg']['tc_tag'];
        $kvs['RequestTime'] = date("YmdHis");
        $kvs['Sign'] = strtolower(md5($config['trdcfg']['tc_tag'] . "&" . $kvs['RequestTime'] . "&" . $config['trdcfg']['tc_api_signkey']));
        if (in_array($kvs['runfunc'], ['REGISTER', 'GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['password'] = md5($uname);
            $kvs['MiniBetLimit'] = $config['trdcfg']['tc_min_bet'];
            $kvs['MaxBetLimit'] = $config['trdcfg']['tc_max_bet'];
            $kvs['TimeZone'] = $config['trdcfg']['tc_timezone'];
            $lang = $config['trdcfg']['tc_langmap'];
            $kvs['Language'] = $this->_getLanguage($lang);
            $kvs['ForceMode'] = 0;//0 自动,1 H5 ,2 PC
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['money'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            $kvs['MerchantOrderNumber'] = date("YmdHis") . rand(100000, 999999);
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
        }
//        Utils::L('T9_transOtherKvs',$kvs);
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETBALANCE') {
            $kds['tcbalance'] = 0;
            if (isset($datas['Data'][0]['Balance'])) {
                $kds['tcbalance'] = $datas['Data'][0]['Balance'];
            }
        }
//        Utils::L('T9_transOtherKvsData',$kds);
//        Utils::L('T9_transResultData',$datas);
    }

    /**
     * 获取语言类型
     * @param string $lang
     * @return int
     */
    function _getLanguage($lang = '')
    {
        $lang = strtolower($lang);
        $Language = 3;
        switch ($lang) {
            case "tw":
                $Language = 1;
                break;
            case "cn":
                $Language = 2;
                break;
            case "en":
                $Language = 3;
                break;
            case "th":
                $Language = 4;
                break;
            case "vn":
                $Language = 5;
                break;
            case "kr":
                $Language = 6;
                break;
            case "jp":
                $Language = 7;
                break;
        }
        return $Language;
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMyLCJ0Y19uYW1lIjoiVDlcdTc3MWZcdTRlYmEiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTE0IDE5OjUxOjQ3IiwidGNfY29kZSI6IlQ5IiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3RwZ2FtZWFwaS50OWNuODE4Lm9ubGluZSIsInRjX2FwaV9wcmlrZXkiOiIiLCJ0Y19hcGlfc2lnbmtleSI6ImJ3dWZQdkpZaGkyTEtVbG5nZ3lNZWtSRXVmeU5yUmc4IiwidGNfdGFnIjoiMTkwODkiLCJ0Y19jdXJyZW5jeSI6IlRXRCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ0dyIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvMDRkMmU4ZjU2MWQzZTUzNDA2OWNjZmQ5YzQyMjYwNDUucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjEuMDAwMCIsInRjX21heF9iZXQiOiIxMDAwMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC80MmQ1M2E2MDZmMDAyZWExNjlmZTQ3YmNlMjdhZDdiMy5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6ODEsImdhX3RjaWQiOjMyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdHJhbnNmZXJfaW5fdjIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uOyBjaGFyc2V0PXV0Zi04XCJ9IiwiZ2FfcHBhcmFtIjoie1wiQWdlbnRJZFwiOlwie0FnZW50SWR9XCIsXCJSZXF1ZXN0VGltZVwiOlwie1JlcXVlc3RUaW1lfVwiLFwiU2lnblwiOlwie1NpZ259XCIsXCJNZW1iZXJBY2NvdW50XCI6XCJ7dW5hbWV9XCIsXCJBbW91bnRcIjpcInttb25leX1cIixcIk1lcmNoYW50T3JkZXJOdW1iZXJcIjpcIntNZXJjaGFudE9yZGVyTnVtYmVyfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIkVycm9yLkNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcIkRhdGEuRGVwb3NpdEJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo4MiwiZ2FfdGNpZCI6MzIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvYXBpXC9tZW1iZXJfYmFsYW5jZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb247IGNoYXJzZXQ9dXRmLThcIn0iLCJnYV9wcGFyYW0iOiJ7XCJBZ2VudElkXCI6XCJ7QWdlbnRJZH1cIixcIlJlcXVlc3RUaW1lXCI6XCJ7UmVxdWVzdFRpbWV9XCIsXCJTaWduXCI6XCJ7U2lnbn1cIixcIk1lbWJlckFjY291bnRzXCI6W1wie3VuYW1lfVwiXX0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJFcnJvci5Db2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo4MywiZ2FfdGNpZCI6MzIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvYXBpXC9tZW1iZXJfYmV0X3JlY29yZCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb247IGNoYXJzZXQ9dXRmLThcIn0iLCJnYV9wcGFyYW0iOiJ7XCJBZ2VudElkXCI6XCJ7QWdlbnRJZH1cIixcIlJlcXVlc3RUaW1lXCI6XCJ7UmVxdWVzdFRpbWV9XCIsXCJTaWduXCI6XCJ7U2lnbn1cIixcIlNlcmlhbE51bWJlclwiOlwie1NlcmlhbE51bWJlcn1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIkVycm9yLkNvZGVcIixcInZhbHVlXCI6MH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcIkRhdGFcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MCxcImN1cnRpbWVcIjowLFwiU2VyaWFsTnVtYmVyXCI6NjE3NDg0OTEsXCJBZ2VudElkXCI6XCIxOTA4OVwiLFwiUmVxdWVzdFRpbWVcIjpcIjIwMjQwOTA2MDIyNzAyXCIsXCJTaWduXCI6XCI5MGMxNDkyNGFkNzMxOWM0ZjFjODQ2MzUwZDRmMjU2ZFwifX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo4NCwiZ2FfdGNpZCI6MzIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvYXBpXC9sYXVuY2hfZ2FtZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb247IGNoYXJzZXQ9dXRmLThcIn0iLCJnYV9wcGFyYW0iOiJ7XCJBZ2VudElkXCI6XCJ7QWdlbnRJZH1cIixcIlJlcXVlc3RUaW1lXCI6XCJ7UmVxdWVzdFRpbWV9XCIsXCJTaWduXCI6XCJ7U2lnbn1cIixcIk1lbWJlckFjY291bnRcIjpcInt1bmFtZX1cIixcIk1lbWJlclBhc3N3b3JkXCI6XCJ7cGFzc3dvcmR9XCIsXCJMYW5ndWFnZVwiOlwie0xhbmd1YWdlfVwiLFwiRm9yY2VNb2RlXCI6XCJ7Rm9yY2VNb2RlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIkVycm9yLkNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcIkRhdGEuR2FtZVVybFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjg1LCJnYV90Y2lkIjozMiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvYXBpXC9jcmVhdGVfYWNjb3VudCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb247IGNoYXJzZXQ9dXRmLThcIn0iLCJnYV9wcGFyYW0iOiJ7XCJBZ2VudElkXCI6XCJ7QWdlbnRJZH1cIixcIlJlcXVlc3RUaW1lXCI6XCJ7UmVxdWVzdFRpbWV9XCIsXCJTaWduXCI6XCJ7U2lnbn1cIixcIk1lbWJlckFjY291bnRcIjpcInt1bmFtZX1cIixcIk1lbWJlclBhc3N3b3JkXCI6XCJ7cGFzc3dvcmR9XCIsXCJOaWNrTmFtZVwiOlwie3VuYW1lfVwiLFwiTWluaUJldExpbWl0XCI6XCJ7TWluaUJldExpbWl0fVwiLFwiTWF4QmV0TGltaXRcIjpcIntNYXhCZXRMaW1pdH1cIixcIlRpbWVab25lXCI6XCJ7VGltZVpvbmV9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIkVycm9yLkNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjg2LCJnYV90Y2lkIjozMiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvYXBpXC90cmFuc2Zlcl9vdXRfdjIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uOyBjaGFyc2V0PXV0Zi04XCJ9IiwiZ2FfcHBhcmFtIjoie1wiQWdlbnRJZFwiOlwie0FnZW50SWR9XCIsXCJSZXF1ZXN0VGltZVwiOlwie1JlcXVlc3RUaW1lfVwiLFwiU2lnblwiOlwie1NpZ259XCIsXCJNZW1iZXJBY2NvdW50XCI6XCJ7dW5hbWV9XCIsXCJUcmFuc2ZlckFsbE91dFwiOnRydWUsXCJNZXJjaGFudE9yZGVyTnVtYmVyXCI6XCJ7TWVyY2hhbnRPcmRlck51bWJlcn1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJFcnJvci5Db2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJEYXRhLldpdGhkcmF3QmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}