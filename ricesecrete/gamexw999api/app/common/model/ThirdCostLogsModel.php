<?php
namespace app\common\model;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Utils;
use think\exception\DbException;
use think\facade\Db;
use think\Model;
class ThirdCostLogsModel extends Model
{
    protected $name = 'third_cost_logs';

    protected $pk = 'tcl_id';

    /**
     CREATE TABLE `dq_third_cost_logs` (
    `tcl_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `tcl_intime` int(11) DEFAULT NULL,
    `tcl_old` decimal(20,2) DEFAULT NULL,
    `tcl_change` decimal(20,2) DEFAULT NULL,
    `tcl_new` decimal(20,2) DEFAULT NULL,
    `tcl_type` int(255) DEFAULT '0' COMMENT '0 节点 1上分 2月清',
    `tcl_opstaff` varchar(64) DEFAULT NULL COMMENT '操作人[上分比例]',
    PRIMARY KEY (`tcl_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='三方平台服务费分值变更记录表';
     *
     *
    CREATE TABLE `dq_third_cost_bettemp` (
    `tct_gbid` int(11) NOT NULL,
    `tct_bettime` int(11) DEFAULT '0',
    `tct_betprice` decimal(20,2) DEFAULT '0.00',
    `tct_winprice` decimal(20,2) DEFAULT '0.00',
    `tct_overtime` int(11) DEFAULT NULL,
    PRIMARY KEY (`tct_gbid`),
    KEY `tct_overtime` (`tct_overtime`),
    KEY `tct_bettime` (`tct_bettime`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='三方平台服务费注单增分统计临时表';
     *
     * env add:
     ;三方平台服务费控制相关 下注扣分（实时），结算赢加分（仅本月），月清，手动上分
    [THIRDCOST]
    ;分值转换比率 实际金额除以此值转换为分值
    RATE=0.05
    ;x天未结算的注单不纳入统计（即不返还分值）
    DROP_BET_TIME=30
    ;允许操作人ID 仅指定操作人可以在后台看到相半操作页面，多人使用,隔开
    ALLOW_OP_STAFFID=0,0
    ;单次统计注单数（设置值要大于分钟产生的注单数）
    STEP_NUM=50000
     *
     *
     * 如果是生产环境升级，又不想统计老的数据，则
     * insert into dq_third_cost_bettemp values (超始注单gb_id-1,0,0,0,null)
     * 注：不执行也不影响，但是会导致任务直到跑到本月gb_id之后才会真正开始统计
     */

    public static function getRate(){
        return env('THIRDCOST.RATE',0.05);
    }

    /**
     *  获取三方服务费分值余额
     * @return number
     */
    public static function getCost(){
        $cost=Redis::get('thirdCost');
        Utils::L('ThirdCostLogs',["redis读取",$cost]);
        if (!$cost){
            //初始化
            $cost=self::order('tcl_id','desc')->limit(1)->value('tcl_new');
            Utils::L('ThirdCostLogs',["数据库读取",$cost]);
            if (!$cost) $cost=0;

            Redis::set('thirdCost',$cost);
        }
        Utils::L('ThirdCostLogs',["get处理",$cost]);
        return $cost;
    }

    /**
     * 获取当前分值换算的钱余额
     * @return float
     */
    public static function getMoney(){
        return self::transToCost(self::getCost(),1);
    }

    /**
     * 金额转换为分值
     * @param $number 钱 或 分
     * @param int $type 0钱转分 1分转钱
     * @return float
     */
    public static function transToCost($number,$type=0){
        $rate=self::getRate();
        return floatval(number_format($type==0?$number/$rate:$number*$rate,2,'.',''));
    }

    /** 写分值变更记录，任务专用 管理业务中不要直接调用(不更新缓存)
     * @return void
     */
    public static function writeLog(){
        $newcost=self::getCost();
        $old=self::order('tcl_id','desc')->limit(1)->value('tcl_new');
        if (!$old) $old=0;
        try {
            self::create([
                'tcl_intime' => time(),
                'tcl_old' => $old,
                'tcl_change' => $newcost - $old,
                'tcl_new' => $newcost,
                'tcl_type' => 0
            ]);
        }catch (\Exception $ex){
            Utils::L('ThirdCostLogs',["==========ERROR==========","写日志错误",$ex->getMessage()]);
        }
    }

    /** 上分操作业务
     * @param $price 上分金额
     * @param $staff 操作人
     * @param $monthClear 是否月清上分 ，月清上分会产生两条记录
     * @return bool
     */
    public static function upCost($price,$opstaff,$monthClear=false){
        $old=self::getCost();
        Utils::L('ThirdCostLogs',["读旧分值",$old]);
        $new=self::transToCost($price)+($monthClear?0:$old);
        Utils::L('ThirdCostLogs',["算新分值",$old,$new]);
        $rate=self::getRate();
        $logs=[];
        Db::startTrans();
        try {
            if ($monthClear) {
                array_push($logs, [
                    'tcl_intime' => time(),
                    'tcl_old' => $old,
                    'tcl_change' => $old * -1,
                    'tcl_new' => 0,
                    'tcl_type' => 2,
                    'tcl_opstaff' => $opstaff
                ]);
                $old = 0;
            }
            array_push($logs, [
                'tcl_intime' => time(),
                'tcl_old' => $old,
                'tcl_change' => $new - $old,
                'tcl_new' => $new,
                'tcl_type' => 1,
                'tcl_opstaff' => $opstaff . '[' . $rate . ']'
            ]);
            (new ThirdCostLogsModel())->saveAll($logs);
            Redis::set('thirdCost', $new);
            Utils::L('ThirdCostLogs',["设置新分值",$old,$new]);
            Db::commit();
            return true;
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('ThirdCostLogs',["==========ERROR==========","上分错误",['price'=>$price,'opstaff'=>$opstaff,'monthclear'=>$monthClear],$ex->getMessage()]);
            return false;
        }
    }

