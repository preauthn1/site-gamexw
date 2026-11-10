<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_ogs extends Third
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
        $param = $data;
        $signature = $param['signature'];
        if ($param['action'] == 'bulk_debit') {
            $param['records'] = json_encode($param['records']);
        }
        foreach ($param as $k => &$v) {
            if (is_array($v)) {
                $param[$k] = json_encode($v);
            }
        }
        unset($param['signature'], $param['third'], $param['action_method'], $param['api'], $param['operator'], $param['s'], $param['bulk'], $param['action']);
        ksort($param);
        $string = Http::ToUrlParams($param, false, []);
        $sign = md5("{$string}{$config['trdcfg']['tc_api_prikey']}");
        if ($sign == $signature) {
            return true;
        }
        return false;
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
        $kvs['public_key'] = $config['trdcfg']['tc_api_prikey'];
        $kvs['operator_name'] = $config['trdcfg']['tc_tag'];
        $kvs['timestamp'] = time();
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_id'] = "togs_" . $uname;
            $kvs['nickname'] = "anni" . $uname;
            $kvs['lang'] = $config['trdcfg']['tc_langmap'];
            $kvs['token'] = md5($kvs['player_id']);
            $kvs['game_id'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
            $kvs['betlimit'] = $config['trdcfg']['tc_token'];
            $signaData = [
                'player_id' => $kvs['player_id'],
                'nickname' => $kvs['nickname'],
                'token' => $kvs['token'],
                'game_id' => $kvs['game_id'] ?: '',
                'betlimit' => $kvs['betlimit'],
                'timestamp' => $kvs['timestamp'],
            ];
            ksort($signaData);
            $string = Http::ToUrlParams($signaData, false, []);
            $kvs['signature'] = md5("{$string}{$config['trdcfg']['tc_api_signkey']}");
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_id'] = "togs_" . $uname;
            $kvs['nickname'] = "anni" . $uname;
            $signaData = [
                'player_id' => $kvs['player_id'],
                'nickname' => $kvs['nickname'],
                'timestamp' => $kvs['timestamp'],
            ];
            //签名步骤一：按字典序排序参数
            ksort($signaData);
            // $body_str = json_encode($data);
            $string = Http::ToUrlParams($signaData, false, []);
            $kvs['signature'] = md5("{$string}{$config['trdcfg']['tc_api_signkey']}");
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_id'] = "togs_" . $uname;
            $kvs['transaction_id'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['transfer_amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            $signaData = [
                'player_id' => $kvs['player_id'],
                'transaction_id' => $kvs['transaction_id'],
                'transfer_amount' => $kvs['transfer_amount'],
                'timestamp' => $kvs['timestamp'],
            ];
            ksort($signaData);
            // $body_str = json_encode($data);
            $string = Http::ToUrlParams($signaData, false, []);
            $kvs['signature'] = md5("{$string}{$config['trdcfg']['tc_api_signkey']}");
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['player_id'] = "togs_" . $uname;
            $signaData = [
                'player_id' => $kvs['player_id']
            ];
            ksort($signaData);
            // $body_str = json_encode($data);
            $string = Http::ToUrlParams($signaData, false, []);
            $kvs['signature'] = md5("{$string}{$config['trdcfg']['tc_api_signkey']}");
        }
//        Utils::L('OGS_transOtherKvs', $kvs);
//        Utils::L('OGS_transOtherKvs', $kvs['runfunc']);
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
//        Utils::L('OGS_transOtherKvsData', $kds);
//        Utils::L('OGS_transResultData', $datas);
        if ($func == 'GETAUTH') {
            if(isset($datas['records'])){
                $config = $this->apicfgs;
                $temp_bet = $this->_getBetlimit($config, $datas['records']);
                //跟新限制配置
                $up_data = [
                    'tc_token' => $temp_bet['id'],
                    'tc_min_bet' => $temp_bet['min_limit'],
                    'tc_max_bet' => $temp_bet['max_limit'],
                ];
                ThirdConfigs::where('tc_id', $tcid)->update($up_data);
            }
        }
        if ($func == "REGISTER") {
            $kds['tcuserkey'] = "";
        }
    }

    /**
     * 获取游戏限制区间数据
     * @param $config
     * @param $betlimit_arr
     * @return mixed
     */
    public function _getBetlimit($config, $betlimit_arr)
    {
        if (!empty($betlimit_arr)) {
            $tc_min_bet = floatval($config['trdcfg']['tc_min_bet']);
            $tc_max_bet = floatval($config['trdcfg']['tc_max_bet']);
            $diff_bet_arr = [];
            foreach ($betlimit_arr as $value) {
                $diff_bet_arr[] = [
                    'data' => $value,
                    'diff' => abs($value['min_limit'] - $tc_min_bet) + abs($value['max_limit'] - $tc_max_bet),
                ];
                if (floatval($value['min_limit']) <= $tc_min_bet && floatval($value['max_limit']) >= $tc_max_bet) {
                    //找到区间内的数据
                    return $value;
                }
            }
            usort($diff_bet_arr, function ($a, $b) {
                return $a['diff'] - $b['diff'];
            });
            return $diff_bet_arr[0]['data'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjM0LCJ0Y19uYW1lIjoiT0dcdTc3MWZcdTRlYmEiLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTE1IDIwOjM3OjQ0IiwidGNfY29kZSI6Ik9HUyIsInRjX3NldF9jb2RlIjoiIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcG9sbG8yLmFsbDU1NTUuY29tIiwidGNfYXBpX3ByaWtleSI6Im9MYU9ZTm1JeWZDYTN4YWJPSGZCdEh6WnA2Sko0N2xkIiwidGNfYXBpX3NpZ25rZXkiOiJGS3lpdFhNN25sRG1PNmE3bkUyUnY3TnJsdlh2SUxzWiIsInRjX3RhZyI6Im1vZzg2NXh3dHdkIiwidGNfY3VycmVuY3kiOiJUV0QiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIxMjkiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoidHciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzM2OTFjZDA5MmEzMWY5YzU1NjMzY2Q1OGNhM2MyMWY0LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIxLjAwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcL2Q0M2MwMGRkZjQ2MWRmOGNhN2EyNjljZjZiZTA0ZTJhLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxMTYsImdhX3RjaWQiOjM0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjJcL3BsYXRmb3JtXC90cmFuc2Zlci13YWxsZXRcL2RlcG9zaXQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJrZXlcIjpcIntwdWJsaWNfa2V5fVwiLFwib3BlcmF0b3ItbmFtZVwiOlwie29wZXJhdG9yX25hbWV9XCJ9IiwiZ2FfcHBhcmFtIjoie1wicGxheWVyX2lkXCI6XCJ7cGxheWVyX2lkfVwiLFwidHJhbnNhY3Rpb25faWRcIjpcInt0cmFuc2FjdGlvbl9pZH1cIixcInRyYW5zZmVyX2Ftb3VudFwiOlwie3RyYW5zZmVyX2Ftb3VudH1cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIixcInNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyc19jb2RlXCIsXCJ2YWx1ZVwiOlwiUy0xMDBcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcImJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMTAsImdhX3RjaWQiOjM0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRBVVRIIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjJcL3BsYXRmb3JtXC9vcGVyYXRvclwvYmV0bGltaXQiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImtleVwiOlwie3B1YmxpY19rZXl9XCIsXCJvcGVyYXRvci1uYW1lXCI6XCJ7b3BlcmF0b3JfbmFtZX1cIn0iLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoiZmlyc3QiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyc19jb2RlXCIsXCJ2YWx1ZVwiOlwiUy0xMDBcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMTEsImdhX3RjaWQiOjM0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjJcL3BsYXRmb3JtXC90cmFuc2Zlci13YWxsZXRcL2dldC1iYWxhbmNlIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJrZXlcIjpcIntwdWJsaWNfa2V5fVwiLFwib3BlcmF0b3ItbmFtZVwiOlwie29wZXJhdG9yX25hbWV9XCJ9IiwiZ2FfcHBhcmFtIjoie1wicGxheWVyX2lkXCI6XCJ7cGxheWVyX2lkfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJzX2NvZGVcIixcInZhbHVlXCI6XCJTLTEwMFwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiY3VycmVudF9iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTEyLCJnYV90Y2lkIjozNCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC9hcGlcL3YyXC9wbGF0Zm9ybVwvdHJhbnNhY3Rpb25cL2hpc3RvcnkiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImtleVwiOlwie3B1YmxpY19rZXl9XCIsXCJvcGVyYXRvci1uYW1lXCI6XCJ7b3BlcmF0b3JfbmFtZX1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJsaW1pdFwiOlwie2xpbWl0fVwiLFwiZmV0Y2hfaWRcIjpcIntmZXRjaF9pZH1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJzX2NvZGVcIixcInZhbHVlXCI6XCJTLTEwMFwifSxcInJfbGlzdFwiOntcImtleVwiOlwicmVjb3Jkc1wifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjowLFwiY3VydGltZVwiOjAsXCJsaW1pdFwiOjUwMDAwMCxcImZldGNoX2lkXCI6NjU2fX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMTMsImdhX3RjaWQiOjM0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjJcL3BsYXRmb3JtXC9nYW1lc1wvbGF1bmNoIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJrZXlcIjpcIntwdWJsaWNfa2V5fVwiLFwib3BlcmF0b3ItbmFtZVwiOlwie29wZXJhdG9yX25hbWV9XCJ9IiwiZ2FfcHBhcmFtIjoie1wicGxheWVyX2lkXCI6XCJ7cGxheWVyX2lkfVwiLFwibmlja25hbWVcIjpcIntuaWNrbmFtZX1cIixcInRva2VuXCI6XCJ7dG9rZW59XCIsXCJnYW1lX2lkXCI6XCJ7Z2FtZV9pZH1cIixcImJldGxpbWl0XCI6XCJ7YmV0bGltaXR9XCIsXCJ0aW1lc3RhbXBcIjpcInt0aW1lc3RhbXB9XCIsXCJzaWduYXR1cmVcIjpcIntzaWduYXR1cmV9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicnNfY29kZVwiLFwidmFsdWVcIjpcIlMtMTAwXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJnYW1lX2xpbmtcIn19IiwiZ2FfcmF0ZSI6MTAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTE0LCJnYV90Y2lkIjozNCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvYXBpXC92MlwvcGxhdGZvcm1cL3RyYW5zZmVyLXdhbGxldFwvcmVnaXN0ZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJrZXlcIjpcIntwdWJsaWNfa2V5fVwiLFwib3BlcmF0b3ItbmFtZVwiOlwie29wZXJhdG9yX25hbWV9XCJ9IiwiZ2FfcHBhcmFtIjoie1wicGxheWVyX2lkXCI6XCJ7cGxheWVyX2lkfVwiLFwibmlja25hbWVcIjpcIntuaWNrbmFtZX1cIixcInRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIixcInNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicnNfY29kZVwiLFwidmFsdWVcIjpcIlMtMTAwXCJ9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMTUsImdhX3RjaWQiOjM0LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiXC9hcGlcL3YyXC9wbGF0Zm9ybVwvdHJhbnNmZXItd2FsbGV0XC93aXRoZHJhdyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImtleVwiOlwie3B1YmxpY19rZXl9XCIsXCJvcGVyYXRvci1uYW1lXCI6XCJ7b3BlcmF0b3JfbmFtZX1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJwbGF5ZXJfaWRcIjpcIntwbGF5ZXJfaWR9XCIsXCJ0cmFuc2FjdGlvbl9pZFwiOlwie3RyYW5zYWN0aW9uX2lkfVwiLFwidHJhbnNmZXJfYW1vdW50XCI6XCJ7dHJhbnNmZXJfYW1vdW50fVwiLFwidGltZXN0YW1wXCI6XCJ7dGltZXN0YW1wfVwiLFwic2lnbmF0dXJlXCI6XCJ7c2lnbmF0dXJlfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJzX2NvZGVcIixcInZhbHVlXCI6XCJTLTEwMFwifSxcInRjdXNlcmtleVwiOntcImtleVwiOlwiYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
