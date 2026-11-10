<?php


namespace app\common\FastGame;


use app\model\{UserThirds, GameBetsTemp, Games};
use app\common\FastGame\Base\{Http, Meta, Third, Utils, Redis, ThirdTask};
use app\common\FastGame\Base\User;

class Third_gw extends Third
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
        $body_str = json_encode($data);
        $key = $config['trdcfg']['tc_api_prikey'];
        $head_hash = md5($body_str.$key);
        return compact('head_hash');
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     *
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        $uid = isset($kvs['uid'])?$kvs['uid']:0;
        $tcid =  $config['trdcfg']['tc_id'];
        $ts = time();
        $ms = microtime();
        $str_64 = Utils::random(64);
        if($kvs['runfunc'] == 'GETUSERURL'){
            $name = Meta::initGetField('uname', $tcid, $config['g_id'], $uid);
            $str_32 = Utils::random(32);
            $ut_token = md5("uid:{$uid}_tcid:{$tcid}_time:{$ts}_ms:{$ms}_str:{$str_32}");
            $guser = UserThirds::where('ut_tcid', $tcid)->where('ut_uid', $uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $name,
                    'ut_token' => $ut_token,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->ut_trdid =  $name;
                $guser->ut_token =  $ut_token;
                $guser->save();
            }
            $kvs['user_token'] = $ut_token;
        }else if($kvs['runfunc'] == 'DEPOSIT'){
            $ut_token = UserThirds::where('ut_tcid', $tcid)->where('ut_uid', $uid)->value('ut_token');
            $kvs['user_token'] = $ut_token;
            $money_token = md5("tid:{$tcid}_uid:{$uid}_type:DEPOSIT_time:{$ms}_str:{$str_64}");
            $kvs['in_money_token'] = $money_token;
            $key = "gw_recharge_" . $tcid . "_" . $money_token;
            Redis::setex($key, $money_token);
        }else if($kvs['runfunc'] == 'WITHDRAW'){
            $ut_token = UserThirds::where('ut_tcid', $tcid)->where('ut_uid', $uid)->value('ut_token');
            $kvs['user_token'] = $ut_token;
            $money_token = md5("tid:{$tcid}_uid:{$uid}_type:WITHDRAW_time:{$ms}_str:{$str_64}");
            $kvs['out_money_token'] = $money_token;
            $key = "gw_withdraw_" . $tcid . "_" . $money_token;
            Redis::setex($key, $money_token);
        }
        $kvs['roomId'] = explode('-', $config['trdcfg']['tc_api_signkey'])[0];
        $kvs['roomHash'] = explode('-', $config['trdcfg']['tc_api_signkey'])[1];
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

        if($func == 'GETUSERURL'){
            $cfgs = Games::getConfigs(null, $tcid);
            $mall_key = "{$ohterkvs['roomId']}-{$ohterkvs['roomHash']}";
            $url = $cfgs['trdcfg']['tc_api_urlpre'] . '/login/thirdparty/' . $mall_key . "?token={$ohterkvs['user_token']}&op={$cfgs['trdcfg']['tc_tag']}&lang={$cfgs['trdcfg']['tc_langmap']}";
            $kds['tcgameurl'] = $url;
        }else if($func == 'GETBALANCE'){
            $info = $datas['data'];
            if($info['isSuccess'] == 1){
                $arr = array_values($info['userList'][0]);
                $kds['tcbalance'] = $arr[0];
            }
        }
    }

    function get_game_logs($apicfgs,$otherkvs){
        //每分钟只能调用10次
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        // $rlt = Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
        //如果是封装中有的方法 可以调用，比如：
        $cls = getc('ThirdTask_gw');
        if (!$cls) return false;
        /** @var ThirdTask_fb $ttobj */
        $ttobj = new $cls();
        $ttobj->init($apicfgs['trdcfg']['tc_id'],$apicfgs['trdcfg']['tc_code']);
        $rlt = Http::http($apicfgs, 'GETBETLIST', $this, $otherkvs);

        // Utils::L('wm_test', ['游戏参数', $rlt]);
        if ($rlt) {
            //处理API配置值
            $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST']['ga_keyfield'], true);
            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
//            Utils::L('wm_test',['游戏列表',$bds]);
            $wds = [];
//            Utils::L('task_fb', ['游戏参数2', $bds]);
            $bds = $ttobj->procBetDataPre($apicfgs, $bds, $rlt);
//            Utils::L('task_fb',['处理后的游戏列表',$bds]);
            foreach ($bds as $bdk => $bdv) {
                //Utils::L('test',['处理',$bdv]);
                $bd = $ttobj->procBetData('all', $bdv);
//                Utils::L('test',['处理结果',$bdv]);
                $bd = array_merge($bd, [
                    'gb_tcid'=>$apicfgs['trdcfg']['tc_id'],
                    'gb_intime' => time(),
//                    'gb_betinfo' => json_encode($bdv)
                ]);
                array_push($wds, $bd);
            }
            //记录数据
//            Utils::L('test_fb',['合并结果',$wds]);
            (new GameBetsTemp())->saveAll($wds);
            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
            ThirdTask::procBetTempData($apicfgs['trdcfg']['tc_useridentify'], 'tcid', $apicfgs['trdcfg']['tc_id'], 0);
            $ttobj->transferBets($apicfgs['trdcfg']['tc_id'], 'all');

        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjE4LCJ0Y19uYW1lIjoiR1cgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIzLTAxLTA3IDAwOjAyOjM3IiwidGNfY29kZSI6IkdXIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkuc2hpY2FpLWNsaWVudC5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiNjA3M2RlOGVjMzBiZCIsInRjX2FwaV9zaWdua2V5IjoiMjk3LTU4NSIsInRjX3RhZyI6IkNVUCIsInRjX2N1cnJlbmN5IjoiVk5EIiwidGNfZXhjaGFuZ2VyYXRlIjoiMC4wMDEwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6InVzIiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyMzAxMDZcLzBmMjJlZGM2YmFhYjhkMDhlMzAwYWM3MjkxNTZiZjI3LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjEwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMCIsImNvdW50cnkiOiJbXCJUV1wiXSIsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6IiIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NDcsImdhX3RjaWQiOjE4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjFcL2dlbmVyYWxcL3dhbGxldFwvcmVjaGFyZ2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJoYXNoPXtoZWFkX2hhc2h9IiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwvanNvbjtcIn0iLCJnYV9wcGFyYW0iOiJ7XCJyb29tTWFzdGVySWRcIjpcIntyb29tSWR9XCIsXCJhY2Nlc3NVc2VyVG9rZW5cIjpcInt1c2VyX3Rva2VufVwiLFwiYWNjZXNzTW9uZXlUb2tlblwiOlwie2luX21vbmV5X3Rva2VufVwiLFwib3BlcmF0b3JcIjpcInt0Y3RhZ31cIixcInVzZXJJZFwiOlwie3VuYW1lfVwiLFwibW9uZXlcIjpcIntvX2Ftb3VudH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJlc3VsdFwiLFwidmFsdWVcIjowfSxcIm1lc3NhZ2VcIjp7XCJrZXlcIjpcIm1lc3NhZ2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjo0OCwiZ2FfdGNpZCI6MTgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvYXBpXC92MVwvZ2VuZXJhbFwvd2FsbGV0XC9tb3JlVXNlckJhbGFuY2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJoYXNoPXtoZWFkX2hhc2h9IiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwvanNvbjtcIn0iLCJnYV9wcGFyYW0iOiJ7XCJyb29tTWFzdGVySWRcIjpcIntyb29tSWR9XCIsXCJ1c2VyQXJyYXlcIjpbXCJ7dW5hbWV9XCJdLFwib3BlcmF0b3JcIjpcInt0Y3RhZ31cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJlc3VsdFwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NTAsImdhX3RjaWQiOjE4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjFcL2dlbmVyYWxcL2dhbWVcL3Byb2plY3RzSGlzdG9yeSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6Imhhc2g9e2hlYWRfaGFzaH0iLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uO1wifSIsImdhX3BwYXJhbSI6IntcInJvb21NYXN0ZXJJZFwiOlwie3Jvb21JZH1cIixcInBhZ2VcIjpcIjFcIixcInBhZ2VMaW1pdFwiOlwiMzAwMFwiLFwib3BlcmF0b3JcIjpcInt0Y3RhZ31cIixcInBlcmlvZFR5cGVcIjpcIjVcIixcInN0YXJ0VGltZVwiOlwie3N0YXJ0fVwiLFwiZW5kVGltZVwiOlwie2VuZHRpbWV9XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJlc3VsdFwiLFwidmFsdWVcIjowfSxcInJfbGlzdFwiOntcImtleVwiOlwiZGF0YS5saXN0XCJ9fSIsImdhX3JhdGUiOjcsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOiJ7XCJnd19nYW1lXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE2NzMyNzAxMDEsXCJjdXJ0aW1lXCI6MCxcInN0YXJ0XCI6MTY3MzI2OTUwMjAwMCxcImVuZFwiOjE2NzMyNjk5MjEwMDB9fSIsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NDYsImdhX3RjaWQiOjE4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjFcL2dlbmVyYWxcL3dhbGxldFwvcmVjaGFyZ2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOm51bGwsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwicmVzdWx0XCIsXCJ2YWx1ZVwiOjh9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MzYwMCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjo0OSwiZ2FfdGNpZCI6MTgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IldJVEhEUkFXIiwiZ2Ffcm91dGUiOiJcL2FwaVwvdjFcL2dlbmVyYWxcL3dhbGxldFwvd2l0aGRyYXciLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiJoYXNoPXtoZWFkX2hhc2h9IiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwvanNvbjtcIn0iLCJnYV9wcGFyYW0iOiJ7XCJyb29tTWFzdGVySWRcIjpcIntyb29tSWR9XCIsXCJhY2Nlc3NVc2VyVG9rZW5cIjpcInt1c2VyX3Rva2VufVwiLFwiYWNjZXNzTW9uZXlUb2tlblwiOlwie291dF9tb25leV90b2tlbn1cIixcIm9wZXJhdG9yXCI6XCJ7dGN0YWd9XCIsXCJ1c2VySWRcIjpcInt1bmFtZX1cIixcIm1vbmV5XCI6XCJ7b19hbW91bnR9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyZXN1bHRcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjpudWxsLCJnYV91cmxwcmUiOm51bGx9XX0=';
    }
}
