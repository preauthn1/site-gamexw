<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_fb extends ThirdTask implements TaskInterface
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
        $func = 'GETBETLIST';
        Utils::L('test',['准备保存API配置值', 'gcode'=>$gtag, 'thistime'=>$this, 'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            /*//TODO 该地方用来处理分页数据 这次请求完后 下次请求page更新为1 读取上一次请求的最后时间 重置为下次请求的时间
            if(is_array($data['data']) && count($data['data']) > 0) {
                $end = ($cfg[$gtag]['end'] / 1000) + 180;
                $t = strtotime(date('Y-m-d H:i'.':00', $end)) * 1000;
            }
            $cfg[$gtag]['end'] = $t;*/
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
        $rd = [];
        //业务代码开始

//        NoResulted  未结算 0
//        Return  走水  和 2
//        Lost  输 3
//        Won  赢 4
//        WinReturn  赢半 5
//        LooseReturn  输半 6
//        Cancel               取消 7
        //默认未结算订单
        $sta = 1;
        $time = 0;
//        if(isset($data['oneData']['settleResult'])){
//            switch($data['oneData']['settleResult']){
//                case 2 :
//                    $sta = 4;
//                    $time = time();
//                    break;
//                case 3 :
//                    $sta = 2;
//                    $time = time();
//                    break;
//                case 4 :
//                case 5 :
//                case 6 :
//                    $sta = 3;
//                    $time = time();
//                break;
//                case 7 :
//                    $sta = 0;
//                    break;
//            }
//        }else{
//            //不存在settleResult的时候 以orderStatus状态
//            if($data['orderStatus'] == 2 || $data['orderStatus'] == 3){
//                $sta = 0;
//            }
//        }

        if($data['orderStatus'] == 0 || $data['orderStatus'] == 1 || $data['orderStatus'] == 4){
                $sta = 1;
        }else if($data['orderStatus'] == 2 || $data['orderStatus'] == 3){
                $sta = 0;
        }else if($data['orderStatus'] == 5){
                if($data['settleAmount'] > 0){
                    $sta = 3;
                }else{
                    $sta = 2;
                }
        }
//        $amount = abs($data['stakeAmount']);
//        //从我方到三方的时候 汇率相乘 回来的时候相除
//        $amount = Third::transExchangerate($amount, 0);
//
//        //彩金
//        $gb_winprice = 0;
//        if(isset($data['settleAmount'])){
//            $gb_winprice = abs($data['settleAmount']);
//            //从我方到三方的时候 汇率相乘 回来的时候相除
//            $gb_winprice = Third::transExchangerate($gb_winprice, 0);
//        }

        // 用于标识记录是新增还是更新  c 新增 u 更新
        $rd['gb_op'] = 'n';
        $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], 'football', 'tag','g_id');
        $rd['gb_uname'] = $data['merchantUserId'];
        $rd['gb_price'] = $data['stakeAmount'];
        $rd['gb_sn'] = $data['id']; //根据子订单来拆分订单
//        $rd['gb_cusfield'] = $data['id'] ;
        $rd['gb_winlose'] = $sta ;
        $rd['gb_bettime'] = $data['createTime'] / 1000;
        $rd['gb_overtime'] = isset($data['settleTime']) ? $data['settleTime'] / 1000 : $time;
        $rd['gb_winprice'] = $data['settleAmount'] ?? 0;
//        //业务代码结束
        return $rd;
    }


    function procBetDataPre($gcfg, $data, $rlt){
        //Utils::L('taskfb',['拿到的游戏数据',$data]);
        $info = [];
        if(is_array($data) && count($data) > 0){
            foreach($data as $v){
                 $otherkvs['fileid'] = $v['fileId'];
                $res = Http::http($gcfg,'GETBETLISTDETAIL',$this->trdClass, $otherkvs);
                if(is_array($res['data'])){
                    if(count($res['data']) > 0) {
                        foreach ($res['data'] as $v) {
                            $info[] = $v;
                        }
                    }

                }
            }
        }
        return $info;
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
        //取前10分钟到前3分钟 为期7分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func,$gtag);
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0){ //初始化

            $flags = [
                "lastruntime" => $thistime,
                "start" => ($thistime - 600) * 1000,
                "end" => ($thistime - 180) * 1000,
            ];
            //Utils::L('taskfb',['初始化配置',$flags]);
        }else{

            $start = $cfg['end'] + (180 * 1000); //查询上一次执行的时间
            if ($start + 3600 * 1000 <= $thistime * 1000){//超过一个小时
                $start = ($thistime - 3600) * 1000;
            }else{
                $start = $start - 300 * 1000;
            }
            $flags = [
                "lastruntime" => $thistime,
                "start" => $start,//($start - 600 * 1000),
                "end" => ($thistime - 180) * 1000,
            ];
        }
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
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
