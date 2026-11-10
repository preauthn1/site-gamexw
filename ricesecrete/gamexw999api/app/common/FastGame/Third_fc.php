<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;
use app\common\lib\Util;
use app\common\service\MemberService;
use DateTime;

class Third_fc extends Third
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
        $Params = $data['param'];
        unset($data['param']);
        $Params_aes = openssl_encrypt(json_encode($Params,JSON_UNESCAPED_SLASHES), 'AES-128-ECB', $config['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA);
        $Sign = md5(json_encode($Params,JSON_UNESCAPED_SLASHES));
        $Params = base64_encode($Params_aes);
        return compact('Params', 'Sign');
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
        $sign = md5(json_encode($data,JSON_UNESCAPED_SLASHES));
        return $sign;
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
        /*Utils::L('test',['TokenTimestampTranser',$in,time()+$in-600]);
        return time()+$in-600;*/
    }

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        if($kvs['runfunc'] == 'GETBETLIST') {
            $max_time = $this->getBetListMaxTime();
            $kvs['next'] = $kvs['start'] + $max_time;
            $kvs['StartDate'] = date("Y-m-d H:i:s", $kvs['start']);
            $kvs['EndDate'] = date("Y-m-d H:i:s", $kvs['next']);
        }
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['Url'];
        }
        if ($func == 'GETBETLIST') {
            $kds['r_list'] = $kds['r_list'];
        }
    }


    function procBetData($list, $cfgs) {
        if (empty($list)) {
            return [];
        }
        Third::initExtClass($cfgs);
        // FC API数据时间的时区为UTC-4
        $tz = gettimezone("-4");
        $result = [];
        foreach ($list as $data) {
            $gb_winprice = Third::transExchangerate($data['prize'],0,2);
            $gb_otherwin = Third::transExchangerate($data['winlose'],0,2);
            $gb_price = Third::transExchangerate($data['bet'],0,2);
            $gid = Game::getGamesInfoBy($cfgs['trdcfg']['tc_id'], $data['gameID'], 'tag', 'g_id');
            $gb_overtime = Util::getTimeZoneConversion(\app\common\Utils::getQueryDateTime($data['bdate']), $tz);
            $rd = [
                'gb_gid' => $gid,
                'gb_uid' => MemberService::getUidByReCode($data['account']),
                'gb_winlose' => $gb_winprice > 0 ? 3 : 2,
                'gb_bettime' => $gb_overtime,
                'gb_overtime' => $gb_overtime,
                'gb_price' => $gb_price,
                'gb_sn'=> $data['recordID'],
                'gb_winprice'=>$gb_winprice,
                'gb_otherwin'=>$gb_otherwin,
                'gb_validwater'=>$gb_winprice > $gb_price ? $gb_price : $gb_price - $gb_winprice,
            ];

            $result[] = $rd;
        }

        return $result;
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIzLCJ0Y19uYW1lIjoiRkMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAyLTIyIDEzOjIxOjU4IiwidGNfY29kZSI6IkZDIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcDEuZmNnMTY4OC5uZXQiLCJ0Y19hcGlfcHJpa2V5IjoiVFl0Wjc4NFBtYmFpTldIViIsInRjX2FwaV9zaWdua2V5IjoiIiwidGNfdGFnIjoiVDY2IiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoiOSIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjExXC8wMmUxZmFjMzI1NDA3N2Y5NTk1ZjgyOWQxODZiNmVkYy5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMS4wMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDI0MDYxMVwvZThhNGNkY2E3ZTYyOTlhODI4Yjc0MWM2NzMyM2JmNDIucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6bnVsbCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo1NywiZ2FfdGNpZCI6MjMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVERFVEFJTCIsImdhX3JvdXRlIjoiXC9HZXRQbGF5ZXJSZXBvcnQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJBZ2VudENvZGVcIjpcInt0Y3RhZ31cIixcIkN1cnJlbmN5XCI6XCJ7dGNjdXJyZW5jeX1cIixcIlBhcmFtc1wiOlwie1BhcmFtc31cIixcIlNpZ25cIjpcIntTaWdufVwiLFwicGFyYW1cIjp7XCJNZW1iZXJBY2NvdW50XCI6XCJ7dW5hbWV9XCIsXCJSZWNvcmRJRFwiOlwie2dic259XCJ9fSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcIlJlc3VsdFwiLFwidmFsdWVcIjowfSxcInRjZ2FtZWRldGFpbHVybFwiOntcImtleVwiOlwiVXJsXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6NTQsImdhX3RjaWQiOjIzLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL0xvZ2luIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiQWdlbnRDb2RlXCI6XCJ7dGN0YWd9XCIsXCJDdXJyZW5jeVwiOlwie3RjY3VycmVuY3l9XCIsXCJQYXJhbXNcIjpcIntQYXJhbXN9XCIsXCJTaWduXCI6XCJ7U2lnbn1cIixcInBhcmFtXCI6e1wiTWVtYmVyQWNjb3VudFwiOlwie3VuYW1lfVwiLFwiR2FtZUlEXCI6XCJ7Z3RhZ31cIixcIkxhbmd1YWdlSURcIjoxLFwiSG9tZVVybFwiOlwiaHR0cHM6XC9cL3d3dy5hZmFwb3N0YS50b3BcL1wiLFwiSmFja3BvdFN0YXR1c1wiOiB0cnVlLFwiQ2xvc2VGZWF0dXJlQnV5XCI6IGZhbHNlLFwiTG9naW5HYW1lSGFsbFwiOiBmYWxzZX19IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiUmVzdWx0XCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJVcmxcIn19IiwiZ2FfcmF0ZSI6NjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
