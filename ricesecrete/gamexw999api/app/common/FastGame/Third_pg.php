<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\Games;

class Third_pg extends Third
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
        //签名步骤一：按字典序排序参数
        ksort($data);
        // $body_str = json_encode($data);
        $string = Http::ToUrlParams($data, false, []);
        $data['signature'] = md5("{$string}{$config['trdcfg']['tc_api_prikey']}");
        return $data;
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
        if($param['action'] == 'bulk_debit'){
            $param['records'] = json_encode($param['records']);
        }
        foreach($param as $k =>&$v){
            if(is_array($v)){
                $param[$k] = json_encode($v);
            }
        }
        unset($param['signature'], $param['third'], $param['action_method'], $param['api'], $param['operator'], $param['s'], $param['bulk'], $param['action']);
        ksort($param);
        $string = Http::ToUrlParams($param, false, []);
        $sign = md5("{$string}{$config['trdcfg']['tc_api_prikey']}");
        if($sign == $signature){
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $kvs['ron_token'] = Utils::random(16);
        $kvs['timestamp'] = time() * 1000;
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['trace_id'] = $this->generateUUID();
            $query = [
                'ops' => myEncrypt(Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid)),
                'btt' => 1,
                'l' => $config['trdcfg']['tc_langmap'],
                'iwk' => 1
            ];
            $kvs['extra_args'] = http_build_query($query);
            $kvs['ip'] = Utils::get_ip();
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        // if($func == 'GETUSERURL'){
        //     $url = $config['trdcfg']['tc_token'];
        //     $query = [
        //         'ot' => Meta::initGetField('tctag', $config['g_tcid'], $config['g_id'], $uid),
        //         'ops' => myEncrypt(Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid)),
        //         'btt' => 1,
        //         'l' => $config['trdcfg']['tc_langmap'],
        //         'iwk' => 1
        //     ];
        //     $gameId = Meta::initGetField('gtag', $config['g_tcid'], $config['g_id'], $uid);
        //     $kds['tcgameurl'] = rtrim($url, '/') . '/'.$gameId.'/index.html?' . http_build_query($query);
        // }

        if($func == 'GETBETDETAIL') {
            $url = $config['apis']['GETBETDETAIL']['ga_taskvalue'];
            $trace_id = Utils::random(20);
            $t = $datas['data']['operator_session'];
            if(strpos($ohterkvs['gbsn'],"-")){
                $sn = explode('-',$ohterkvs['gbsn']);
                $psid = $sn[1];
                $sid = $sn[0];
            }else{
                $gb_sn_ac = GameBetsSon::where(['gb_uid'=>$uid,'gb_gid'=>$ohterkvs['betInfo']['gb_gid'],'gb_sn'=>$ohterkvs['gbsn']])->value('gb_sn_ac');
                if($gb_sn_ac){
                    $psid = $ohterkvs['gbsn'];
                    $sid = $gb_sn_ac;
                }else{
                    $psid = $ohterkvs['betInfo']['gb_cusfield'];
                    $sid = $ohterkvs['gbsn'];
                }
            }
            $gids = Meta::initGetField('gtag',$tcid,$ohterkvs['betInfo']['gb_gid'],$uid); //游戏标识
            $lang = $config['trdcfg']['tc_langmap'];
            $kds['tcgamedetailurl'] = $url."/history/redirect.html?trace_id=".$trace_id."&t=".$t."&psid=".$psid."&sid=".$sid."&gid=".$gids."&lang=".$lang."&type=operator";
        }
    }

    function get_game_list($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        try{
            $otherkvs = [];
            $rlt=Http::http($apicfgs,'GETGAMELIST',self::$extClass,$otherkvs);
            if($rlt && $rlt['rs_code'] == 'S-100'){
                $arr = [];
                foreach ($rlt['records'] as $k=>$v){
                    $arr[$v['game_id']] = $v['game_name'];
                }
                Utils::writeToStaticFile('og_game.info.json', json_encode($arr));
            }
        }catch (\Exception $e){
//            dump($e->getMessage());
            return $e->getMessage();
        }

    }

    function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-4%03x-8%03x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjI4LCJ0Y19uYW1lIjoiUEcgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAxLTE0IDE1OjA3OjAzIiwidGNfY29kZSI6IlBHIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9wcm90aHJlZW5vdGlmeS5ua3dzLnh5elwvIiwidGNfYXBpX3ByaWtleSI6IjcyNDg4OUZFMzM2OENBQjhEODg4NjlBNzA2OEIwNTgwIiwidGNfYXBpX3NpZ25rZXkiOiJEODMyQTgxNzhCRDcyQzNGNDI2NEE5RTk3NkZDNTU4RSIsInRjX3RhZyI6IjkyMDREODVFLTg3NzctNjJDMy04MTQ5LUM4MkI0NTczNDcwNiIsInRjX2N1cnJlbmN5IjoiQkRUIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiaHR0cHM6XC9cL20ucGdyLW5tZ2EuY29tIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6ImJuIiwidGNfc3RhdHVzIjoxLCJzb3J0IjoxMDAsImljb24iOiJodHRwczpcL1wvaW1nLmhpcmFiZDQuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjIyXC8wNmQ4YTJiYzMwNGFhNzAwZmNlZjk0ZmZkOTExN2RhNy5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC41MCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmhpcmFiZDQuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjIyXC9hYzY4MDVlMWZkZmM5YTgyNDUwZDZkNDQxNDg1MGUyOS5wbmciLCJ0Y190aW1lem9uZSI6MSwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjpudWxsLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjYzLCJnYV90Y2lkIjoyOCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUREVUQUlMIiwiZ2Ffcm91dGUiOiJcL0xvZ2luXC92MVwvTG9naW5Qcm94eSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6InRyYWNlX2lkPXtyYW5kb219IiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wib3BlcmF0b3JfdG9rZW5cIjpcInt0Y3RhZ31cIixcInNlY3JldF9rZXlcIjpcInt0Y2FwaWtleX1cIixcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZGF0YVwiLFwidmFsdWVcIjpcIlZcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiJodHRwc1wvXC9hcGkucGctYm8uY29cL2V4dGVybmFsIn0seyJnYV9pZCI6NjQsImdhX3RjaWQiOjI4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2V4dGVybmFsLWdhbWUtbGF1bmNoZXJcL2FwaVwvdjFcL0dldExhdW5jaFVSTEhUTUwiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJ0cmFjZV9pZD17dHJhY2VfaWR9IiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wib3BlcmF0b3JfdG9rZW5cIjpcInt0Y3RhZ31cIixcInBhdGhcIjpcIlwve2d0YWd9XC9pbmRleC5odG1sXCIsXCJleHRyYV9hcmdzXCI6XCJ7ZXh0cmFfYXJnc31cIixcInVybF90eXBlXCI6XCJnYW1lLWVudHJ5XCIsXCJjbGllbnRfaXBcIjpcIntpcH1cIixcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInRjZ2FtZXVybFwiOntcImtleVwiOlwicmF3ZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6Imh0dHBzOlwvXC9hcGkucGctYm8uY28ifV19';
    }
}
