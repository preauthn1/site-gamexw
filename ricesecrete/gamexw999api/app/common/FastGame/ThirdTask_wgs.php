<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_wgs extends ThirdTask implements TaskInterface
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
        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code'=> 'WGS' ]]);
        foreach ($data as $v) {
            $gb_price = Third::transExchangerate($v['gold'],0);//投注金额
            $gb_winprice = Third::transExchangerate($v['gold'] + $v['result'], 0, 2);//输赢金额
            //注单状态 1=有效注单 其他都为注销的订单
            if( $v['status'] == 1 ){
//                1-未派彩、其他都為已派彩
//                2-贏
//                3-輸
//                4-和局、註銷、未開賽等無效單
                if( $v['pstatus'] == 1 ){
                    $gb_winlose = 1;
                    $gb_winprice = 0;
                    $op = 'c';
                }elseif ( in_array($v['pstatus'], [2,3]) ){
                    if( $gb_winprice > 0 ){
                        $gb_winlose = 3;
                    }else{
                        $gb_winlose = 2;
                    }
                    $op = 'n';
                }else{
                    $gb_winlose = 0;
                    $op = 'u';
                }
            }else{
                $gb_winlose = 0;
                $gb_winprice = 0;
                $gb_price = 0;
                $op = '';
            }
            $rd[] = [
                'gb_op' => $op,
                'gb_gid'=>Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['gtype'], 'tag', 'g_id'),
                'gb_uname'=> preg_replace('/\D/', '', $v['username']),
                'gb_winlose'=>$gb_winlose,
                'gb_bettime'=> strtotime($v['ins_time']),
                'gb_overtime'=>strtotime($v['updated_at']),
                'gb_price'=>$gb_price,
                'gb_sn'=> $v['id'],
                'gb_winprice'=>$gb_winprice,
            ];
        }
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
        $cfg = $this->getApiCfgvalues($func,$gtag);
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
            "sdate" =>date('Y-m-d H:i:s', $start), //前2小时
            "edate" => date('Y-m-d H:i:s', $endtime), //当前时间
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
