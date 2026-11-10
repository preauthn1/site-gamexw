<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_dbsport extends Third
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
        $kvs['wallet_code'] = $config['trdcfg']['tc_api_signkey'];
        $kvs['secret_key'] = $config['trdcfg']['tc_api_prikey'];
        $kvs['operator_token'] = $config['trdcfg']['tc_tag'];
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","game_code":"{game_code},"language":"{language}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_name'] = $uname;
            $kvs['language'] = $config['trdcfg']['tc_langmap'];
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","currency":"{currency}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_name'] = $uname;
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","amount":"{amount},"traceId":"{traceId},"wallet_code":"{wallet_code}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_name'] = $uname;
            $kvs['traceId'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_name'] = $uname;
        }
//        Utils::L('OGS_transOtherKvs', $kvs);
//        Utils::L('OGS_transOtherKvs', $kvs['runfunc']);
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
//        Utils::L('OGS_transOtherKvsData', $kds);
//        Utils::L('OGS_transResultData', $datas);
        if (in_array($func, ['REGISTER'])) {
            $kds['tcuserkey'] = "";
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQxLCJ0Y19uYW1lIjoiXHU3MThhXHU3MzJiXHU5YWQ0XHU4MGIyIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxOTozMzoyOSIsInRjX2NvZGUiOiJEQlNQT1JUIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3h3LmFwaS5jZ3BsYXk4Lm5ldFwvZ2YiLCJ0Y19hcGlfcHJpa2V5IjoiYzk4YTkyODk1Yzk3N2I2Y2EzOTJhMGNlMDc2NjRmMGQiLCJ0Y19hcGlfc2lnbmtleSI6ImdmX29ic3BvcnRzX3dhbGxldCIsInRjX3RhZyI6ImE5YmQ2NzIzOTliODNlMjQ0Yzk1NmQ1ZDEyMTdmYjRjIiwidGNfY3VycmVuY3kiOiJUV0QiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiemgtVFciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzMxYzE5OGY5ZmIzYTk3YjU4MGVmYThkM2MzYTBmMjg0LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIxMC4wMDAwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC81NzEzNzdmZDY2YmRlNzRkYjNkZTM3NTBlOTcxMzVkZC5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6MTU0LCJnYV90Y2lkIjo0MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC9UcmFuc2ZlckluIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInNlY3JldF9rZXlcIjpcIntzZWNyZXRfa2V5fVwiLFwib3BlcmF0b3JfdG9rZW5cIjpcIntvcGVyYXRvcl90b2tlbn1cIixcInBsYXllcl9uYW1lXCI6XCJ7cGxheWVyX25hbWV9XCIsXCJhbW91bnRcIjpcInthbW91bnR9XCIsXCJ0cmFjZUlkXCI6XCJ7dHJhY2VJZH1cIixcIndhbGxldF9jb2RlXCI6XCJ7d2FsbGV0X2NvZGV9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTU1LCJnYV90Y2lkIjo0MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9HZXRQbGF5ZXJCYWxhbmNlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInNlY3JldF9rZXlcIjpcIntzZWNyZXRfa2V5fVwiLFwib3BlcmF0b3JfdG9rZW5cIjpcIntvcGVyYXRvcl90b2tlbn1cIixcInBsYXllcl9uYW1lXCI6XCJ7cGxheWVyX25hbWV9XCIsXCJ3YWxsZXRfY29kZVwiOlwie3dhbGxldF9jb2RlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE1NiwiZ2FfdGNpZCI6NDEsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvdjNcL0JldFwvUmVjb3JkXC9HZXQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwidmVuZG9yX2NvZGVcIjpcInt2ZW5kb3JfY29kZX1cIixcInN0YXJ0X3RpbWVcIjpcIntzdGFydF90aW1lfVwiLFwiZW5kX3RpbWVcIjpcIntlbmRfdGltZX1cIixcInBhZ2VcIjoxLFwicGFnZV9zaXplXCI6NTAwMH0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGEuYmV0bG9nc1wifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1NTU5MzQxLFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI1NTU5MzQxLFwic3RhcnRfdGltZVwiOjE3MjU1NTg5ODAwMDAsXCJlbmRfdGltZVwiOjE3MjU1NTkyODAwMDAsXCJ2ZW5kb3JfY29kZVwiOlwiT0JTUE9SVFNcIixcInNlY3JldF9rZXlcIjpcImM5OGE5Mjg5NWM5NzdiNmNhMzkyYTBjZTA3NjY0ZjBkXCIsXCJvcGVyYXRvcl90b2tlblwiOlwiYTliZDY3MjM5OWI4M2UyNDRjOTU2ZDVkMTIxN2ZiNGNcIixcInBhZ2VcIjowLFwicGFnZV9zaXplXCI6NTAwMH19IiwiZ2FfdXJscHJlIjoiaHR0cHM6XC9cL3h3LnJlY29yZC5jZ3BsYXk4Lm5ldFwvZ2YifSx7ImdhX2lkIjoxNTcsImdhX3RjaWQiOjQxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL0xhdW5jaCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwiZ2FtZV9jb2RlXCI6XCJ7Z3RhZ31cIixcImxhbmd1YWdlXCI6XCJ7bGFuZ3VhZ2V9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZGF0YS5nYW1lX3VybFwifX0iLCJnYV9yYXRlIjoxMCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNTgsImdhX3RjaWQiOjQxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJSRUdJU1RFUiIsImdhX3JvdXRlIjoiXC9QbGF5ZXJcL0NyZWF0ZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwiY3VycmVuY3lcIjpcIntjdXJyZW5jeX1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInRjdXNlcmtleVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTU5LCJnYV90Y2lkIjo0MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvVHJhbnNmZXJPdXQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwicGxheWVyX25hbWVcIjpcIntwbGF5ZXJfbmFtZX1cIixcImFtb3VudFwiOlwie2Ftb3VudH1cIixcInRyYWNlSWRcIjpcInt0cmFjZUlkfVwiLFwid2FsbGV0X2NvZGVcIjpcInt3YWxsZXRfY29kZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }
}
