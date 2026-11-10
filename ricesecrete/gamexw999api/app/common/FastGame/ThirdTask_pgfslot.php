<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Game, TaskInterface, Third, ThirdTask, Utils};


//ThirdTask_pgfslot
//PGFSLOT
class ThirdTask_pgfslot extends ThirdTask implements TaskInterface
{


    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        $cfgvalues = $this->cfgvalues;
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
        // Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            // 已完成
            if ($data['data']['total_count'] < 2000) { // 最大2000
                // 页码归0
                $cfg[$gtag]['game_page'] = 0;
            } else {
                $cfg[$gtag]['game_page'] += 1;
            }
        }
        if ($upflag) {
            $this->updateApiConfigValue($func, $cfg);
        }
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        date_default_timezone_set('America/Sao_Paulo'); //转三方时区时间
        $data = $data['list'] ?: [];
        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code' => 'PGFSLOT']]);
        foreach ($data as $key => $v) {
            $gb_winprice = Third::transExchangerate($v['award_amount'] ?? 0, 0, 2);
            $gb_price = Third::transExchangerate($v['bet_amount'] ?? 0, 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['type'], 'tag', 'g_id');
            if(empty($gid)){
                continue;
            }
            //Stake (下注)
            //Payoff (派彩)
            //cancelStake（取消本注）
            //cancelPayoff（取消派彩)
            $op = 'n';
            if ($gb_winprice > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }
//            $gb_price = 0;
            $rd[] = [
                'gb_op' => $op,
                'gb_gid' => $gid,
                'gb_uname' => $v['user_id'],
                'gb_winlose' => $gb_winlose,
                'gb_overtime' => strtotime($v['award_ts']),
                'gb_price' => $gb_price,
                'gb_sn' => $v['term'],
                'gb_bettime' => strtotime($v['award_ts']),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['term']
            ];
        }
        // return [];
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
        date_default_timezone_set('America/Sao_Paulo'); //转三方时区时间
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
        // dump($cfg);
        // dump('=============================================================');
//        $this->cfg=Games::getConfigs(null,$this->trdid);
//        Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        $old_page = $cfg['game_page'] ?? 0; //查询上一次执行的分页

        if ($cfg['lastruntime'] == 0) { //初始化
            $start = (time() - 600); //前10分钟
            $endtime = ($thistime - 300); //前10分钟
        } else {
            if ($old_page > 0) {
                // 继续使用上次的时间
                $start = $cfg['start_time'];
                $endtime = $cfg['end_time'];
            } else {
                $start = $cfg['end']; //查询上一次执行的时间
                $endtime =$start;//前10分钟
                $start = ($start - 300); //前10分钟
            }
        }
        // 注：最大查询范围24 小时
        $flags = [
            "lastruntime" => $thistime,
            "end" => min($endtime + 300, $thistime), //当前执行时间 给下次执行用的
            "start_ts" => $start, //上次的执行时间减5分钟为这次的开始时间
            "end_ts" => $endtime,//上次的执行时间为这次的结束时间 注：最大查询范围24 小时
            "page" => $old_page,
            "size" => 2000, // 默认20，最大2000
        ];
        $config = $this->cfg;
        $flags['operator_token'] = $config['trdcfg']['tc_token'];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
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
