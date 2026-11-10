<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_cg extends Third
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
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $g_tcid = isset($config['g_tcid']) ? $config['g_tcid']:$config['trdcfg']['tc_id'];
        $gid = $config['g_id'] > 0 ?  $config['g_id'] : Game::getGamesInfoBy($g_tcid, 'aecricket', 'code', 'g_id');
        if($kvs['runfunc'] == 'REGISTER'){
            $data = json_encode([
                'accountId' => Meta::initGetField('uname',$g_tcid,$gid,$uid),
                'currency' => Meta::initGetField('tccurrency',$g_tcid,$gid,$uid),
            ]);
            $key = Meta::initGetField('tcapikey',$g_tcid,$gid,$uid);
            $iv = Meta::initGetField('tcapisignkey',$g_tcid,$gid,$uid);
            $kvs['data'] = $this->aes256CbcEncrypt($key, $data, $iv);
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if($func == 'GETUSERURL'){
            $key = Meta::initGetField('tcapikey', $config['g_tcid'], $config['g_id'], $uid);
            $iv = Meta::initGetField('tcapisignkey', $config['g_tcid'], $config['g_id'], $uid);
            $url = $config['trdcfg']['tc_token'];
            $reCode = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid);
            $query = [
                'version' => '1.0',
                'language' => Meta::initGetField('lang',$tcid,$gid,$uid),
                'channelId' => Meta::initGetField('tctag', $config['g_tcid'], $config['g_id'], $uid),
                'mask' => false,
                'data' => $this->aes256CbcEncrypt($key, $reCode, $iv),
            ];
            $gameId = Meta::initGetField('gtag', $config['g_tcid'], $config['g_id'], $uid);
            $kds['tcgameurl'] = rtrim($url, '/') . '/'.$gameId.'/?' . http_build_query($query);
        }else if($func == 'GETBETDETAIL') {
            $url = $config['apis']['GETBETDETAIL']['ga_gparam'];
            //http://testenv-dev.cg11systems.com/client/api/game_result.php?snmode&sn=18216877
//            $gb_sn_ac = GameBetsSon::where(['gb_uid'=>$uid,'gb_gid'=>$ohterkvs['betInfo']['gb_gid'],'gb_sn'=>$ohterkvs['gbsn']])->value('gb_sn_ac');
//            $gids = Meta::initGetField('gtag',$tcid,$ohterkvs['betInfo']['gb_gid'],$uid); //游戏标识
            $lang = Meta::initGetField('lang',$tcid,$gid,$uid);
            $kds['tcgamedetailurl'] = $url."/client/api/game_result.php?snmode&sn=".$ohterkvs['gbsn']."&lang=".$lang;
        }else{
            $key = Meta::initGetField('tcapikey', $config['g_tcid'], $config['g_id'], $uid);
            $iv = Meta::initGetField('tcapisignkey', $config['g_tcid'], $config['g_id'], $uid);
            if( $kds ){
                $kds = $this->aes256CbcDecrypt($key, $kds, $iv);
            }else{
                $kds = [];
            }
        }



    }

    private function aes256CbcDecrypt($key, $data, $iv)
    {
        $raw_key = base64_decode($key);
        $raw_iv = base64_decode($iv);
        //$raw_data = base64_decode($data);
        $raw_data = $data;

        return openssl_decrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }

    private function aes256CbcEncrypt($key, $data, $iv)
    {
        $raw_key = base64_decode($key);
        $raw_iv = base64_decode($iv);
        //$raw_data = base64_decode($data);
        $raw_data = $data;

        return openssl_encrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjI2LCJ0Y19uYW1lIjoiQ0ciLCJ0Y19pbnRpbWUiOiIyMDIzLTA4LTA5IDIyOjEzOjQxIiwidGNfY29kZSI6IkNHIiwidGNfYXBpX3VybHByZSI6Imh0dHA6XC9cL2NyZWF0aXZlZ2FtZS1kZXYuY2cxMXN5c3RlbXMuY29tOjgwODgiLCJ0Y19hcGlfcHJpa2V5IjoiK0dqeUJJOXBZZmNUMFwvYnN2NURWSWllVmRISW9uc1p6dzBHMW9GNDA2Y1E9IiwidGNfYXBpX3NpZ25rZXkiOiJrZWlkVFwvR1lFVHNTQnhMcUhcL2lKM3c9PSIsInRjX3RhZyI6IjY5MjczIiwidGNfY3VycmVuY3kiOiJCUkwiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiJodHRwczpcL1wvY3JlYXRpdmVnYW1lLWNsaWVudC5jZzExcHJvLmNvbSIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJ7XCJDTlwiOiBcInpoXCIsXCJFTlwiOiBcImVuXCIsXCJCUlwiOlwicHRcIn0iLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDIzMDkxOVwvNDc4M2M1N2EwYTVmNDQ3NjFiNzNjM2Q1OTZjYjViY2EucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjEuMDAiLCJ0Y19tYXhfYmV0IjoiMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjMwOTE5XC9hNDM4ZjFjODcxOTMxNmVmZjAxZmY3ZDkwNDBmZjUwYS5wbmciLCJ0Y190aW1lem9uZSI6LTMsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6bnVsbCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo2MSwiZ2FfdGNpZCI6MjYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVERFVEFJTCIsImdhX3JvdXRlIjoiXC9vayIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6Imh0dHA6XC9cL3Rlc3RlbnYtZGV2LmNnMTFzeXN0ZW1zLmNvbSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6Int9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIjFcIn0sXCJ0Y2dhbWVkZXRhaWx1cmxcIjp7XCJrZXlcIjpcImRhdGEubXNnXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5iZXQxNjg4LnZpcCJ9LHsiZ2FfaWQiOjU5LCJnYV90Y2lkIjoyNiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9vayIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoie1widmVyc2lvblwiOlwiMS4wXCIsXCJsYW5ndWFnZVwiOlwie3RjbGFuZ21hcH1cIiwsXCJjaGFubmVsSWRcIjpcInt0Y3RhZ31cIn0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjF9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLm1zZ1wifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6Imh0dHBzOlwvXC9hcGkuYmV0MTY4OC52aXAifSx7ImdhX2lkIjo2MCwiZ2FfdGNpZCI6MjYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL3RkX2NyZWF0ZV9hY2NvdW50IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1widmVyc2lvblwiOlwiMS4wXCIsXCJjaGFubmVsSWRcIjpcInt0Y3RhZ31cIn0iLCJnYV90eXBlIjoiZ2V0YXV0aHRva2VuIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JDb2RlXCIsXCJ2YWx1ZVwiOlwiMFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
