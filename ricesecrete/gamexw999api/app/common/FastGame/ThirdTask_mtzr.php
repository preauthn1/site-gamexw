<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_mtzr extends ThirdTask implements TaskInterface {

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
        $func = 'GETBETLIST';
        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);

        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            // 已完成
            if ($data['data']['total_page'] <= $cfg[$gtag]['page']) {
                // 页码归0
                $cfg[$gtag]['page'] = 0;
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
        $func = 'GETBETLIST';
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
            "end_time" => is_numeric($endtime) ? date('Y-m-d H:i:s', $endtime) : $endtime,
            "start_time" => is_numeric($start) ? date('Y-m-d H:i:s', $start) : $start, //上次的执行时间减5分钟为这次的开始时间
            'page' => $old_page + 1,
            'page_size' => 100,
        ];
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

        if(empty($data)){
            return null;
        }
        Third::initExtClass(['trdcfg' => ['tc_code' => 'MTZR']]);
        $rd = [];
        //业务代码开始
        $rd['gb_op'] = 'n'; //等于N的时候 就是非重对 Y是重对 重对就是修改
        $rd['gb_gid'] = Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'], $data['game_code'], 'tag', 'g_id')??0;
        $rd['gb_uname'] = $data['user_id'];
        $rd['gb_price'] = Third::transExchangerate($data['order_money'], 0, 2); //当有效下注金额不等于下注金额的时候 用有效下注金额
        $rd['gb_sn'] = $data['sn'];
        $rd['gb_bettime'] = strtotime($data['order_time']);
        $rd['gb_overtime'] = $data['settle_time'] ? strtotime($data['settle_time']):null;
        $win = Third::transExchangerate($data['win_money'], 0, 2);
        $rd['gb_winlose'] = $win >= 0 ? 3 : 2; //输赢金额大于0 就是赢 否则就是输
        $rd['gb_winprice'] = $win;

        return $rd;
    }


    function procBetDataPre($gcfg, $data, $rlt) {
        return $data ?: [];
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
