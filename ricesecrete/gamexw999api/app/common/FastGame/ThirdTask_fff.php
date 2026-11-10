<?php


namespace app\common\FastGame;


use app\model\Games;
use app\common\FastGame\Base\{Game, Http, Meta, Redis, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_fff extends ThirdTask implements TaskInterface
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
            if (!isset($cfg[$gtag]['page']) || $data['data']['total'] <= $cfg[$gtag]['page']) {
                // 页码归0
                $cfg[$gtag]['page'] = 0;
            }
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        Third::initExtClass(['trdcfg' => ['tc_code' => 'FFF']]);
        $rd = [];

        foreach ($data as $key => $v) {
            $name = explode('_',$v['username']);
            if (!isset($name[1])) {
                continue;
            }

            $m = $v['bonus_amount'] - $v['amount'];
            if ($m > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }

            $gb_winprice = Third::transExchangerate($v['bonus_amount'], 0, 2);
            $gbprice = Third::transExchangerate($v['amount'], 0, 2);
            //FFF由于没有具体游戏，故这里写死
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], 'FFF', 'tag', 'g_id');
            $rd[] = [

                'gb_op' => 'n',
                'gb_gid' => $gid,
                'gb_uname' => $name[1],
                'gb_winlose' => $v['status'] >= 0 ? $gb_winlose : 0,
                'gb_overtime' => strtotime($v['bonus_time']),
                'gb_price' => $gbprice,
                'gb_sn' => $v['id'],
                'gb_bettime' => strtotime($v['create_time']),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['period_number']
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
        $config = $this->cfg;

        $cfg = $this->getApiCfgvalues($func, $gtag);
//        $this->cfg=Games::getConfigs(null,$this->trdid);
//        Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        $old_page = $cfg['page'] ?? 0; //查询上一次执行的分页

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
                $start = $cfg['start_time'];
                $endtime = $cfg['end_time'];
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
            "start_time" => date('Y-m-d H:i:s',$start), //上次的执行时间减5分钟为这次的开始时间
            "end_time" => date('Y-m-d H:i:s',$endtime), //上次的执行时间为这次的结束时间
            'page' => $old_page,
            'limit' => 5000
        ];
        //echo json_encode($flags);die;
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
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
