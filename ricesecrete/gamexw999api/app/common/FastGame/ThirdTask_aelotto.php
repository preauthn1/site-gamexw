<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_aelotto extends ThirdTask implements TaskInterface
{

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        date_default_timezone_set('UTC'); //转三方时区时间
        $cfgvalues=$this->cfgvalues;
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
    function setBetListStartFlag($gtag, $thistime, $data)
    {
        $func = 'GETBETLIST';
        Utils::L('test',['准备保存API配置值', 'gcode'=>$gtag, 'thistime'=>$this, 'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            $cfg[$gtag]['page_max'] = 1;
            if ($data['status']['code'] == "0" && isset($data['data']['page_max']) && $cfg[$gtag]['list_page'] < $data['data']['page_max']) {
                $cfg[$gtag]['page_max'] = $data['data']['page_max'];
            }
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

        //默认未结算订单
        $sta = 1;
        if($data['status'] == 1){
            $sta = 1;
        }else if(in_array($data['status'],[4,6,3,5]) && $data['player_profit_amount'] > 0){
            $sta = 3;
        }else if(in_array($data['status'],[4,6,3,5]) && $data['player_profit_amount'] == 0){
            $sta = 4;
        }else if(in_array($data['status'],[4,6,3,5]) && $data['player_profit_amount'] <= 0){
            $sta = 2;
        }else if(in_array($data['status'],[2,7])){
            $sta = 0;
        }

        $settled_at = 0;
        switch($data['status']){
            case  2:
            case  7:
                $settled_at = $data['canceled_at'];
                break;
            case  3:
                $settled_at = $data['settled_at'];
                break;
            case  4:
                $settled_at = $data['paid_at'];
                break;
            case  5:
                $settled_at = $data['resettled_at'];
                break;
            case  6:
                $settled_at = $data['repaid_at'];
                break;
        }

        // 用于标识记录是新增还是更新  c 新增 u 更新
        $rd['gb_op'] = 'n';
        $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], 'aelotto', 'tag','g_id');
        $rd['gb_uname'] = $data['account'];
        $rd['gb_price'] = $data['bet_amount'];
        $rd['gb_sn'] = $data['order_id'];
        $rd['gb_winlose'] = $sta ;
        $rd['gb_bettime'] = $data['bet_at'];
        $rd['gb_overtime'] = $settled_at;
        $rd['gb_winprice'] = $data['paid_amount'];
//        //业务代码结束
        return $rd;
    }



    function procBetDataPre($gcfg, $data, $rlt){
        return $data ?? [];
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
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func,$gtag);
        $andtime = date("c",strtotime(date('Y-m-d 00:00:00', $thistime)));
        $endtime = date("c",strtotime(date('Y-m-d 23:59:59', $thistime)));
        $cen = [
            "lastruntime" => $thistime,
            "start" => $andtime,
            "end" => $endtime,
            "list_page" => 1,
            "page_max" => 1,
        ];
        if($cfg['lastruntime'] == 0) {   //初始化
            $flags = $cen;
        }else{
            if($cfg['list_page'] < $cfg['page_max']){
                $cen['list_page'] = $cfg['list_page']+1;
                $cen['page_max'] =  $cfg['page_max'];
                $cen['start'] = $cfg['start'];
                $cen['end'] = $cfg['end'];
                $flags = $cen;
            }else{
                $cen['list_page'] = 1;
                $cen['page_max'] = 1;
                $flags = $cen;
            }
        }
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
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
