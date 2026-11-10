<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};


class Third_bgaming extends Third
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
        if(in_array($kvs['runfunc'],['REGISTER','GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['password'] = md5($uname);
        }
        $kvs['RequestTime'] = time();
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if ($func == 'REGISTER') {
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjEsInRjX25hbWUiOiJCZ2FtaW5nIiwidGNfaW50aW1lIjoiMjAyMi0wOS0wOCAxNDo1MzozMiIsInRjX2NvZGUiOiJCRyIsInRjX2FwaV91cmxwcmUiOiJodHRwOlwvXC90d3QuNTFwYW5odW8uY29tIiwidGNfYXBpX3ByaWtleSI6InpaYjNmc0FNR3hRYVY5aG1KWHpUazZFZiIsInRjX2FwaV9zaWdua2V5IjoiIiwidGNfdGFnIjoiZmluZGVyLWludCIsInRjX2N1cnJlbmN5IjoidXNkdCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6InpaYjNmc0FNR3hRYVY5aG1KWHpUazZFZiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJ7XCJ6aC1DTlwiOlwiemgtQ05cIixcImVuLVVTXCI6XCJlbi1VU1wiLFwidmktVk5cIjpcInZpLVZOXCIsXCJ0aC1USFwiOlwidGgtVEhcIixcImlkLUlOXCI6XCJpZC1JTlwiLFwicHQtQlJcIjpcInB0LUJSXCJ9IiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6bnVsbCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo1LCJnYV90Y2lkIjoxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL29wXC90aGlyZHRlc3QiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6ImdpZD17Z2lkfSZwYWdlPXtwYWdlfSZwc2l6ZT17Z2dldGJldGxpc3RzaXplfSZ1dG9rZW49e3V0b2tlbn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJwaG9uZVwiOlwie3Bob25lfVwiLFwidWlkXCI6XCJ7dWlkfVwiLFwiZ291cmxcIjpcIntnYXBpdXJscHJlfVwvYWJjXC97aWR9XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IntcImRhdGEuY291bnRcIjp7XCJuYW1lXCI6XCJcdThiYjBcdTVmNTVcdTY3NjFcdTY1NzBcIixcInZ0eXBlXCI6XCJpbnRcIixcInZrdlwiOm51bGx9LFwiZGF0YS5saXN0XCI6e1wibmFtZVwiOlwiXHU4YmIwXHU1ZjU1XHU1MjE3XHU4ODY4XCIsXCJ2dHlwZVwiOlwibGlzdFwiLFwidmtcIjp7XCJnX2lkXCI6e1wibmFtZVwiOlwiXHU4YmIwXHU1ZjU1SURcIixcInZ0eXBlXCI6XCJpbnRcIixcInZrdlwiOm51bGx9LFwiZ3R5cGVcIjp7XCJuYW1lXCI6XCJcdTdjN2JcdTU3OGJcIixcInZ0eXBlXCI6XCJpbnRcIixcInZrdlwiOntcIjBcIjpcIlx1NjVlMFx1NjU0OFwiLFwiMVwiOlwiXHU2NzA5XHU2NTQ4XCJ9fX19fSIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOlwiITAwMDAwLDAwMDAyXCJ9LFwiY291bnRcIjp7XCJrZXlcIjpcImRhdGEuY291bnRcIn0sXCJsaXN0XCI6e1wia2V5XCI6XCJkYXRhLmxpc3RcIixcImZpZWxkc1wiOntcImdpZFwiOntcImtleVwiOlwiZ19pZFwifSxcImdjb2RlXCI6e1wia2V5XCI6XCJnX2NvZGVcIn19fSxcInV0b2tlblwiOntcImtleVwiOlwidXRva2VuXCJ9LFwidXRva2VuZXhwXCI6e1wia2V5XCI6XCJ1ZXhwXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjozLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NiwiZ2FfdGNpZCI6MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9vcFwvdGdldHVzZXJ1cmwiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6InVpZD17dWlkfSZ1bmFtZT17dW5hbWV9JnVpZD17dWlwfSZ1dG9rZW49e3V0b2tlbn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7fSIsImdhX3R5cGUiOiJmaXJzdCIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIjAwMDAwXCJ9LFwicl91dG9rZW5cIjp7XCJrZXlcIjpcImRhdGEudG9rZW5cIn0sXCJyX3VzZXJnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLnVybFwifSxcInJfdXRva2VuZXhwXCI6e1wia2V5XCI6XCJkYXRhLnVleHBcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjMsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoyLCJnYV90Y2lkIjoxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJsb2dpbiIsImdhX3JvdXRlIjoiXC9sb2dpbiIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoidXNlcj17dXNlcn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOm51bGwsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6bnVsbCwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxLCJnYV90Y2lkIjoxLCJnYV9naWQiOjEsImdhX2Z1bmMiOiJyZWciLCJnYV9yb3V0ZSI6IlwvcmVnaXN0ZXJcL3tndGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6InVzZXI9e3VuYW1lfSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInBob25lXCI6XCJ7cGhvbmV9XCIsXCJ1aWRcIjpcInt1aWR9XCIsXCJnb3VybFwiOlwie2dhcGl1cmxwcmV9XC9hYmNcL3tpZH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOm51bGwsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NCwiZ2FfdGNpZCI6MSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvZ2FtZWxpc3QiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6ImdpZD17Z2lkfSZwYWdlPXtwYWdlfSZwc2l6ZT17Z2dldGJldGxpc3RzaXplfSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInBob25lXCI6XCJ7cGhvbmV9XCIsXCJ1aWRcIjpcInt1aWR9XCIsXCJnb3VybFwiOlwie2dhcGl1cmxwcmV9XC9hYmNcL3tpZH1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiJ7XCJkYXRhLmNvdW50XCI6e1wibmFtZVwiOlwiXHU4YmIwXHU1ZjU1XHU2NzYxXHU2NTcwXCIsXCJ2dHlwZVwiOlwiaW50XCIsXCJ2a3ZcIjpudWxsfSxcImRhdGEubGlzdFwiOntcIm5hbWVcIjpcIlx1OGJiMFx1NWY1NVx1NTIxN1x1ODg2OFwiLFwidnR5cGVcIjpcImxpc3RcIixcInZrXCI6e1wiZ19pZFwiOntcIm5hbWVcIjpcIlx1OGJiMFx1NWY1NUlEXCIsXCJ2dHlwZVwiOlwiaW50XCIsXCJ2a3ZcIjpudWxsfSxcImd0eXBlXCI6e1wibmFtZVwiOlwiXHU3YzdiXHU1NzhiXCIsXCJ2dHlwZVwiOlwiaW50XCIsXCJ2a3ZcIjp7XCIwXCI6XCJcdTY1ZTBcdTY1NDhcIixcIjFcIjpcIlx1NjcwOVx1NjU0OFwifX19fX0iLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIiEwMDAwMCwwMDAwMlwifSxcImNvdW50XCI6e1wia2V5XCI6XCJkYXRhLmNvdW50XCJ9LFwibGlzdFwiOntcImtleVwiOlwiZGF0YS5saXN0XCIsXCJmaWVsZHNcIjp7XCJnaWRcIjp7XCJrZXlcIjpcImdfaWRcIn0sXCJnY29kZVwiOntcImtleVwiOlwiZ19jb2RlXCJ9fX0sXCJ1dG9rZW5cIjp7XCJrZXlcIjpcInV0b2tlblwifSxcInV0b2tlbmV4cFwiOntcImtleVwiOlwidWV4cFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjpudWxsLCJnYV91cmxwcmUiOm51bGx9LHsiZ2FfaWQiOjMsImdhX3RjaWQiOjEsImdhX2dpZCI6MSwiZ2FfZnVuYyI6InRlc3QiLCJnYV9yb3V0ZSI6IlwvdGVzdCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoidXNlcj17dXNlcn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOm51bGwsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6bnVsbCwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfV19';
    }

}
