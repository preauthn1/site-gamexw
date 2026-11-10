<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;

class Third_jili extends Third
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
        date_default_timezone_set('America/Caracas'); //转三方时区时间
        $pam = $data;
        unset($pam['Key']);
        if (isset($pam['AgentId'])) {
            $KeyA = urldecode(http_build_query(myArrayFilter($pam)));
        } else {
            $KeyA = urldecode(http_build_query(myArrayFilter($pam))).'&AgentId='.$config['trdcfg']['tc_tag'];
        }
        $KeyB = md5(date('ymj',time()).$config['trdcfg']['tc_tag'].$config['trdcfg']['tc_api_signkey']);
        $key = get_rand_char(6).md5($KeyA.$KeyB).get_rand_char(6);
        return compact('key');
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
        $sys=(new ApiRedis())->systemInfo();
        if($kvs['runfunc'] == 'GETUSERURL'){
            $kvs['platform'] = $kvs['platform'] == 1 ? 'web' : 'app';
            $kvs['homeurl'] = getAppUrl();
        }
        elseif ($kvs['runfunc'] == 'GETBETDETAIL') {
            $kvs['gbsn'] = $kvs['betInfo']['gb_sn'];
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['Data'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIxLCJ0Y19uYW1lIjoiVEFEQSBHYW1lcyIsInRjX2ludGltZSI6IjIwMjMtMDItMDkgMTI6NDE6MjgiLCJ0Y19jb2RlIjoiSklMSSIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvd2ItYXBpLTIudGFkYWdhbWluZy5jb21cL2FwaTEiLCJ0Y19hcGlfcHJpa2V5IjoiIiwidGNfYXBpX3NpZ25rZXkiOiI3NzVmMjk1NGE2YzhmZWIxYWNkNzY5MTRjM2JhYmUyMTI3MzI4NDM3IiwidGNfdGFnIjoiVDY2X1NlYW1sZXNzIiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoiZW4tVVMiLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjc3NzcsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvYTk0ZWQ3N2UyNThiNTAyMjk4MmQyMDJjMDMwNjMyZGIucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MTFcLzc1Nzc4MGNiZmNjMmYxOWM5YWI0NWQxMjliMjQ2MzU2LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NTgsImdhX3RjaWQiOjIxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRERVRBSUwiLCJnYV9yb3V0ZSI6IlwvR2V0R2FtZURldGFpbFVybCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcIldhZ2Vyc0lkXCI6XCJ7Z2Jzbn1cIiwgXCJBZ2VudElkXCI6XCJ7dGN0YWd9XCIsIFwiS2V5XCI6XCJ7a2V5fVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJFcnJvckNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2dhbWVkZXRhaWx1cmxcIjp7XCJrZXlcIjpcIkRhdGEuVXJsXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6NTMsImdhX3RjaWQiOjIxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL3NpbmdsZVdhbGxldFwvTG9naW5XaXRob3V0UmVkaXJlY3QiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6IlRva2VuPXt1bmFtZX0mR2FtZUlkPXtndGFnfSZMYW5nPXt0Y2xhbmdtYXB9JlBsYXRmb3JtPXtwbGF0Zm9ybX0mSG9tZVVybD17aG9tZXVybH0mQWdlbnRJZD17dGN0YWd9JktleT17a2V5fSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcIlRva2VuXCI6XCJ7dW5hbWV9XCIsXCJHYW1lSWRcIjpcIntndGFnfVwiLFwiTGFuZ1wiOlwie3RjbGFuZ21hcH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJFcnJvckNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcIkRhdGFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9XX0=';
    }

}