    /**
     *  获取分值变更记录
     * @param $type all全部 0节点 1上分 2月清
     * @param $page
     * @param $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getlist($type,$page,$limit){
        if ($type=='all'){
            $rlt=self::order('tcl_id','desc')->limit(($page-1)*$limit,$limit)->select();
            $total=self::count();
        }else{
            $rlt=self::where('tcl_type',$type)->order('tcl_id','desc')->limit(($page-1)*$limit,$limit)->select();
            $total=self::where('tcl_type',$type)->count();
        }
        foreach ($rlt as &$v){
            $v['tcl_intime'] = date('Y-m-d H:i:s', $v['tcl_intime']);
        }
        return [
            'code'=>1,
            'page'=>$page,
            'limit'=>$limit,
            'total'=>$total,
            'data'=>$rlt

        ];
    }

    /** 分钟任务，用于统计三方服务费分值
     * 投注扣，赢注加
     * @return void
     */
    public static function thirdCostTask(){
        try {
            $step=env("THIRDCOST.STEP_NUM",50000);
            $limittime=env("THIRDCOST.DROP_BET_TIME",30);
            $limittime=time()-$limittime*86400;
            $mstime=strtotime(date("Y-m-01 00:00:00"));//本月第一天时间戳
            Utils::L('ThirdCostLogs',["开始统计"]);
            //获取待统计起始ID(临时表中有数据从临时表拿，否则0)
            $lastid=Db::query("select max(tct_gbid) as num from dq_third_cost_bettemp");
            if (!empty($lastid) && is_numeric($lastid[0]['num'])){
                $lastid=$lastid[0]['num'];
            }else{
                $lastid=0;
            }
            Utils::L('ThirdCostLogs',["起始注单ID",$lastid]);
            //删除已处理的结算数据
            $sql="delete from dq_third_cost_bettemp where tct_overtime>0 or tct_bettime<{$limittime}";
            $rlt=Db::execute($sql);
            Utils::L('ThirdCostLogs',["删除历史已结算或失效数据",$sql,$rlt]);
            //更新历史结算数据
            $sql="update dq_third_cost_bettemp
left join dq_game_bets on gb_id=tct_gbid and gb_overtime>0
set tct_winprice=gb_winprice,tct_overtime=gb_overtime
where tct_overtime is null and gb_id is not null";
            $rlt=Db::execute($sql);
            $update=$rlt;
            Utils::L('ThirdCostLogs',["更新历史结算数据",$sql,$rlt]);
            //转移新注单数据
            $sql="insert into dq_third_cost_bettemp
select gb_id,gb_bettime,gb_price,gb_winprice,gb_overtime
from dq_game_bets
where gb_id>{$lastid}
order by gb_id
limit {$step}";
            $rlt=Db::execute($sql);
            $add=$rlt;
            Utils::L('ThirdCostLogs',["转移新注单数据",$sql,$rlt]);
            //没有新注单数据，且原最后记录又已不存在的情况下，回写一个占位记录
            if ($add==0 && $lastid>0) {
                Utils::L('ThirdCostLogs',["不存在新注单，准备回写占位符",$lastid]);
                $flag=Db::query("select tct_gbid from dq_third_cost_bettemp where tct_gbid={$lastid}");
                if (empty($flag) || empty($flag[0]['tct_gbid'])) {
                    $sql = "insert into dq_third_cost_bettemp values ({$lastid},0,0,0,1);";
                    $flag = Db::execute($sql);
                    Utils::L('ThirdCostLogs', ["不存在新注单回写占位符", $sql, $flag]);
                }else{
                    Utils::L('ThirdCostLogs', ["原注单存在，不需要回写点位符"]);
                }
            }
            //统计投注
            $bet=0;
            if ($add>0) {
                Utils::L('ThirdCostLogs', ["有新投注记录", $add]);
                $sql = "select sum(tct_betprice) as price from dq_third_cost_bettemp where tct_gbid>{$lastid} and tct_bettime>={$mstime}";
                $rlt = Db::query($sql);
                if (!empty($rlt) && !empty($rlt[0]['price'])){
                    $bet=$rlt[0]['price'];
                }
                Utils::L('ThirdCostLogs', ["统计投注", $sql, $rlt]);
            }
            //统计结算
            $win=0;
            if ($add>0 || $update>0) {
                Utils::L('ThirdCostLogs', ["有新投注记录或历史结算记录", $add,$update]);
                $sql = "select sum(tct_winprice) as price from dq_third_cost_bettemp where tct_overtime>={$mstime}";
                $rlt = Db::query($sql);
                if (!empty($rlt) && !empty($rlt[0]['price'])){
                    $win=$rlt[0]['price'];
                }
                Utils::L('ThirdCostLogs', ["统计结算", $sql, $rlt]);
            }
            //写日志，更新缓存
            if ($bet>0 || $win>0) {
                $cost = self::getCost();
                $newcost = $cost - $bet + $win;
                Utils::L('ThirdCostLogs', ["计算分值", 'old' => $cost, 'sumbet' => $bet, 'sumwin' => $win, 'new' => $newcost]);
                Redis::set('thirdCost', $newcost);
                self::writeLog();
            }
        }catch (\Exception $ex){
            Utils::L('ThirdCostLogs',["==========ERROR==========","统计任务出错",$ex->getMessage()]);
        }
    }

}