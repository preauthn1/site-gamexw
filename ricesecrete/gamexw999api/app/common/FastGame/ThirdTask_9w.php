<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use think\facade\Db;
use app\model\ThirdConfigs;
use app\common\lib\Util;

class ThirdTask_9w extends ThirdTask implements TaskInterface {

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec() {
        // TODO 必须转三方时区
        date_default_timezone_set('Asia/Shanghai'); //转三方时区时间
        $cfgvalues = $this->cfgvalues;
        //执行任务
        $this->runThirdTasks();
        $this->runJs($cfgvalues['jsst']);
        $this->runFy($cfgvalues['fyst']);
        $this->runStat($cfgvalues['statst']);
        $this->updateConfigValue();
        //转转回项目时区
        date_default_timezone_set(env('DEFAULT_TIMEZONE', 'Asia/Shanghai')); //转三方时区时间

    }

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag
     * @param $thistime
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    function setBetListStartFlag($gtag, $thistime, $data) {
        $func = $this->nowGetbetlistFunc;

        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);

        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            // 已完成
            if(isset($data['currentPage']) && isset($data['totalPage']) && ($data['currentPage'] != $data['totalPage'])){
                //页数还没拉完的时候，继续拉

            }else{//已经拉完页数，归零
                $cfg[$gtag]['page'] = 0;
            }

