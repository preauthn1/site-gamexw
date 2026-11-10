<?php


namespace app\common\FastGame;


use app\common\model\UserModel;
use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;

class Third_jdb extends Third
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
        $data = self::padString(json_encode($data));
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $config['trdcfg']['tc_api_signkey'], OPENSSL_NO_PADDING, $config['trdcfg']['tc_api_prikey']);
        $encrypted = base64_encode($encrypted);
        $x = str_replace(array('+','/','=') , array('-','_','') , $encrypted);
        return compact('x');
    }

    private static function padString($source)
    {
        $paddingChar = ' ';
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0;$i < $padLength;$i++)
        {
            $source .= $paddingChar;
        }
        return $source;
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
        date_default_timezone_set('America/Caracas'); //转三方时区时间
        $config = $this->apicfgs;
        if($kvs['runfunc'] == 'GETUSERURL') {
            $sys = (new ApiRedis())->systemInfo();
            $da = [
                'action' => 21,
                'ts' => time()*1000,
                'parent' => $config['trdcfg']['tc_tag'],
                'uid' => Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']),
//                'balance' => floatval(Meta::initGetField('ubalance', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid'])),
                'balance' => floatval(UserModel::getBalanceForDoubleWalletByGid($kvs['uid'])),
                'lang' => $config['trdcfg']['tc_langmap'],
                'gType' => $config['g_tag'],
                'mType' => $config['g_code'],
                'windowMode' => 2,
                'isAPP' => $kvs['platform'] == 1 ? false : true,
                'lobbyURL' => getAppUrl(),
            ];
            $encrypt = $this->MakeSign($config, $da);
            $kvs['dc'] = $config['trdcfg']['tc_token'];
            $kvs['x'] = $encrypt['x'];
        }
        if($kvs['runfunc'] == 'GETBETDETAIL') {
            $da = [
                'action' => 54,
                'ts' => time()*1000,
                'parent' =>Meta::initGetField('tctag', $kvs['betInfo']['gb_tcid'], $kvs['betInfo']['gb_gid'], $kvs['uid']),
                'uid' => Meta::initGetField('uname', $kvs['betInfo']['gb_tcid'], $kvs['betInfo']['gb_gid'], $kvs['uid']),
                'gType' => Meta::initGetField('gtag', $kvs['betInfo']['gb_tcid'], $kvs['betInfo']['gb_gid'], $kvs['uid']),
            ];
            $gb_betinfo = $kvs['betInfo']['gb_betinfo'];
            if (is_array($gb_betinfo)) {
                $json = isset($gb_betinfo['gameSeqNo']) ? $gb_betinfo : ($gb_betinfo[0] ?? []);
            } else {
                $json = json_decode($gb_betinfo,true);
            }
            if($json['action'] == 8 && isset($json['gameSeqNo'])){
                $order_sn = $json['gameSeqNo'];
            }else{
                $order_sn = $kvs['gbsn'];
            }
            $da['seqNo'] = $order_sn;
            $encrypt = $this->MakeSign($config, $da);
            $kvs['dc'] = $config['trdcfg']['tc_token'];
            $kvs['x'] = $encrypt['x'];
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['path'];
        }
        if($func == 'GETBETDETAIL') {
            $kds['tcgamedetailurl'] = $datas['data'][0]['path'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjI1LCJ0Y19uYW1lIjoiSkRCIiwidGNfaW50aW1lIjoiMjAyMy0wMi0yMyAxMjowMDoxOSIsInRjX2NvZGUiOiJKREIiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cDpcL1wvYXBpLmpkYjI0Ny5uZXQiLCJ0Y19hcGlfcHJpa2V5IjoiZTdhZDE1MTI0YTQxYzI2MSIsInRjX2FwaV9zaWdua2V5IjoiOTYwMTAyYjAwMjhmY2RjNiIsInRjX3RhZyI6InQ2NmJybCIsInRjX2N1cnJlbmN5IjoiQlJMIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiWkZTIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6ImVuLXVzIiwidGNfc3RhdHVzIjoxLCJzb3J0Ijo2NjY2LCJpY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MTFcL2Q5YzI2YzgzYjExZDBjODY5OTNhYjc1ZWMxMmQ1MTIxLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjpudWxsLCJ0Y19taW5fYmV0IjoiMC4xMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MSwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvYjA1YzkxZGNiNjQyZDAyNGNkMjcxOTllZTM3ZTcwMTAucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6bnVsbCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo1NiwiZ2FfdGNpZCI6MjUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvYXBpUmVxdWVzdC5kbyIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJkY1wiOlwie2RjfVwiLFwieFwiOlwie3h9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwic3RhdHVzXCIsXCJ2YWx1ZVwiOlwiMDAwMFwifSxcInRjdXNlcmtleVwiOntcImtleVwiOlwicGF0aFwifX0iLCJnYV9yYXRlIjoyNSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9XX0=';
    }
}
