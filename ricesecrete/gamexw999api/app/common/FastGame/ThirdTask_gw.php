<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_gw extends ThirdTask implements TaskInterface
{

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        $cfgvalues=$this->cfgvalues;
        //执行任务
        $this->runThirdTasks();
        $this->runJs($cfgvalues['jsst']);
        $this->runFy($cfgvalues['fyst']);
        $this->runStat($cfgvalues['statst']);
        $this->updateConfigValue();

    }

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag
     * @param $thistime
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    function setBetListStartFlag($gtag, $thistime, $data)
    {
        $func='GETBETLIST';
        Utils::L('test',['准备保存API配置值','gcode'=>$gtag,'thistime'=>$this,'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag=false;
        if (isset($cfg[$gtag])) {
//            $upflag=true;
//            //业务代码开始
//            if (isset($data['detail']) && isset($data['detail']['game_history']) && is_array($data['detail']['game_history'])) {
//                $cnt=count($data['detail']['game_history']);
//
//                $cfg[$gtag]['list_page'] = 1;//恢复page值
//                if ($cnt>0) {
//                    $cfg[$gtag]['list_to'] = Date("YmdHis", $data['detail']['game_history'][$cnt - 1]['bet_time'] - 1);//记录下最后读取记录的时间值-1秒
//                }
//            }
            //业务代码结束
        }
        if ($upflag) $this->updateApiConfigValue($func,$cfg);
    }

    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param $gcode 游戏在三方平台的代码
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procBetData($gcode, $data)
    {
        date_default_timezone_set('Asia/Shanghai');
        $rd = [];
        Utils::L('gw', [$gcode, $data]);
        try {
            //业务代码开始
            $rd['gb_op'] = 'n'; //可能会出现第一次返回是下注 第二次返回是结算的注单
            $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], 'GW_GAME', 'code','g_id');
            $rd['gb_uname'] = $data['thirdparty_id'];
            Third::initExtClass(['trdcfg' => ['tc_code'=> 'GW' ]]);
            $total_amount = Third::transExchangerate($data['total_price'],0);
            $rd['gb_price'] = $total_amount;
            $rd['gb_sn'] = 'gw_'.$data['project_id'];
            $rd['gb_bettime'] = strtotime($data['write_time']);
            $rd['gb_overtime'] = strtotime($data['bonus_time']);
            //is_cancel 大于0 都算无效注单
            if($data['is_cancel'] > 0){
                $winlose = 0;
            }else{
                if($data['is_getprize'] == 0){
                    //未结算
                    $winlose = 1;
                }else if($data['is_getprize'] == 1){
                    //赢
                    $winlose = 3;
                    $winprice = $data['bonus'];
                    $winprice = Third::transExchangerate($winprice,0);
                }else if($data['is_getprize'] == 2){
                    //输
                    $winlose = 2;
                }
            }
            $rd['gb_winlose'] = $winlose; //输赢金额大于0 就是赢 否则就是输
            $rd['gb_winprice'] = $winprice ?? 0;
            $rd['gb_betinfo'] = json_encode($data);
            //业务代码结束
            return $rd;
        }catch(\Exception $ex){
            Utils::L('gw',[$ex->getMessage()]);
            return false;
        }

    }



    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag,$thistime)
    {
        //不能去掉 否则订单时间会有误差
        date_default_timezone_set('Asia/Shanghai');
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func,$gtag);
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0){ //初始化
            $flags = [
                "lastruntime" => $thistime,
                "start" => date('Y-m-d H:i:s', $thistime - 600), //前10分钟
                "end" => $thistime, //当前执行时间 给下次执行用的
                "endtime" => date('Y-m-d H:i:s', $thistime  - 300), //前五分钟
            ];
            //Utils::L('taskfb',['初始化配置',$flags]);
        }else{
            //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
            //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
            //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
            $start = $cfg['end']; //查询上一次执行的时间
            if ($start + 3600 <= $thistime){//超过一个小时
                $start = $thistime - 3600; //一个小时以前
                $endtime = $thistime;
            }else{
                $endtime = $start;
                $start = $start - 300; //前5分钟
            }
            $flags = [
                "lastruntime" => $thistime,
                "start" => date('Y-m-d H:i:s', $start), //上次的执行时间减5分钟为这次的开始时间
                "end" => $thistime, //当前执行时间 给下次执行用的
                "endtime" => date('Y-m-d H:i:s', $endtime), //上次的执行时间为这次的结束时间
            ];
        }
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
    }


    function procBetDataPre($gcfg, $data, $rlt)
    {
        return $data ?: [];
    }


    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid,$type){
        //业务代码开始
        parent::transferBets($gid,$type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
        //业务代码结束
    }

    public function runFy($lasttime)
    {
        // TODO: Implement runJs() method.
    }

    public function runJs($lasttime)
    {
        // TODO: Implement runJs() method.
    }



}
