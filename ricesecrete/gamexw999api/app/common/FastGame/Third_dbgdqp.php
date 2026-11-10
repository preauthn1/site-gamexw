<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_dbgdqp extends Third
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
        return 'eyJjb25maWciOnsidGNfaWQiOjQzLCJ0Y19uYW1lIjoiXHU5YWQ4XHU3NjdiXHU2OGNiXHU3MjRjIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxOTozMzoyOSIsInRjX2NvZGUiOiJEQkdEUVAiLCJ0Y19zZXRfY29kZSI6IiIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wveHcuYXBpLmNncGxheTgubmV0XC9nZiIsInRjX2FwaV9wcmlrZXkiOiI5YzE3ZDg2NmRhMzQ2NTcxMGYyODI2Y2NiYWM2NThkNiIsInRjX2FwaV9zaWdua2V5IjoiZ2ZfeGdkX2NhcmQiLCJ0Y190YWciOiI5ZTI2NDEyYThiNTcwYTc3ZDEyNzExYmRjNjMyZTgyMSIsInRjX2N1cnJlbmN5IjoiVFdEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InpoLVRXIiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC80OWI5NmE3YjkzMTdiNTEwOGQxMmMyMmM2ZDE4MTcyZS5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMTAuMDAwMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAwMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvMTliMGYxNGFkZGFmMDI5ZjA4MzVjZGEyNDJkMDFmMDcucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjoxLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjE2NiwiZ2FfdGNpZCI6NDMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQiLCJnYV9yb3V0ZSI6IlwvVHJhbnNmZXJJbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwiYW1vdW50XCI6XCJ7YW1vdW50fVwiLFwidHJhY2VJZFwiOlwie3RyYWNlSWR9XCIsXCJ3YWxsZXRfY29kZVwiOlwie3dhbGxldF9jb2RlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE2NywiZ2FfdGNpZCI6NDMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvR2V0UGxheWVyQmFsYW5jZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJzZWNyZXRfa2V5XCI6XCJ7c2VjcmV0X2tleX1cIixcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7b3BlcmF0b3JfdG9rZW59XCIsXCJwbGF5ZXJfbmFtZVwiOlwie3BsYXllcl9uYW1lfVwiLFwid2FsbGV0X2NvZGVcIjpcInt3YWxsZXRfY29kZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJkYXRhLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxNjgsImdhX3RjaWQiOjQzLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL3YzXC9CZXRcL1JlY29yZFwvR2V0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInNlY3JldF9rZXlcIjpcIntzZWNyZXRfa2V5fVwiLFwib3BlcmF0b3JfdG9rZW5cIjpcIntvcGVyYXRvcl90b2tlbn1cIixcInZlbmRvcl9jb2RlXCI6XCJ7dmVuZG9yX2NvZGV9XCIsXCJzdGFydF90aW1lXCI6XCJ7c3RhcnRfdGltZX1cIixcImVuZF90aW1lXCI6XCJ7ZW5kX3RpbWV9XCIsXCJwYWdlXCI6MSxcInBhZ2Vfc2l6ZVwiOjUwMDB9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJkYXRhLmFjdGlvbl9yZXN1bHRcIixcInZhbHVlXCI6XCJTdWNjZXNzXCJ9LFwicl9saXN0XCI6e1wia2V5XCI6XCJkYXRhLmJldGxvZ3NcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTU1OTE2NCxcImN1cnRpbWVcIjowLFwiZW5kXCI6MTcyNTU1OTE2NCxcInN0YXJ0X3RpbWVcIjoxNzI1NTU4Nzk4MDAwLFwiZW5kX3RpbWVcIjoxNzI1NTU5MDk4MDAwLFwidmVuZG9yX2NvZGVcIjpcIlhHRFwiLFwic2VjcmV0X2tleVwiOlwiOWMxN2Q4NjZkYTM0NjU3MTBmMjgyNmNjYmFjNjU4ZDZcIixcIm9wZXJhdG9yX3Rva2VuXCI6XCI5ZTI2NDEyYThiNTcwYTc3ZDEyNzExYmRjNjMyZTgyMVwiLFwicGFnZVwiOjAsXCJwYWdlX3NpemVcIjo1MDAwfX0iLCJnYV91cmxwcmUiOiJodHRwczpcL1wveHd4dy5yZWNvcmQuY2dwbGF5OC5uZXRcL2dmIn0seyJnYV9pZCI6MTY5LCJnYV90Y2lkIjo0MywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9MYXVuY2giLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwicGxheWVyX25hbWVcIjpcIntwbGF5ZXJfbmFtZX1cIixcImdhbWVfY29kZVwiOlwie2d0YWd9XCIsXCJsYW5ndWFnZVwiOlwie2xhbmd1YWdlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEuZ2FtZV91cmxcIn19IiwiZ2FfcmF0ZSI6MTAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTcwLCJnYV90Y2lkIjo0MywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvUGxheWVyXC9DcmVhdGUiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1wic2VjcmV0X2tleVwiOlwie3NlY3JldF9rZXl9XCIsXCJvcGVyYXRvcl90b2tlblwiOlwie29wZXJhdG9yX3Rva2VufVwiLFwicGxheWVyX25hbWVcIjpcIntwbGF5ZXJfbmFtZX1cIixcImN1cnJlbmN5XCI6XCJ7Y3VycmVuY3l9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImRhdGEuYWN0aW9uX3Jlc3VsdFwiLFwidmFsdWVcIjpcIlN1Y2Nlc3NcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE3MSwiZ2FfdGNpZCI6NDMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IldJVEhEUkFXIiwiZ2Ffcm91dGUiOiJcL1RyYW5zZmVyT3V0IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInNlY3JldF9rZXlcIjpcIntzZWNyZXRfa2V5fVwiLFwib3BlcmF0b3JfdG9rZW5cIjpcIntvcGVyYXRvcl90b2tlbn1cIixcInBsYXllcl9uYW1lXCI6XCJ7cGxheWVyX25hbWV9XCIsXCJhbW91bnRcIjpcInthbW91bnR9XCIsXCJ0cmFjZUlkXCI6XCJ7dHJhY2VJZH1cIixcIndhbGxldF9jb2RlXCI6XCJ7d2FsbGV0X2NvZGV9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YS5hY3Rpb25fcmVzdWx0XCIsXCJ2YWx1ZVwiOlwiU3VjY2Vzc1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
