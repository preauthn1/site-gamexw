<?php


namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games, GameWmSpecial, UserThirds};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_wms extends Third
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
//        return true;
        // 此三方站回复数据无签名
        $param = $data['param'];
        $header = $data['header'];
        $ip_list = explode(',', $config['trdcfg']['tc_api_signkey']);
        //渠道id 等于数据库中的渠道id 或者ping请求的时候为true  并且请求ip要跟数据库定义的ip相等
        if(in_array($header['x-forwarded-for'], $ip_list)){
            if($param['signature'] == $config['trdcfg']['tc_api_prikey']){
                return true;
            }
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

    //获取密码
    private function getPassword($uname){
        return md5($uname.'jnuofewhufrwe');
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        $kvs['timestamp'] = time();
        if( !in_array($kvs['runfunc'], ['GETBETLIST', 'GETBETLISTSPEC']) ){
            $name = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['wm_name'] = $name;
        }
        if( $kvs['runfunc'] == 'REGISTER' ){
            //注册
            $kvs['password'] = $this->getPassword($name);
        }elseif ( $kvs['runfunc'] == 'GETUSERURL' ){
            $kvs['password'] = $this->getPassword($name);
            $lang = Meta::initGetField('tclangmap', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['mode'] = $config['g_code'];
            $kvs['lang'] = $this->getThirdLangByLang($lang);
            $kvs['returnurl'] = getAppUrl(false);#返回地址
        }elseif ( $kvs['runfunc'] == 'DEPOSIT' ){
            //充值，我方转三方
            $kvs['money'] = Third::transExchangerate($kvs['o_amount'], 1, 2);
        }elseif ( $kvs['runfunc'] == 'WITHDRAW' ){
            //充值，我方转三方
            $kvs['money'] = Third::transExchangerate($kvs['o_amount'], 0, 2);
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdname' => $ohterkvs['wm_name'],
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->ut_trdname =  $ohterkvs['wm_name'];
                //TODO 错误处理
                $guser->save();
            }
        }
    }

    //根据语言获取平台对应类型

    private function getThirdLangByLang($lang = 'cn'){
        //三方使用语言
        $lang = strtolower($lang);#转小写
        $languageMap = [
            'cn' => 0,     // 中文
            'en' => 1,     // 英文
            'th' => 2,     // 泰文
            'vi' => 3,     // 越南文
            'ja' => 4,     // 日文
            'ko' => 5,     // 韩文
            'hi' => 6,     // 印度文
            'ms' => 7,     // 马来西亚文
            'id' => 8,     // 印尼文
            'zh-TW' => 9,  // 繁体中文
            'es' => 10     // 西班牙文
        ];
        return $languageMap[$lang] ?? 0;
    }


    function get_game_logs($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        // $rlt = Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
        //如果是封装中有的方法 可以调用，比如：
        $cls = getc('ThirdTask_wm');
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
                    'gb_betinfo' => json_encode($bdv)
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

    function get_special_logs($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        // $rlt = Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
        //如果是封装中有的方法 可以调用，比如：
        $cls = getc('ThirdTask_wm');
        if (!$cls) return false;
        /** @var ThirdTask_wm $ttobj */
        $ttobj = new $cls();
        $ttobj->init($apicfgs['trdcfg']['tc_id'],$apicfgs['trdcfg']['tc_code']);
        $rlt = Http::http($apicfgs, 'GETBETLISTSPEC', $this, $otherkvs);

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
                $bd = $ttobj->procSpecialData($bdv);
//                Utils::L('test',['处理结果',$bdv]);
                $bd = array_merge($bd, [
                    'gs_tcid' => $apicfgs['trdcfg']['tc_id'],
                    'gs_create_time' => time(),
                    'gs_info' => json_encode($bdv)
                ]);
                $wds[] = $bd;
            }
            //记录数据
//            Utils::L('test_fb',['合并结果',$wds]);
            (new GameWmSpecial())->replace()->saveAll($wds);
            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
//            ThirdTask::procBetTempData($apicfgs['trdcfg']['tc_useridentify'], 'tcid', $apicfgs['trdcfg']['tc_id'], 0);
//            $ttobj->transferBets($apicfgs['trdcfg']['tc_id'], 'all');
        }


    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjM1LCJ0Y19uYW1lIjoiV01cdTc3MWZcdTRlYmEiLCJ0Y19pbnRpbWUiOiIyMDIyLTExLTIxIDAxOjU3OjMwIiwidGNfY29kZSI6IldNUyIsInRjX3NldF9jb2RlIjpudWxsLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3dtd2ItMDAxLndtYXBpODguY29tIiwidGNfYXBpX3ByaWtleSI6IjU5ZTdhN2QxNzkwZDQwZmI3OTM3OTI3ZGQ5ZmNlNWM0IiwidGNfYXBpX3NpZ25rZXkiOiIiLCJ0Y190YWciOiJYV0IxIiwidGNfY3VycmVuY3kiOiJ0dyIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ0dyIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvNGY5MzIzZjNjODkzNTA0MzdjZWZlOTM1NGQ3YTRmMjEucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJ0cmR1c2VybmFtZSIsInRjX21pbl9iZXQiOiIwLjEwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzUzYTY0NDY1NWJiZGE3MmNlODhiMzYyYzliNTU5YzY3LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxMjIsImdhX3RjaWQiOjM1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvcHVibGljXC9HYXRld2F5LnBocCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiY21kPUNoYW5nZUJhbGFuY2UmdmVuZG9ySWQ9e3RjdGFnfSZzaWduYXR1cmU9e3RjYXBpa2V5fSZ1c2VyPXt3bV9uYW1lfSZtb25leT17bW9uZXl9JnRpbWVzdGFtcD17dGltZXN0YW1wfSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yQ29kZVwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwicmVzdWx0LmNhc2hcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMjEsImdhX3RjaWQiOjM1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiJcL2FwaVwvcHVibGljXC9HYXRld2F5LnBocCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiY21kPUdldEJhbGFuY2UmdmVuZG9ySWQ9e3RjdGFnfSZzaWduYXR1cmU9e3RjYXBpa2V5fSZ1c2VyPXt3bV9uYW1lfSZ0aW1lc3RhbXA9e3RpbWVzdGFtcH0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvckNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcInJlc3VsdFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjExNywiZ2FfdGNpZCI6MzUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvYXBpXC9wdWJsaWNcL0dhdGV3YXkucGhwIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJjbWQ9R2V0RGF0ZVRpbWVSZXBvcnQmdmVuZG9ySWQ9e3RjdGFnfSZzaWduYXR1cmU9e3RjYXBpa2V5fSZzdGFydFRpbWU9e3N0YXJ0VGltZX0mZW5kVGltZT17ZW5kVGltZX0mdGltZXN0YW1wPXt0aW1lc3RhbXB9JnRpbWV0eXBlPTEmZGF0YXR5cGU9MCIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JDb2RlXCIsXCJ2YWx1ZVwiOlwiMCwxMDdcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcInJlc3VsdFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1NTYwMDU2LFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI1NTYwMDU2LFwic3RhcnRUaW1lXCI6XCIyMDI0MDkwNjAyMDkxNlwiLFwiZW5kVGltZVwiOlwiMjAyNDA5MDYwMjE0MTZcIn19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTE4LCJnYV90Y2lkIjozNSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVFNQRUMiLCJnYV9yb3V0ZSI6IlwvYXBpXC9wdWJsaWNcL0dhdGV3YXkucGhwIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJjbWQ9R2V0VGlwUmVwb3J0JnZlbmRvcklkPXt0Y3RhZ30mc2lnbmF0dXJlPXt0Y2FwaWtleX0mc3RhcnRUaW1lPXtzdGFydFRpbWV9JmVuZFRpbWU9e2VuZFRpbWV9JnRpbWVzdGFtcD17dGltZXN0YW1wfSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JDb2RlXCIsXCJ2YWx1ZVwiOlwiMCwxMDdcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcInJlc3VsdFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjExOSwiZ2FfdGNpZCI6MzUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvYXBpXC9wdWJsaWNcL0dhdGV3YXkucGhwIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJjbWQ9U2lnbmluR2FtZSZ2ZW5kb3JJZD17dGN0YWd9JnNpZ25hdHVyZT17dGNhcGlrZXl9JnVzZXI9e3dtX25hbWV9JnBhc3N3b3JkPXtwYXNzd29yZH0mbGFuZz17bGFuZ30mdm9pY2U9e3RjbGFuZ21hcH0mcmV0dXJudXJsPXtyZXR1cm51cmx9IiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoiIiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JDb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJyZXN1bHRcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMjAsImdhX3RjaWQiOjM1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJSRUdJU1RFUiIsImdhX3JvdXRlIjoiXC9hcGlcL3B1YmxpY1wvR2F0ZXdheS5waHAiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6ImNtZD1NZW1iZXJSZWdpc3RlciZ2ZW5kb3JJZD17dGN0YWd9JnNpZ25hdHVyZT17dGNhcGlrZXl9JnVzZXI9e3dtX25hbWV9JnBhc3N3b3JkPXtwYXNzd29yZH0mdXNlcm5hbWU9e3dtX25hbWV9JnRpbWVzdGFtcD17dGltZXN0YW1wfSIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJyZWciLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvckNvZGVcIixcInZhbHVlXCI6XCIwLDEwNFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjEyMywiZ2FfdGNpZCI6MzUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IldJVEhEUkFXIiwiZ2Ffcm91dGUiOiJcL2FwaVwvcHVibGljXC9HYXRld2F5LnBocCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiY21kPUNoYW5nZUJhbGFuY2UmdmVuZG9ySWQ9e3RjdGFnfSZzaWduYXR1cmU9e3RjYXBpa2V5fSZ1c2VyPXt3bV9uYW1lfSZtb25leT0te21vbmV5fSZ0aW1lc3RhbXA9e3RpbWVzdGFtcH0iLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvckNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcInJlc3VsdC5jYXNoXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
