<?php


namespace app\common\FastGame;


use app\common\model\GameBetsModel;
use app\model\Games;
use app\common\FastGame\Base\{Game, Http, Meta, Redis, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_dbsport extends ThirdTask implements TaskInterface
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
        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            // 已完成
            if ($data['data']['page_count'] <= $cfg[$gtag]['page']) {
                // 页码归0
                $cfg[$gtag]['page'] = 0;
            }
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code' => 'DBSPORT']]);
        foreach ($data as $key => $v) {
            $gb_winprice = Third::transExchangerate($v['win_amount']??0, 0, 2);
            $gb_price = Third::transExchangerate($v['bet_amount']??0, 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['game_code'], 'tag', 'g_id');
            //Stake (下注)
            //Payoff (派彩)
            //cancelStake（取消本注）
            //cancelPayoff（取消派彩)
            if($v['trans_type'] == 'Stake'){
                $gb_winlose = 1;
                $op = 'c';
            }else if ($v['trans_type'] == 'Payoff') {
                if ($gb_winprice > 0) {
                    $gb_winlose = 3;
                } else {
                    $gb_winlose = 2;
                }
                $op = 'u';
                $gb_price = 0;
            } else {
                $gb_price = 0;
                $gb_winlose = 0;
                $gb_winprice = 0;
                $op = 'u';
            }
            $rd[] = [
                'gb_op' => $op,
                'gb_gid' => $gid,
                'gb_uname' => $v['player_name'],
                'gb_winlose' => $gb_winlose,
                'gb_overtime' => intval($v['created_at'] / 1000),
                'gb_price' => $gb_price,
                'gb_sn' => $v['bet_id'],
                'gb_bettime' => intval($v['created_at'] / 1000),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['bet_id']
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
//        $this->cfg=Games::getConfigs(null,$this->trdid);
//        Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        $old_page = $cfg['page'] ?? 0; //查询上一次执行的分页

        if ($cfg['lastruntime'] == 0) { //初始化
            $start = (time() - 600) * 1000; //前10分钟
            $endtime = ($thistime - 300) * 1000; //前10分钟
        } else {
            if ($old_page > 0) {
                // 继续使用上次的时间
                $start = $cfg['start_time'];
                $endtime = $cfg['end_time'];
            } else {
                //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
                //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
                //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
                $start = $cfg['end']; //查询上一次执行的时间
//                if ($start + 3600 <= $thistime) {//超过一个小时
//                    //DB的查询时间只能五分钟
//                    $start = ($thistime - 600) * 1000; //一个小时以前
//                    $endtime = ($thistime - 300) * 1000;
//                } else {
                    $endtime = $start * 1000;
                    $start = ($start - 300) * 1000; //前5分钟
//                }
            }
        }
        $flags = [
            "lastruntime" => $thistime,
            "end" => min($endtime / 1000 + 240, $thistime - 60), //当前执行时间 给下次执行用的,必须大于当前时间60秒
            "start_time" => intval($start), //上次的执行时间减5分钟为这次的开始时间
            "end_time" => intval($endtime), //上次的执行时间为这次的结束时间
            'page' => $old_page + 1,
            'page_size' => 5000
        ];
        $config = $this->cfg;
        $vendor_code = $config['trdcfg']['tc_api_signkey'];
        $vendor_code_str = "";
        if (strpos($vendor_code, '_') >= 0) {
            $vendor_code_arr = explode('_', $vendor_code);
            $vendor_code_str = strtoupper($vendor_code_arr[1]);
        }
//        $flags['start_time'] = 1716373990000;
//        $flags['end_time'] = 1716374290000;
        $flags['vendor_code'] = $vendor_code_str;
        $flags['secret_key'] = $config['trdcfg']['tc_api_prikey'];
        $flags['operator_token'] = $config['trdcfg']['tc_tag'];
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
