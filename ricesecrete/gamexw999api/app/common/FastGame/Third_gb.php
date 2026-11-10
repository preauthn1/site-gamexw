<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_gb extends Third
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
        // 签名秘钥
        $key = json_decode($config['trdcfg']["tc_api_signkey"], true);
        $data['sign_key'] = $key['sign_key'];
        $encrypt_key = $key['encrypt_key'];
        // 加密秘钥
        $sign_key = $this->generateSign($data);
        $data['sign_key'] = $sign_key;
        $x = $this->generateX($data, $encrypt_key);
        $data['x'] = $x;
        return compact('sign_key', 'x');
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
        $param = $data['param'];
        // 删除其他变量
        unset($param['s']);
        $sign = $param['sign'];
        unset($param['sign']);
        unset($param['third']);
        unset($param['action_method']);
        unset($param['XDEBUG_SESSION_START']); // TODO 删掉

        // 参数转 int
        $param['action'] = (int) $param['action'];
        $param['timestamp'] = (int) $param['timestamp'];
        if (isset($param['type'])) $param['type'] = (int) $param['type'];

        // 按键名排序
        ksort($param);

        // 获取加密秘钥 wallet_key
        $key = json_decode($config['trdcfg']["tc_api_signkey"], true);
        // 追加 key 变量到数组
        $param['key'] = $key['wallet_key'];
        // 生成签名并校验
        if ($sign == md5(json_encode($param))) {
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
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $g_tcid = isset($config['g_tcid']) ? $config['g_tcid']:$config['trdcfg']['tc_id'];
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['user_token'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_token');
            $kvs['user_id'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_trdid');
        }
}


function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if( $func == 'REGISTER' ){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if( !$guser ){
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{

            }
        }
    }

    private function generateSign($data){
        unset($data['x']);
        unset($data['lang']);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $sign_key = strtoupper(md5($str));
        return $sign_key;
    }

    private function generateX($data, $secret = ''){
            if (version_compare(PHP_VERSION, '8.1.0', '<=')) {
            unset($data['x']);
            unset($data['lang']);
            $pad = 8 - (strlen(json_encode($data)) % 8);
            // openssl_encrypt 的 DES-ECB 加密方式不支持 PHP 8.2
            $encryptedData = openssl_encrypt(json_encode($data).str_repeat(chr($pad), $pad), 'DES-ECB', $secret,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
            return str_replace('/', '\/', base64_encode($encryptedData));
        } else {
            Utils::L('ThirdErrLogs',
                [
                    'msg'     => '当前 PHP 版本'.PHP_VERSION.'，openssl_encrypt 的 DES-ECB 仅支持 PHP 版本 <= 8.1',
                    'suggest' => '切换 PHP 版本 <= 8.1',
                ]
            );
            return '';
        }


    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjkxLCJ0Y19uYW1lIjoiR0IiLCJ0Y19pbnRpbWUiOiIyMDI0LTAzLTAyIDA1OjQxOjU0IiwidGNfY29kZSI6IkdCIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkuamF2ODg4OS5jb21cL2hjUmVxdWVzdCIsInRjX2FwaV9wcmlrZXkiOiJrRE5iSG5Hb09ObEpZb2JRNXNDeFZrTWw2SkhHVVp5QyIsInRjX2FwaV9zaWdua2V5Ijoie1wic2lnbl9rZXlcIjpcIjM1N2Q0Y2Y1NTVkNmI0YTE4ZGQxNjE3NDg3YmY2YmFkXCIsIFwiZW5jcnlwdF9rZXlcIjpcImViZmM4Y2M5ZTNiNDExMTE0MjA0OWJlNzA4YzNiMDdjXCIsIFwid2FsbGV0X2tleVwiOlwicHJvdHQ2MTZhcGlwcml2YXRlXCJ9IiwidGNfdGFnIjoiaGJ0MWloZG5VUVl0WFRoRlI2IiwidGNfY3VycmVuY3kiOiJJTlIiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiZW4iLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmZnLmdhbWVcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MTZcL2M0NDExMTFlOWRmZjY0ZjVlNjc1YTI5NGMzOTQ5MmRhLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAxIiwidGNfbWF4X2JldCI6Ijk5OTk5OTkuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmZnLmdhbWVcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MTZcLzQxYWZiNWE3MjE2M2FiZDM0NTUyYjM3YTA2NjhiY2FkLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOiIiLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjE5MiwiZ2FfdGNpZCI6OTEsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQxIiwiZ2Ffcm91dGUiOiJcLyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcImxhbmdcIjpcIntsYW5nfVwiLFwiYWN0aW9uXCI6XCIxNFwiLFwiYXBwSURcIjpcInt0Y3RhZ31cIixcImFwcFNlY3JldFwiOlwie3RjYXBpa2V5fVwiLFwidWlkXCI6XCJ7dWlkfVwiLFwib3JkZXJOb1wiOlwie29fdHJhbnNpZH1cIixcImFtb3VudFwiOlwie29fYW1vdW50fVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjoge1wia2V5XCI6XCJyZXR1cm5Db2RlXCIsXCJ2YWx1ZVwiOlwiMDAwMFwifSwgXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuQW1vdW50XCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTkzLCJnYV90Y2lkIjo5MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRTEiLCJnYV9yb3V0ZSI6IlwvIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie2xhbmd9XCIsXCJhY3Rpb25cIjpcIjEzXCIsXCJhcHBJRFwiOlwie3RjdGFnfVwiLFwiYXBwU2VjcmV0XCI6XCJ7dGNhcGlrZXl9XCIsXCJ1aWRcIjpcInt1aWR9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOiB7XCJrZXlcIjpcInJldHVybkNvZGVcIixcInZhbHVlXCI6XCIwMDAwXCJ9LCBcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5BbW91bnRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxOTQsImdhX3RjaWQiOjkxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcLyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcImFjdGlvblwiOlwiMTdcIixcImFwcElEXCI6XCJ7dGN0YWd9XCIsXCJhcHBTZWNyZXRcIjpcInt0Y2FwaWtleX1cIixcInN0YXJ0dGltZVwiOlwie3N0YXJ0dGltZX1cIixcImVuZHRpbWVcIjpcIntlbmR0aW1lfVwiLFwicGFnZVwiOlwiMVwiLFwicGVybnVtYmVyXCI6XCI1MDAwXCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6IHtcImtleVwiOlwicmV0dXJuQ29kZVwiLFwidmFsdWVcIjpcIjAwMDBcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGEubGlzdFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1Mzk0ODY2LFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI1Mzk0ODY2LFwic3RhcnR0aW1lXCI6XCIyMDI0LTA5LTA0IDA0OjEzOjA3XCIsXCJlbmR0aW1lXCI6XCIyMDI0LTA5LTA0IDA0OjE4OjA3XCIsXCJwYWdlXCI6MCxcInBlcm51bWJlclwiOjUwMDB9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE5NSwiZ2FfdGNpZCI6OTEsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcImFjdGlvblwiOlwiMTJcIixcImlzX2h0dHBzXCI6XCIxXCIsXCJhcHBJRFwiOlwie3RjdGFnfVwiLFwiYXBwU2VjcmV0XCI6XCJ7dGNhcGlrZXl9XCIsXCJ1aWRcIjpcInt1bmFtZX1cIiwgXCJnYW1lQ29kZVwiOlwie2d0YWd9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOiB7XCJrZXlcIjpcInJldHVybkNvZGVcIixcInZhbHVlXCI6XCIwMDAwXCJ9LCBcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZGF0YS5wYXRoXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTk2LCJnYV90Y2lkIjo5MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcImFjdGlvblwiOlwiMTFcIixcImFwcElEXCI6XCJ7dGN0YWd9XCIsXCJhcHBTZWNyZXRcIjpcInt0Y2FwaWtleX1cIixcInVpZFwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJyZWciLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6IHtcImtleVwiOlwicmV0dXJuQ29kZVwiLFwidmFsdWVcIjpcIjAwMDAsMTAwMVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
