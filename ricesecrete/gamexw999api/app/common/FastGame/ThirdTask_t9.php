<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Game, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_t9 extends ThirdTask implements TaskInterface
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
//        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            if(count($data['Data'])>0){
                //OrderStatus
                //1已派彩
                //2待結算
                //3不結算
                //TODO 该位置必须循环判断，出现未结算时打断，否则下次就会跳过该订单
                $serialNumber = 0;
                foreach ($data['Data'] as $value) {
                    if( $value['OrderStatus'] == 2 ){
                        break;
                    }
                    $serialNumber = $value['SerialNumber'];
                }
                $cfg[$gtag]['SerialNumber'] = $serialNumber;
//                $cfg[$gtag]['SerialNumber'] = $data['Data'][count($data['Data'])-1]['SerialNumber'];
            }
        }
        $this->nowGetbetlistFunc = $func;
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        //TODO 因为这个请求三方时要求0时区请求，所以不能在exec中设置时区
        date_default_timezone_set('UTC'); //转三方时区时间
        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code' => 'T9']]);
        foreach ($data as $key=>$v) {
            $gb_winprice = Third::transExchangerate($v['WinAmount'] + $v['BetAmount'], 0, 2);
            $gb_price = Third::transExchangerate($v['BetAmount'], 0, 2);
            $gb_winlose = 1;
            //1已派彩
            //2待結算
            //3不結算
            if ($v['OrderStatus'] == 1) {
                if ($v['WinAmount'] >= 0) {
                    $gb_winlose = 3;
                } else {
                    $gb_winlose = 2;
                }
                $op = 'n';
            }elseif ( $v['OrderStatus'] == 2 ){
                $gb_winlose = 1;
                $gb_winprice = 0;
                $op = 'c';
            }else{
                $gb_winlose = 0;
                $gb_price = 0;
                $gb_winprice = 0;
                $op = 'u';
            }
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['TableId'], 'tag', 'g_id');
            $rd[] = [
                'gb_op' => $op,
                'gb_gid' => $gid,
                'gb_uname' => $v['MemberAccount'],
                'gb_winlose' => $gb_winlose,
                'gb_overtime' => strtotime($v['PayoutTime']),
                'gb_price' => $gb_price,
                'gb_sn' => $v['OrderNumber'],
                'gb_bettime' => strtotime($v['BetTime']),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['SerialNumber']
            ];
        }
        //转转回项目时区
        date_default_timezone_set(env('DEFAULT_TIMEZONE', 'Asia/Shanghai')); //转三方时区时间
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
    function getBetListStartFlag($gtag, $thistime)
    {
        $func = 'GETBETLIST';
        $config = $this->cfg;
        $AgentId = $config['trdcfg']['tc_tag'];
        $RequestTime = date("YmdHis");
        $Sign = strtolower(md5($config['trdcfg']['tc_tag'] . "&" . $RequestTime . "&" . $config['trdcfg']['tc_api_signkey']));
        $cfg = $this->getApiCfgvalues($func, $gtag);
        if (isset($cfg['SerialNumber'])) {
            $SerialNumber = $cfg['SerialNumber'];
        } else {
            $SerialNumber = 0;
        }
        $flags = [
            "AgentId" => $AgentId,
            "RequestTime" => $RequestTime,
            "Sign" => $Sign,
            "SerialNumber" => $SerialNumber
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
