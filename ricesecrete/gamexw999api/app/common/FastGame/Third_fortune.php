<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{ Meta, Third};


class Third_fortune extends Third
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
        return [];
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
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if ($func == 'GETUSERURL') {
            $baseUrl = $config['trdcfg']['tc_api_urlpre'];
            $data = [];
            $data['oid'] = $config['trdcfg']['tc_tag'];
            $data['oa'] = Meta::initGetField('uname', $tcid, $gid, $uid);
            $data['op'] = md5('fortune_'.$data['oa']);
            $data['hv'] = 1;//用户的物理设备地址，没有可以传1
            $data['ip'] = Util::get_ip();
            $data['sid'] = 0;#会话id，没有填写0
            $data['l'] = $config['trdcfg']['tc_langmap'];//语言
            $data['g'] = $config['g_tag'];
            $data['m'] = 0;#固定值
            $data['t'] = intval(microtime(true) * 1000);//毫秒级时间戳
            $data['ce'] =  $config['trdcfg']['tc_currency'];//货币
            $kds['tcgameurl'] = $baseUrl.'/'.$data['g'].'/index.html?'.http_build_query($data);
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMwLCJ0Y19uYW1lIjoiZm9ydHVuZSIsInRjX2ludGltZSI6IjIwMjQtMDUtMjQgMDI6NDM6MDYiLCJ0Y19jb2RlIjoiZm9ydHVuZSIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvbS5wZy1ubWdhcy5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiOGl2ZXJyYWkwOXY5b2RhN2p6IiwidGNfYXBpX3NpZ25rZXkiOiIiLCJ0Y190YWciOiI2NTg5NjUiLCJ0Y19jdXJyZW5jeSI6IkJSTCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJiciIsInRjX3N0YXR1cyI6MCwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuaGlyYWJkNC5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyMzAxMjJcLzFmMTMyODQ2Y2ZiNzQ0OTBjN2U3OGI3NWNkZmVkODBjLmpwZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAxIiwidGNfbWF4X2JldCI6Ijk5OTk5OTk5LjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6IiIsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NjcsImdhX3RjaWQiOjMwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL29rIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie30iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjF9LFwidGNnYW1lZGV0YWlsdXJsXCI6e1wia2V5XCI6XCJkYXRhLm1zZ1wifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6Imh0dHBzOlwvXC9hcGkua2dnYW1lczg4OC5jb20ifV19';
    }

}