            //matched和unmatched注单更新最后日期
            if (isset($data['lastUpdateDate']) && $data['lastUpdateDate'] != 0) {
                $cfg[$gtag]['lastUpdateDate'] = $data['lastUpdateDate'];
            }
            //cancelled注单更新最后日期
            if (isset($data['cancelled']['lastUpdateDate']) && $data['cancelled']['lastUpdateDate'] != 0) {
                $cfg[$gtag]['lastUpdateDate'] = $data['cancelled']['lastUpdateDate'];
            }
            //voided注单更新最后日期
            if (isset($data['voided']['lastUpdateDate']) && $data['voided']['lastUpdateDate'] != 0) {
                $cfg[$gtag]['lastUpdateDate'] = $data['voided']['lastUpdateDate'];
            }
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime) {
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = $this->nowGetbetlistFunc;
        $cfg = $this->getApiCfgvalues($func, $gtag);


        $old_page = $cfg['page'] ?? 0; //查询上一次执行的分页
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        if(!isset($cfg['end_time']) || !isset($cfg['start_time'])){
            $cfg['lastruntime'] = 0;
            $cfg['lastUpdateDate'] = 0;
        }
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0) { //初始化
            $start = time() - 600; //前10分钟
            $endtime = $thistime - 300; //前5分钟
        } else {
            if ($old_page > 0) {
                // 继续使用上次的时间
                $start = strtotime($cfg['start_time']);
                $endtime = strtotime($cfg['end_time']);
            } else {
                //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
                //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
                //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
                $start = $cfg['end']??$thistime - 300; //查询上一次执行的时间
                $endtime = $start;
                $start = $start - 300; //前5分钟
            }
        }
        $flags = [
            "end" => min($endtime + 240, $thistime), //当前执行时间 给下次执行用的
            "lastruntime" => $thistime,
            "end_time" => is_numeric($endtime) ? date('Y-m-d H:i', $endtime) : $endtime, //当前执行时间 给下次执行用的
            "start_time" => is_numeric($start) ? date('Y-m-d H:i', $start) : $start, //上次的执行时间减5分钟为这次的开始时间

            'page_size' => 2000
        ];
        $flags['page'] = $old_page + 1;

        $threshold = 23 * 3600 + 57 * 60;
        $ts = time();


        if(!isset($cfg['lastUpdateDate']) || $cfg['lastUpdateDate'] == 0){//如果最大时间为空，获取23小时前的时间戳

            $flags['lastUpdateDate'] =  ($ts - $threshold) * 1000;
        }else if($ts - ($cfg['lastUpdateDate']/1000) > $threshold){
            $flags['lastUpdateDate'] =  ($ts - $threshold) * 1000;
        }else{
            $flags['lastUpdateDate'] = $cfg['lastUpdateDate'];
        }
        //这里可以设置初始时间，拉完注单后可以从这里设置
        // $flags['lastUpdateDate'] = '1730808000000';
        // $flags['end'] = '1730808000';
        // $flags["start_time"] = '2024-11-05 15:00';
        // $flags["end_time"] = '2024-11-05 20:00';

        //拉历史注单，设置这里，从22号开始到11月一号，手动设置每次一天
        //先开启 GETBETLIST3 拉，其它任务先关闭
        //$flags["start_time"] = '2024-11-08 03:00';
        //$flags["end_time"] = '2024-11-08 03:20';



        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
    }

    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param $gcode 游戏在三方平台的代码
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procBetData($gcode, $data) {
        return $data;
    }


    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){

        Third::initExtClass(['trdcfg' => ['tc_code'=> '9W' ]]);
        $newdatas = [];
        if(isset($rlt['settled']['resultList'][0])){
            $re = $this->newToarr($rlt['settled']['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }
        if(isset($rlt['voided']['resultList'][0])){
            $re = $this->newToarr($rlt['voided']['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }
        if(isset($rlt['cancelled']['resultList'][0])){
            $re = $this->newToarr($rlt['cancelled']['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }
        //未结算注单
        if(isset($rlt['matched']['resultList'][0])){
            $re = $this->newToarr($rlt['matched']['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }
        if(isset($rlt['unmatched']['resultList'][0])){
            $re = $this->newToarr($rlt['unmatched']['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }
        //已经结算注单和重新结算注单
        if(isset($rlt['resultList'][0])){
            $re = $this->newToarr($rlt['resultList']);
            $newdatas = array_merge($newdatas,$re);
        }

        if(!isset($newdatas[0])){
            return [];
        }


        foreach ($newdatas as $v) {
            if(!is_numeric($v['userId'])){
                continue;
            }
            $btmie = strtotime($v['betPlaced']);

            if($v['matchSettledDate'] != ''){
                $otmie = strtotime($v['matchSettledDate']);
            }else{
                $otmie = null;
            }

            //$gid = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], $v['eventTypeName'], 'tag', 'g_id');
            //echo $v['eventTypeName'].'-'.$gid.'<pre>';
            $bet = Third::transExchangerate($v['matchStake'], 0, 4);
            $win = Third::transExchangerate($v['profitLoss'], 0, 4);

            if($win < 0 && abs($win) != $bet){//如果真是下注金额有变,重新替换
                $bet = abs($win);
            }
            $win = $bet + $win;

            //判断注单是否存在或已经结算
            $betId = $v['betId'];
            $betdata = Db::table('dq_game_bets')->where('gb_sn', $betId)->field('*')->find();
            //判断输赢状态
            if($v['status'] == 'Not Settled'){
                $status = 1;
            }else if($v['status'] == 'Settled'){
                $status = $win > 0 ? 3 : 2;
            }else{
                $status = 0;
            }
            //判断是c还是u
            if($betdata && $betdata['gb_winlose'] != 1){

                if(isset($v['resettledDate'])){
                    //如果是重新结算
                    $op = 'n';
                    $status = $win > 0 ? 3 : 2;
                }else{
                    //已经结算，返回
                    continue;
                }

            }else if($betdata && $betdata['gb_winlose'] == 1){
                $op = 'n';
            }else{
                $op = 'c';
            }

            $rd[] = [
                //业务代码开始
                'gb_op' => $op, //等于N的时候 就是非重对 Y是重对 重对就是修改
                'gb_gid' => Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], $v['eventType'], 'tag', 'g_id')??0,

                'gb_uname' => $v['userId'],
                'gb_price' => $bet,
                'gb_sn' => $betId,
                'gb_bettime' => $btmie,
                'gb_overtime' => $otmie ? $otmie:null,

                'gb_winlose' => $status, //输赢金额大于0 就是赢 否则就是输
                'gb_winprice' => abs($win)
            ];

        }
        return $rd??[];
    }

    private function newToarr($data) {
        $re = [];
        if(isset($data[0]['matchAmount'])){//获取当前未结算注单

            foreach ($data as $v){
                $re['betPlaced']= $v['betPlaced'];
                $re['matchSettledDate']= $v['matchSettledDate'];
                $re['matchStake']= $v['matchAmount'];
                $re['profitLoss']= 0;
                $re['eventType']= $v['eventType'];
                $re['userId']= $v['userId'];
                $re['betId']= $v['betId'];
                $re['status'] = $v['betStatus'];
                $res[] = $re;
            }

        }else if(isset($data[0]['matchStake'])){//获取所有注单

            foreach ($data as $v){
                $re['betPlaced']= $v['betPlaced'];
                $re['matchSettledDate']= $v['matchSettledDate'];
                $re['matchStake']= $v['matchStake'];
                $re['profitLoss']= $v['profitLoss'];
                $re['eventType']= $v['eventType'];
                $re['userId']= $v['userId'];
                $re['betId']= $v['betId'];
                $re['status'] = $v['betStatus'];
                $res[] = $re;
            }

        }else if(isset($data[0]['win'])){//获取迷你游戏所有注单

            foreach ($data as $v){
                $re['betPlaced']= $v['betTime'];
                $re['matchSettledDate']= $v['settleDate'];
                $re['matchStake']= $v['stake'];
                $re['profitLoss']= $v['win'];
                $re['eventType']= $v['vendorPlatform'];
                $re['userId']= $v['userId'];
                $re['betId']= $v['vendorTxnId'];
                $re['status'] = 'settle';
                $res[] = $re;
            }

        }
        return $res;
    }


    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid, $type) {
        //业务代码开始
        parent::transferBets($gid, $type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
        //业务代码结束
    }

    public function runFy($lasttime) {
        // TODO: Implement runJs() method.
    }

    public function runJs($lasttime) {
        // TODO: Implement runJs() method.
    }
}
