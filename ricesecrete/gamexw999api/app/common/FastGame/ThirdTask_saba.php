<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;
//use app\model\{GameBets};
use think\facade\Db;

class ThirdTask_saba extends ThirdTask implements TaskInterface
{

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        $cfgvalues=$this->cfgvalues;
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
        Utils::L('test',['准备保存API配置值', 'gcode'=>$gtag, 'thistime'=>$this, 'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]),true);
        //$cfg = $this->getApiCfgvalues($func, $gtag);;
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            $cfg[$gtag]['version_key'] = $data['Data']['last_version_key'];
        }else{
            $upflag = true;
            $cfg[$gtag]['version_key'] = $data['Data']['last_version_key'];
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
        return $data;
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){

        Third::initExtClass(['trdcfg' => ['tc_code'=> 'SABA' ]]);
        if(!isset($data[0])){
            return [];
        }
        foreach ($data as $v) {
            $uname = explode('_',$v['vendor_member_id']);
            $gb_price = Third::transExchangerate($v['stake'],0);//投注金额
            $winlost_amount = Third::transExchangerate($v['winlost_amount'],0);//输赢金额

            if($winlost_amount >= 0){
                $gb_winprice = $winlost_amount;
            }else{
                $gb_winprice = $gb_price + $winlost_amount;
            }


            if(in_array($v['ticket_status'],['refund','reject'])){

                $gb_winlose = 0;

            }elseif(in_array($v['ticket_status'],['waiting','running'])){//waiting,

                $gb_winlose = 1;

            }elseif($gb_winprice > $gb_price){//赢

                $gb_winlose = 3;

            }elseif($gb_winprice < $gb_price){//赢

                $gb_winlose = 2;

            }else{

                $gb_winlose = 0;

            }

            //判断注单是否存在或已经结算
            $betId = $v['trans_id'];
            //$bet = GameBets::where('gb_sn', '=', $betId)->find();
            $bet = Db::table('dq_game_bets')->where('gb_sn', $betId)->field('*')->find();

            if($bet){//如果存在且已经结算，把状态改为未结算

                Db::table('dq_game_bets')->where('gb_sn', $betId)->update(['gb_winlose' => 1]);
                $op = 'u';
                $gb_price = 0;
                if($bet['gb_winprice'] != $gb_winprice){//说明结算金额有变动
                    $gb_winprice = $gb_winprice - $bet['gb_winprice'];
                }

            }else{
                $op = 'c';
            }

            $rd[] = [
                'gb_op' => $op,
                'gb_gid'=>Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['sport_type'], 'tag', 'g_id')??0,
                'gb_uname'=> $uname[1],
                'gb_winlose'=>$gb_winlose,
                'gb_bettime'=> strtotime($v['transaction_time']),
                'gb_overtime'=>$v['settlement_time']?strtotime($v['settlement_time']):null,
                'gb_price'=>$gb_price,
                'gb_sn'=> $betId,
                'gb_winprice'=>$gb_winprice,
            ];
        }//echo '<pre>';print_r($rd);die;
        return empty($data) ? []:$rd;
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
        $func = 'GETBETLIST';

        $cfg = $this->getApiCfgvalues($func, $gtag);
        $version_key = $cfg['version_key'] ?? 0; //查询上一次执行的分页
        //echo json_encode($cfg);die;
        $flags = [
            "lastruntime" => $thistime,
            "version_key" =>$version_key, //版本号
        ];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        return $flags;
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
