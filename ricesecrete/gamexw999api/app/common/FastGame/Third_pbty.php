<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\ThirdConfigs;

class Third_pbty extends Third
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

        $agent = $agent_code = $config['trdcfg']['tc_tag'];
        $agent_key = $config['trdcfg']['tc_api_prikey'];
        $signKey = $config['trdcfg']['tc_api_signkey'];
        $iv = $config['trdcfg']['tc_token'];

        $timestamp = time()*1000;
        $hashToken = md5($agent_code. $timestamp . $agent_key);
        $tokenPayLoad = $agent_code . '|' . $timestamp . '|' . $hashToken;
        $token = $this->encryptAES($signKey, $tokenPayLoad,$iv);
        //echo $token;die;
        return compact('token','agent');
    }

    private function encryptAES($secretKey, $tokenPayLoad,$iv){
        $encrypt = openssl_encrypt($tokenPayLoad, "AES-128-CBC", $secretKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypt);
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


        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {

        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {


        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {

            $kvs['traceId'] = md5(date("YmdHis") . Utils::random(16));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {

        }

    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQ2LCJ0Y19uYW1lIjoiXHU1ZTczXHU1MzVhXHU5YWQ0XHU4MGIyIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxOTozMzoyOSIsInRjX2NvZGUiOiJQQlRZIiwidGNfc2V0X2NvZGUiOiIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3BhYXBpc3RnLm9yZW84OC5jb21cL2IyYiIsInRjX2FwaV9wcmlrZXkiOiIxOTdiYjE5Mi05NGI3LTQxNjAtOWIwNy0xMGEyZTY5ODUyNDYiLCJ0Y19hcGlfc2lnbmtleSI6ImMxSDBucENWODd0QlZrZ0MiLCJ0Y190YWciOiJYNEEwMSIsInRjX2N1cnJlbmN5IjoiVFdEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiUmFuZG9tSW5pdFZlY3RvciIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ6aC1UVyIsInRjX3N0YXR1cyI6MCwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvNjBjOWI1ZWIzOGY1ZjUyNTEwZDY4N2VmZWU5NDI1NWIucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjEwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzBhZTNiZGNiOTE0MDE3OTBlNThlYzFiNzk4Mjg1N2RmLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxODQsImdhX3RjaWQiOjQ2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL3BsYXllclwvZGVwb3NpdCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcInVzZXJDb2RlXCI6XCJ7YWdlbnR9XCIsXCJ0b2tlblwiOlwie3Rva2VufVwifSIsImdhX3BwYXJhbSI6IntcImFtb3VudFwiOlwie2Ftb3VudH1cIixcInRyYW5zYWN0aW9uSWRcIjpcInt0cmFjZUlkfVwiLFwidXNlckNvZGVcIjpcInt1bmFtZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJhdmFpbGFibGVCYWxhbmNlXCIsXCJ2YWx1ZVwiOlwiVlwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiYXZhaWxhYmxlQmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE4NSwiZ2FfdGNpZCI6NDYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvcGxheWVyXC9pbmZvIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJ1c2VyQ29kZT17dW5hbWV9IiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwiLFwidXNlckNvZGVcIjpcInthZ2VudH1cIixcInRva2VuXCI6XCJ7dG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoiIiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiYXZhaWxhYmxlQmFsYW5jZVwiLFwidmFsdWVcIjpcIlZcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImF2YWlsYWJsZUJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxODYsImdhX3RjaWQiOjQ2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL3JlcG9ydFwvYWxsLXdhZ2VycyIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiZGF0ZUZyb209e3N0YXJ0VGltZX0mZGF0ZVRvPXtlbmRUaW1lfSZzZXR0bGU9MSIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcInVzZXJDb2RlXCI6XCJ7YWdlbnR9XCIsXCJ0b2tlblwiOlwie3Rva2VufVwifSIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImh0dHBjb2RlXCIsXCJ2YWx1ZVwiOjIwMH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcInJsdFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE4NywiZ2FfdGNpZCI6NDYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvcGxheWVyXC9sb2dpbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcInVzZXJDb2RlXCI6XCJ7YWdlbnR9XCIsXCJ0b2tlblwiOlwie3Rva2VufVwifSIsImdhX3BwYXJhbSI6IntcInVzZXJDb2RlXCI6XCJ7dW5hbWV9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwibG9naW5VcmxcIixcInZhbHVlXCI6XCJWXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJsb2dpblVybFwifX0iLCJnYV9yYXRlIjoxMCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxODgsImdhX3RjaWQiOjQ2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJSRUdJU1RFUiIsImdhX3JvdXRlIjoiXC9wbGF5ZXJcL2NyZWF0ZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIixcInVzZXJDb2RlXCI6XCJ7YWdlbnR9XCIsXCJ0b2tlblwiOlwie3Rva2VufVwifSIsImdhX3BwYXJhbSI6IntcImxvZ2luSWRcIjpcInt1bmFtZX1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwibG9naW5JZFwiLFwidmFsdWVcIjpcIlZcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcImxvZ2luSWRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxODksImdhX3RjaWQiOjQ2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJXSVRIRFJBVyIsImdhX3JvdXRlIjoiXC9wbGF5ZXJcL3dpdGhkcmF3IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwiLFwidXNlckNvZGVcIjpcInthZ2VudH1cIixcInRva2VuXCI6XCJ7dG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoie1wiYW1vdW50XCI6XCJ7YW1vdW50fVwiLFwidHJhbnNhY3Rpb25JZFwiOlwie3RyYWNlSWR9XCIsXCJ1c2VyQ29kZVwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImFtb3VudFwiLFwidmFsdWVcIjpcIlZcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImFtb3VudFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
