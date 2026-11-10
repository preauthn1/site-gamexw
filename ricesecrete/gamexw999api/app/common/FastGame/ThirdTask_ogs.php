<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Game, Http, Meta, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_ogs extends ThirdTask implements TaskInterface
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
            if (isset($data['records']) && $data['records']) {
                //transaction_type 交易类型
                //debit 下注
                //credit 派彩
                //rollback 回滚
                //TODO 该位置必须循环判断，出现未结算时打断，否则下次就会跳过该订单
                $fetchId = 0;
                foreach ($data['records'] as $value) {
                    if( $value['transaction_type'] == 'debit' ){
                        break;
                    }
                    $fetchId = $value['fetch_id'];
                }
                $cfg[$gtag]['fetch_id'] = $fetchId;
            }
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        Third::initExtClass(['trdcfg' => ['tc_code' => 'OGS']]);
        $rd = [];
        foreach ($data as $key => $v) {
            if (strpos($v['player_id'], 'togs_') === 0) {
                $gb_winprice = Third::transExchangerate($v['credit_amount'], 0, 2);
                $gb_price = Third::transExchangerate($v['debit_amount'], 0, 2);
                //transaction_type 交易类型
                //debit 下注
                //credit 派彩
                //rollback 回滚
                if( $v['transaction_type'] == 'debit' ){
                    $gb_winlose = 1;
                    $gb_winprice = 0;
                    $op = 'c';
                }elseif ( $v['transaction_type'] == 'credit' ){
                    if( $gb_winprice > 0 ) {
                        $gb_winlose = 3;
                    }else{
                        $gb_winlose = 2;
                    }
                    $op = 'n';
                }else{
                    $gb_winlose = 0;
                    $gb_winprice = 0;
                    $gb_price = 0;
                    $op = 'u';
                }
                $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['game_id'], 'tag', 'g_id');
                $rd[] = [
                    'gb_op' => $op,
                    'gb_gid' => $gid,
                    'gb_uname' => str_replace('togs_', '', $v['player_id']),
                    'gb_winlose' => $gb_winlose,
                    'gb_overtime' => $v['credit_at'],
                    'gb_price' => $gb_price,
                    'gb_sn' => $v['fetch_id'],
                    'gb_bettime' => $v['debit_at'],
                    'gb_winprice' => $gb_winprice,
                    'gb_cusfield' => $v['fetch_id']
                ];
            }
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
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime)
    {
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
        if (isset($cfg['fetch_id'])) {
            $fetch_id = $cfg['fetch_id'];
        } else {
            $fetch_id = 1;
        }
        $limit = 10000;
        $flags = [
            "limit" => $limit,
            'fetch_id' => $fetch_id,
        ];
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
