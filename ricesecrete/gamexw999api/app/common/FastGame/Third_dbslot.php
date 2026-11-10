<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_dbslot extends Third
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
            $kvs['game_code'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
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
        return 'eyJjb25maWciOnsidGNfaWQiOjQyLCJ0Y19uYW1lIjoiREJcdTk2ZmJcdTViNTAiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE5OjMzOjI5IiwidGNfY29kZSI6IkRCU0xPVCIsInRjX3NldF9jb2RlIjoiIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC94dy5hcGkuY2dwbGF5OC5uZXRcL2dmIiwidGNfYXBpX3ByaWtleSI6ImM5OGE5Mjg5NWM5NzdiNmNhMzkyYTBjZTA3NjY0ZjBkIiwidGNfYXBpX3NpZ25rZXkiOiJnZl9nZHNsb3Rfd2FsbGV0IiwidGNfdGFnIjoiYTliZDY3MjM5OWI4M2UyNDRjOTU2ZDVkMTIxN2ZiNGMiLCJ0Y19jdXJyZW5jeSI6IlRXRCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ6aC1UVyIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYyMFwvYzczZmM2NzVhZDhmNzA4NjdlNzk2Njc1ZTZhZDUwNDgucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjEwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MjBcL2Y3NDk0YmZlMzVlNDU2OTgwMTMwZTU1Mjg1MzAxNjYyLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxNjAsImdhX3RjaWQiOjQyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL1RyYW5zZmVySW4iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwicGxheWVyX25hbWVcIjpcIntwbGF5ZXJfbmFtZX1cIixcImFtb3VudFwiOlwie2Ftb3VudH1cIixcInRyYWNlSWRcIjpcInt0cmFjZUlkfVwiLFwid2FsbGV0X2NvZGVcIjpcInt3YWxsZXRfY29kZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNjEsImdhX3RjaWQiOjQyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiJcL0dldFBsYXllckJhbGFuY2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwicGxheWVyX25hbWVcIjpcIntwbGF5ZXJfbmFtZX1cIixcIndhbGxldF9jb2RlXCI6XCJ7d2FsbGV0X2NvZGV9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTYyLCJnYV90Y2lkIjo0MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC92M1wvQmV0XC9SZWNvcmRcL0dldCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJ2ZW5kb3JfY29kZVwiOlwie3ZlbmRvcl9jb2RlfVwiLFwic3RhcnRfdGltZVwiOlwie3N0YXJ0X3RpbWV9XCIsXCJlbmRfdGltZVwiOlwie2VuZF90aW1lfVwiLFwicGFnZVwiOjEsXCJwYWdlX3NpemVcIjo1MDAwfSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInJfbGlzdFwiOntcImtleVwiOlwiZGF0YS5iZXRsb2dzXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU1NTkyMjUsXCJjdXJ0aW1lXCI6MCxcImVuZFwiOjE3MjU1NTkyMjUsXCJzdGFydF90aW1lXCI6MTcyNTU1ODg2NDAwMCxcImVuZF90aW1lXCI6MTcyNTU1OTE2NDAwMCxcInZlbmRvcl9jb2RlXCI6XCJHRFNMT1RcIixcInNlY3JldF9rZXlcIjpcImM5OGE5Mjg5NWM5NzdiNmNhMzkyYTBjZTA3NjY0ZjBkXCIsXCJvcGVyYXRvcl90b2tlblwiOlwiYTliZDY3MjM5OWI4M2UyNDRjOTU2ZDVkMTIxN2ZiNGNcIixcInBhZ2VcIjowLFwicGFnZV9zaXplXCI6NTAwMH19IiwiZ2FfdXJscHJlIjoiaHR0cHM6XC9cL3h3LnJlY29yZC5jZ3BsYXk4Lm5ldFwvZ2YifSx7ImdhX2lkIjoxNjMsImdhX3RjaWQiOjQyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL0xhdW5jaCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwiZ2FtZV9jb2RlXCI6XCJ7Z2FtZV9jb2RlfVwiLFwibGFuZ3VhZ2VcIjpcIntsYW5ndWFnZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLmdhbWVfdXJsXCJ9fSIsImdhX3JhdGUiOjEwLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE2NCwiZ2FfdGNpZCI6NDIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL1BsYXllclwvQ3JlYXRlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInNlY3JldF9rZXlcIjpcIntzZWNyZXRfa2V5fVwiLFwib3BlcmF0b3JfdG9rZW5cIjpcIntvcGVyYXRvcl90b2tlbn1cIixcInBsYXllcl9uYW1lXCI6XCJ7cGxheWVyX25hbWV9XCIsXCJjdXJyZW5jeVwiOlwie2N1cnJlbmN5fVwifSIsImdhX3R5cGUiOiJyZWciLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNjUsImdhX3RjaWQiOjQyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiXC9UcmFuc2Zlck91dCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwiYW1vdW50XCI6XCJ7YW1vdW50fVwiLFwidHJhY2VJZFwiOlwie3RyYWNlSWR9XCIsXCJ3YWxsZXRfY29kZVwiOlwie3dhbGxldF9jb2RlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
