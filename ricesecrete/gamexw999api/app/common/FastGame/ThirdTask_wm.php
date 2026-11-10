<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_wm extends ThirdTask implements TaskInterface
{

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        // TODO 必须转三方时区
        date_default_timezone_set('Asia/Shanghai'); //转三方时区时间
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
        $func='GETBETLIST';
        Utils::L('test',['准备保存API配置值','gcode'=>$gtag,'thistime'=>$this,'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag=false;
        if (isset($cfg[$gtag])) {
            $upflag=true;
            //业务代码开始
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
        $rd = [];
//        Utils::L('wm', [$gcode, $data]);
        //业务代码开始
        $rd['gb_op'] = $data['reset'] == 'N' ? 'c' : 'u'; //等于N的时候 就是非重对 Y是重对 重对就是修改
        $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], $this->get_gamecode($data['gid']), 'tag','g_id');
        $rd['gb_uname'] = $data['user'];
        $rd['gb_price'] = Third::transExchangerate($data['bet'],0); //当有效下注金额不等于下注金额的时候 用有效下注金额
        $rd['gb_sn'] = "{$data['betId']}_{$data['event']}_{$data['eventChild']}";
        $rd['gb_bettime'] = strtotime($data['betTime']);
        $rd['gb_overtime'] = strtotime($data['settime']);
        if($data['winLoss'] >= 0){
            $winlose = 3;
            $winprice = Third::transExchangerate($data['bet'] + $data['winLoss'],0);
        }else{
            $winlose = 2;
            $winprice = 0;
            $rd['gb_price'] = Third::transExchangerate(abs($data['winLoss']),0);//存在输一半的情况 需要将注金调整成输的金额
        }
        $rd['gb_winlose'] = $winlose; //输赢金额大于0 就是赢 否则就是输
        $rd['gb_winprice'] = $winprice;
        //业务代码结束
        return $rd;
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
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func,$gtag);
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0){ //初始化
            $flags = [
                "lastruntime" => $thistime,
                "start" => date('YmdHis', $thistime - 600), //前10分钟
                "end" => $thistime, //当前执行时间 给下次执行用的
                "endtime" => date('YmdHis', $thistime  - 300), //前五分钟
            ];
            //Utils::L('taskfb',['初始化配置',$flags]);
        }else{
            //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
            //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
            //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
            $start = $cfg['end']; //查询上一次执行的时间
//            if ($start + 3600 <= $thistime){//超过一个小时
//                $start = $thistime - 3600; //一个小时以前
//                $endtime = $thistime;
//            }else{
                $endtime = $start;
                $start = $start - 300; //前5分钟
//            }
            $flags = [
                "lastruntime" => $thistime,
                "start" => date('YmdHis', $start), //上次的执行时间减5分钟为这次的开始时间
                "end" => min($endtime + 240, $thistime), //当前执行时间 给下次执行用的
                "endtime" => date('YmdHis', $endtime), //上次的执行时间为这次的结束时间
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


    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param array $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procSpecialData($data)
    {
        $rd=[
            'gs_uid' => 0,
            'gs_gid' => 0,
            'gs_price' => 0.00,
            'gs_sn' => '',
            'gs_type' => 1,
            'gs_type_name' => '小费支出',
            'gs_time' => 0,
        ];
//        Utils::L('wm', [$gcode, $data]);

        //业务代码开始
        $rd['gs_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], 'live', 'tag','g_id');
        /* @var User*/
        $rd['gs_uid'] = (new UserThirds())->where(['ut_trdname' => $data['username']])->value('ut_uid');
        $rd['gs_price'] = $data['Tip'];
        $rd['gs_sn'] = "{$data['betId']}_{$data['event']}_{$data['eventChild']}";
        $rd['gs_time'] = strtotime($data['betTime']);
        //业务代码结束
        return $rd;
    }

    function get_gamecode($key)
    {
//        101:百家乐,102:龙虎,103:轮盘,104:骰宝,105:牛牛,107:番摊,108:色碟,110:鱼虾蟹,128:安達巴哈
        $arr = [
            '101' => 'onlybac',
            '102' => 'onlydgtg',
            '103' => 'onlyrou',
            '104' => 'onlysicbo',
            '105' => 'onlyniuniu',
            '107' => 'onlyfantan',
            '108' => 'onlysedie',
            '110' => 'onlyfishshrimpcrab',
            '128' => 'onlyandarbahar',
        ];
        return $arr[$key];
    }
}
