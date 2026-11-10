<?php


namespace app\common\FastGame;


use app\model\Games;
use app\common\FastGame\Base\{Game, Http, Meta, Redis, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_pbty extends ThirdTask implements TaskInterface
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
    function setBetListStartFlag($gtag, $thistime, $data)
    {
        $func = 'GETBETLIST';
        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        $rd = [];
        //echo json_encode($rlt);die;
        if($rlt == null || empty($rlt)){
            return [];
        }
        foreach ($rlt as $key => $v) {
            if(!isset($v['winLoss'])){
                continue;
            }
            if ($v['winLoss'] > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }
            $win = bcadd($v['toRisk'],$v['winLoss'],2);
            Third::initExtClass(['trdcfg' => ['tc_code' => 'PBTY']]);
            $gb_winprice = Third::transExchangerate($win, 0, 2);
            $gbprice = Third::transExchangerate($v['toRisk'], 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['sportId'], 'tag', 'g_id');
            $rd[] = [
                'gb_op' => 'n',
                'gb_gid' => $gid?$gid:0,
                'gb_uname' => $v['loginId'],
                'gb_winlose' => $gb_winlose,
                'gb_overtime' => strtotime($v['settleDateFm']),
                'gb_price' => $gbprice,
                'gb_sn' => $v['wagerId'],
                'gb_bettime' => strtotime($v['wagerDateFm']),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['wagerId']
            ];
        }
        return empty($rd) ? [] : $rd;
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
        return $data;
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326 DBLIVE
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime)
    {
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
        if ($cfg['lastruntime'] == 0) { //初始化
            $start = time() - 600; //前10分钟
            $endtime = $thistime - 300; //前10分钟
        } else {
            //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
            //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
            //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
            $start = $cfg['end']?? $thistime; //查询上一次执行的时间
//            if ($start + 3600 <= $thistime) {//超过一个小时
//                $start = $thistime - 600; //一个小时以前
//                $endtime = $thistime - 300;
//            } else {
            $endtime = $start;
            $start = $start - 300; //前5分钟
//            }
        }
        $flags = [
            "lastruntime" => $thistime,
            "end" => min($endtime + 240, $thistime),
            "startTime" =>date('Y-m-d H:i:s', $start),
            "endTime" =>date('Y-m-d H:i:s', $endtime),
        ];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
    }

    /**
     * 合并订单数据
     * @param $data
     * @return array
     */
    function combinData($data)
    {
        $data_temp = [];
        foreach ($data as $key => $v) {
            if (!isset($data_temp[$v['parent_bet_id']])) {
                $data_temp[$v['parent_bet_id']] = [];
            }
            if ($v['trans_type'] == 'Stake') {
                //投注
                $data_temp[$v['parent_bet_id']]['traceId'] = $v['traceId'];
                $data_temp[$v['parent_bet_id']]['player_name'] = $v['player_name'];
                $data_temp[$v['parent_bet_id']]['game_code'] = $v['game_code'];
                $data_temp[$v['parent_bet_id']]['bet_amount'] = $v['bet_amount'];
                $data_temp[$v['parent_bet_id']]['bet_time'] = $v['created_at'] / 1000;
                if (!isset($data_temp[$v['parent_bet_id']]['trans_type'])) {
                    $data_temp[$v['parent_bet_id']]['trans_type'] = "Stake";
                }
            }
            if ($v['trans_type'] == 'Payoff') {
                //结算
                $data_temp[$v['parent_bet_id']]['win_amount'] = floatval($v['win_amount']);
                $data_temp[$v['parent_bet_id']]['over_time'] = $v['created_at'] / 1000;
                $data_temp[$v['parent_bet_id']]['trans_type'] = "Payoff";
            }
        }
        return $data_temp;
    }

    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid, $type)
    {
        //业务代码开始
        parent::transferBets($gid, $type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
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
