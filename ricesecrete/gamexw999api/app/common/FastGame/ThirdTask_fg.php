<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_fg extends ThirdTask implements TaskInterface
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
        $func= $this->nowGetbetlistFunc;
        // $func='GETBETLIST';
        Utils::L('test',['准备保存API配置值','gcode'=>$gtag,'thistime'=>$this,'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag=false;
        if (isset($cfg[$gtag])) {
            $upflag=true;
            //业务代码开始
            if (isset($data['data']['data'])) {
                if (count($data['data']['data']) > 0) {
                    $cfg[$gtag]['id'] = $data['data']['data'][count($data['data']['data']) - 1]['id'];
                }
            }
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
        // dd($data['data']['data'], $cfg);
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
        Third::initExtClass(['trdcfg' => ['tc_code'=> 'FG' ]]);
        $rd = [];
        //业务代码开始
        $rd['gb_op'] = 'N';
        $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], $data['game_id'], 'tag','g_id');
        //之前对接过单钱包，用户名不能和之前相同，再后台api加了fg前缀，必须去掉
        $rd['gb_uname'] = str_replace('fg', '', $data['player_name']);
        $rd['gb_price'] = Third::transExchangerate($data['all_bets'] * 100,0, 2); //当有效下注金额不等于下注金额的时候 用有效下注金额
        $rd['gb_sn'] = $data['id'];
        $rd['gb_bettime'] = $data['time'];
        $rd['gb_overtime'] = $data['time'];
        //派彩金额=总派彩+jackpot奖金
        $winprice = Third::transExchangerate(($data['all_wins'] + $data['jackpot_bonus'])  * 100, 0, 2);
        if($winprice > 0){
            $winlose = 3;
        }else{
            $winlose = 2;
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
        $func = $this->nowGetbetlistFunc;
        $cfg = $this->getApiCfgvalues($func,$gtag);
        $id = $cfg['id'] ?? '0';
        //业务代码 设置非元数据中的字段值
        $flags = [
            "lastruntime" => $thistime,
            "end" => $thistime, //当前执行时间 给下次执行用的
            "id" => $id, //三方查询起始id
        ];
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
