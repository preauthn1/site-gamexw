<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\TaskInterface;
use app\common\FastGame\Base\ThirdTask;
use app\common\FastGame\Base\Utils;
use app\model\ThirdConfigs;

class ThirdTask_kyrianstg extends ThirdTask implements TaskInterface
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
        $func='GETBETLIST';
        Utils::L('test',['准备保存API配置值','gcode'=>$gtag,'thistime'=>$this,'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag=false;
        if (isset($cfg[$gtag])) {
            $upflag=true;
            //业务代码开始
            if (isset($data['detail']) && isset($data['detail']['game_history']) && is_array($data['detail']['game_history'])) {
                $cnt=count($data['detail']['game_history']);

                $cfg[$gtag]['list_page'] = 1;//恢复page值
                if ($cnt>0) {
                    $cfg[$gtag]['list_to'] = Date("YmdHis", $data['detail']['game_history'][$cnt - 1]['bet_time'] - 1);//记录下最后读取记录的时间值-1秒
                }
            }
        //业务代码结束
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
        $rd = [];
        //业务代码开始
        $rd['gb_uname']=$data['username'];
        $rd['gb_price']=$data['bet_amount'];
        $rd['gb_sn']=$data['uniqueid'];
        $rd['gb_winlose']=$data['status']==5?($data['payout_amount']>0?3:2):1;
        $rd['gb_bettime']=$data['bet_time'];
        $rd['gb_overtime']=$data['payout_time'];
        $rd['gb_winprice']=$data['payout_amount'];
        //业务代码结束
        return $rd;
    }

    function timeTransToTs($t){
        if (is_string($t) && strlen($t)==14){
            $str=substr($t,0,4)."-".
                substr($t,4,2)."-".
                substr($t,6,2)." ".
                substr($t,8,2).":".
                substr($t,10,2).":".
                substr($t,12,2);
            return strtotime($str);
        }else{
            return $t;
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
        $ts=time();
        $func='GETBETLIST';
        $cfg=$this->getApiCfgvalues($func,$gtag);
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime']==0){ //初始化
            $flags=[
                "lastruntime"=>$thistime,
                "curtime"=>$ts,
                "list_gtag"=>$gtag,
                "list_from"=>Date("YmdHis",$thistime-86400*30),//默认查询一月的数据，本游戏仅支持查询一个月的数据
                "list_to"=>Date("YmdHis",$thistime-5),
                "list_page"=>1
            ];

        }else{
            $start=$this->timeTransToTs($cfg['list_to'])+1;//查询上一次结尾的下一秒开始数据
            if ($start+86400*30<=$ts){//超过一个月
                $start=$ts-86400*30;
            }
            if ($cfg['lastruntime']!=$thistime){//获取第一页数据
                $flags=[
                    "lastruntime"=>$thistime,
                    "curtime"=>$ts,
                    "list_gtag"=>$gtag,
                    "list_from"=>Date("YmdHis",$start),
                    "list_to"=>Date("YmdHis",$thistime-5),
                    "list_page"=>1
                ];
            }else{//获取其它页数据
                $flags=[
                    "curtime"=>$ts,
                    "list_page"=>$cfg['list_page']+1
                ];
            }
        }
        //特别说明：因缓存数据不会被即时更新，因此，如果在一轮业务中需要多次执行此方法获取列表数据时，要注意更新缓存中的值
        //各三方平台对接时也请注意
        $this->apiscfgvalues[$func][$gtag]=array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        //业务代码结束
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
