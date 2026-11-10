<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_aecricket extends ThirdTask implements TaskInterface
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
            /*//TODO 该地方用来处理分页数据 这次请求完后 下次请求page更新为1 读取上一次请求的最后时间 重置为下次请求的时间
            if(is_array($data['data']) && count($data['data']) > 0) {
                $end = ($cfg[$gtag]['end'] / 1000) + 180;
                $t = strtotime(date('Y-m-d H:i'.':00', $end)) * 1000;
            }
            $cfg[$gtag]['end'] = $t;*/
        }
        if ($upflag) $this->updateApiConfigValue($func,$cfg);
    }


    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){
        $rd = [];

        $gb_winlose = 1;
        foreach ($data as $e) {
            foreach ($e['trade_detail'] as $k => $v) {
                $profit = trim($v['profit'],'-');
                $gb_winprice = $profit;
                if ($e['status'] == 'OPEN') {
                    $gb_winlose = 1;
                } else if ($e['status'] == 'SETTLE' && $v['profit'] > 0) {
                    $gb_winlose = 3;
                    $gb_winprice = $v['place_order_quantity'] + $profit;
                } else if ($e['status'] == 'SETTLE' && $v['profit'] <= 0) {
                    $gb_winlose = 2;
                }else{
                    $gb_winlose = 0;
                }
                $rd[] = [
                    'gb_op' => 'n',
                    'gb_gid'=>Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], 'aecricket', 'code', 'g_id'),
                    'gb_uname'=>$e['player_id'],
                    'gb_winlose'=>$gb_winlose,
                    'gb_overtime'=>empty($e['settle_order_dt']) ? '' : strtotime($e['settle_order_dt']),
                    'gb_price'=>$v['place_order_quantity'],
                    'gb_sn'=> $v['place_order_id'],
                    'gb_bettime'=>strtotime($v['place_order_dt']),
                    'gb_winprice'=>$gb_winprice,
                ];
            }
        }
        return empty($data) ? []:$rd;
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
