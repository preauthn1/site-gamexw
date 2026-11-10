<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\payment\PayRequest;
use app\common\service\MemberService;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_fc1 extends ThirdTask implements TaskInterface
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
        date_default_timezone_set(gettimezone("-4"));; //转三方时区时间 -4
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
        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
        }
        if ($upflag) $this->updateApiConfigValue($func,$cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){
        return $data ?? [];
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
        try {
            $gcfg = $this->cfg;
            Third::initExtClass(['trdcfg' => ['tc_code'=> 'FC1' ]]);
            // FC API数据时间的时区为UTC-4
            $gb_winprice = Third::transExchangerate($data['prize'],0,2);
            $gb_otherwin = Third::transExchangerate($data['winlose'],0,2);
            $gb_price = Third::transExchangerate($data['bet'],0,2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $data['gameID'], 'tag', 'g_id');
            return [
                'gb_gid' => $gid,
                'gb_uid' => MemberService::getUidByReCode($data['account']),
                'gb_winlose' => $gb_winprice > 0 ? 3 : 2,
                'gb_bettime' => strtotime($data['bdate']),
                'gb_overtime' => time(),
                'gb_price' => $gb_price,
                'gb_sn'=> $data['recordID'],
                'gb_winprice'=>$gb_winprice,
                'gb_otherwin'=>$gb_otherwin,
                'gb_validwater'=>$gb_winprice > $gb_price ? $gb_price : $gb_price - $gb_winprice,
            ];
        } catch (\Exception $e) {
            Utils::L("{$gcfg['trdcfg']['tc_id']}/markReportError",['markReport-error', $e->getMessage(), $e->getTraceAsString()]);
        }
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
        return [];
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
