<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_fb extends Third
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
        $timestamp = strval(time())."000";
        $body_str = json_encode($data);
        $head_sign = md5("{$body_str}.{$config['trdcfg']['tc_tag']}.{$timestamp}.{$config['trdcfg']['tc_api_prikey']}");
        return compact('head_sign',  'timestamp');
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $platform = $kvs['platform'] ?? 0;
        $kvs['platForm'] = $platform == 1 ? 'pc' : 'mobile'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            $info = $datas['data'];
            $cfgs = Games::getConfigs(null, $tcid);
            $tc_name = Meta::initGetField('tcname',$tcid,$gid,$uid); //平台名称
            $uname = Meta::initGetField('uname',$tcid,$gid,$uid); //用户名称
            $icoUrl = ''; //
            $handicap = 1; //亚洲盘 欧洲盘
            $language = Meta::initGetField('lang',$tcid,$gid,$uid) ;
            $lang = $language == 'zh-CN' ? 'ENG' : $language; //没有传到配置好的语种的时候  默认英文 配置在tclangmap 中 传cn的时候转成ENG  其他语言默认zh-cn 也是转成ENG
            $color = 'dark'; //主题颜色
            $currencyId = $cfgs['trdcfg']['tc_currency']; //货币代码
            $url =  "/index.html#/?token={$info['token']}&nickname={$uname}&apiSrc={$info['serverInfo']['apiServerAddress']}&pushSrc={$info['serverInfo']['pushServerAddress']}&platformName={$tc_name}&icoUrl={$icoUrl}&handicap={$handicap}&language={$lang}&color={$color}&currencyId={$currencyId}";
            $href = $ohterkvs['platform'] == 1 ? $info['serverInfo']['pcAddress'] : $info['serverInfo']['h5Address'];
            $kds['tcgameurl'] = $href.$url;
        }

    }




    function get_game_logs($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        $rlt=Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
        //如果是封装中有的方法 可以调用，比如：
        $cls=getc('ThirdTask_fb');
        if (!$cls) return false;
        /** @var ThirdTask_fb $ttobj */
        $ttobj=new $cls();
//        Utils::L('task_fb', ['游戏参数', $otherkvs]);
        $ttobj->init($apicfgs['trdcfg']['tc_id'],$apicfgs['trdcfg']['tc_code']);
        $rlt = Http::http($apicfgs, 'GETBETLIST', $this, $otherkvs);
        if ($rlt) {
            //处理API配置值
            $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST']['ga_keyfield'], true);
            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
//            Utils::L('task_fb',['游戏列表',$bds]);
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
}
