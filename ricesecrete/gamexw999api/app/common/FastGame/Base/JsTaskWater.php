<?php


namespace app\common\FastGame\Base;


use app\api\redis\ApiRedis;
use app\model\GameApis;
use app\model\GameBetsTemp;
use app\model\GameConfigs;
use app\model\Games;
use app\model\SysSets;
use app\model\ThirdConfigs;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Util;
use think\facade\Db;

/**
 * 三方平台统一结算任务(用以支持用户代码按流水分佣，内部代理按原方案不动)
 * Class JsTask
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-09-17 12:22
 */
abstract class JsTaskWater
{

/*
.env中增加
;三方平台相关配置
;充值手续费（除过百分比的，比如 0.01表示1%
DEPOSIT_FEE=0.005
;提现手续费（除过百分比的，比如0.01表示1%
WITHDRAW_FEE=0.005
;负盈分佣模式下技术服务费阶梯 格式为（金额从大到小，每一段表示超过多少金额应用对应的比率）：起始金额|服务费比率,...
FYFY_TEC_RATE=100000|0.01,10000|0.005,1000|0.002,0|0.001
;负盈分佣模式下当月负盈忽略金额（负数），负盈大于此值的忽略
FYFY_LOSE_LINEAMOUNT= -10000

;内部代理是否自动发放分佣 0 不自动 1自动
FYFY_AUTO_COMMISSION_SELF_PROXY=0

;分佣结算周期模式 w/W表示按周结算，m/M表示按月结算--此模式下此设置仅作用于内部代理
FYFY_BILLING_CYCEL=w
;分佣结算周期变更开始时间戳 0表示按最早时间或自然周期开始时间 指定时间戳表示按指定时间开始，用于在改变结算周期方式后避免重复统计(此种情况下一定要注意设置的时间戳值必须是更改结算模式的当天)
;且更改模式时一定要保证前一天的结算日志记录已经存在
FYFY_BILLING_START_TIME=0

;外部代理（用户代理）按流水分佣模式下的配置，是否自动分佣按系统配置设置
;分佣周期--此模式下内外部代理的分佣周期分别支持，此设置仅用于外部代理 d/D表示按日结算，w/W表示按周结算，m/M表示按月结算
FYFY_BILLING_CYCEL_OUTSIDE=d
;分佣级数，默认三级
FYFY_BILLING_LEVEL_OUTSIDE=3
;分佣级数对应的分佣比例，分佣级数多少级，这里就要设置多少个比率（从一级到X级），使用,隔开，如设置数量不足，则不足部分全部使用最后一个比率进行分佣
FYFY_BILLING_LEVEL_OUTSIDE_RATE=0.05,0.03,0.01

sql:
ALTER TABLE `dq_temp_fyfy_proxy_users`
ADD COLUMN `tfpu_comlvl` int(11) NULL DEFAULT 0 COMMENT '相对级别，用于按级别进行分佣时的统计' AFTER `tfpu_scdm`,
ADD INDEX(`tfpu_comlvl`);
ALTER TABLE `dq_third_stats_fyfy`
ADD COLUMN `sff_bet` decimal(20, 8) NULL DEFAULT 0 COMMENT '有效投注额，用于外部用户代理返佣' AFTER `sff_prelosetotal`;
ALTER TABLE `dq_third_stats_fyfylog`
DROP INDEX `sffl_dw`,
ADD INDEX `sffl_dw`(`sffl_dw`, `sffl_uid`) USING BTREE;
ALTER TABLE `dq_third_stats_fyfy`
ADD COLUMN `sff_exinf` varchar(255) NULL COMMENT '扩展信息，比如保存外部代理流水分佣每次的分佣设置' AFTER `sff_memo`;
*/

    /** 外部代理流水分佣模式下专用于查询分佣明细的方法 ，给运营后台使用
     * sff_w字段值如果为8位日期，表示是按日结算的记录，如果是6位的周代码，表示是按周结算的记录，null表示按月结算的记录
     * @param $uid fyfy表中的sff_uid字段值，即外部代理的用户ID
     * @param $dm fyfy表中的sff_m字段值
     * @param $dw fyfy表中的sff_w字段值
     * @param $cfg fyfy表中的sff_exinf字段值
     * @return array | string 返回字符串表示出错
     */
    public static function getOutProxyWaterFyDetail($uid,$dm,$dw,$cfg){
        //根据$dm找到表后缀

        //根据dw区分结算周期，如果有跨表暂时报错 TODO 将来如果需要，可以静态化
        $arr=[];
        $dts='';
        $dte='';
        $usql='';
        $casesql=self::getfyfycasesql($cfg);
        if (empty($dw)){
            //按月
            $tbex=Utils::getCutTableEx('dq_third_stats_fyfylog',$dm,'m');
            $dts=$dm."01";
            $dte=$dm."31";
        }elseif($dw>999999){
            //按日
            $tbex=Utils::getCutTableEx('dq_third_stats_fyfylog',$dm,'m');
            $dts=$dw;
            $dte=$dw;
        }else{
            //按周
            $tbex=Utils::getCutTableEx('dq_third_stats_fyfylog',$dw,'w');
            $t=Utils::nextweek($dw,0);
            $dts=date("Ymd",$t['ys']);
            $dte=date("Ymd",$t['ye']);
            $dms=date("Ym",$t['ys']);
            $dme=date("Ym",$t['ye']);
            if ($dms !=$dme){
                //跨表
                $tbex1=Utils::getCutTableEx('dq_third_stats_fyfylog',$dme,'m');
                if ($tbex1===false){
                    return "未找到分佣日志分表数据";
                }
                $usql="select sff_uid,lvl,bet,
                        (
                            case lvl
                            ".$casesql['sql']."
                            else
                            0
                            end
                        ) as fy from
                        (
                        select sff_uid,lvl,sum(bet) as bet from (
                        select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sffl_bet as bet
                        from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog{$tbex}
                        where tfpu_uid=sffl_uid and tfpu_proxysubuid={$uid} and tfpu_proxytype=1 and sffl_dt BETWEEN {$dts} and {$dms}31
                        union all
                        select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sffl_bet as bet
                        from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog{$tbex1}
                        where tfpu_uid=sffl_uid and tfpu_proxysubuid=10056 and tfpu_proxytype=1 and sffl_dt BETWEEN {$dme}01 and {$dte}
                        ) tbc
                        group by lvl
                        ) tba";
            }

        }
        if ($tbex===false){
            return "未找到分佣日志分表数据";
        }
        $sql=empty($usql)?("select sff_uid,lvl,bet,
	(
		case lvl
		".$casesql['sql']."
		else
		0
		end
	) as fy from
	(
	select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sum(sffl_bet) as bet
	from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog{$tbex}
	where tfpu_uid=sffl_uid and tfpu_proxysubuid={$uid} and tfpu_proxytype=1 and sffl_dt BETWEEN {$dts} and {$dte} 
	group by tfpu_comlvl
	) tba"):$usql;
        $arr=Db::query($sql);
        return $arr;
    }

    public static function getcasejsnumber($d,$num){
        $number=0;
        for($i=0;$i<count($d)-1;$i++){
            if ($d[$i]['amount']<=$num){
                $number+=$d[$i]['add'];
            }
        }
        return $number;
    }


    /**
     * 生成负盈返佣结算技术服务费计算SQL
     * @return string
     * @author twt326
     * @date 2022-10-22 1:48
     */
    public static function getfyteccasesql(){
        $sql="";
        $tecs=explode(",",str_replace('，',',',env('FYFY_TEC_RATE')));
        $tecrate=[];
        for($i=0;$i<count($tecs);$i++){
            $tmptec=explode("|",$tecs[$i]);
            $tecrate[$i]=[
                'amount'=>$tmptec[0],
                'rate'=>$tmptec[1],
                'add'=>0
            ];
            if ($i>0){
                $tecrate[$i-1]['add']=($tecrate[$i-1]['amount']-$tecrate[$i]['amount'])*$tecrate[$i]['rate'];
            }
        }
        for($i=0;$i<count($tecrate);$i++){
            //按客损的算法 如果客损是负数，且又设置了负数客损的技术费比例，则会将技术服务费取绝对值，以避免技术服务费出现负数的情况
            $sql.=" when sff_ks>".$tecrate[$i]['amount']." then abs((sff_ks-".$tecrate[$i]['amount'].")*".$tecrate[$i]['rate'].") + ".self::getcasejsnumber($tecrate,$tecrate[$i]['amount']);
            //第一版按充值提现差额的算法
            /*$sql.=" when (sff_payin-sff_payout)>".$tecrate[$i]['amount']." then (sff_payin-sff_payout-".$tecrate[$i]['amount'].")*".$tecrate[$i]['rate']." + ".self::getcasejsnumber($tecrate,$tecrate[$i]['amount']);*/
        }
        return $sql;
    }

    /** 统计外部代理有效投注分佣金额的SQL
     * @param $cfg 传入指定配置 格式  lvls|rates
     * @return string
     * @author twt326
     * @date 2022-10-28 23:02
     */
    public static function getfyfycasesql($cfg=null){
        $sql="";
        if (empty($cfg)) {
            $lvls = intval(env('FYFY_BILLING_LEVEL_OUTSIDE', 3));
            $rate = env('FYFY_BILLING_LEVEL_OUTSIDE_RATE', '0');
        }else{
            $cfg=explode("|",$cfg);
            $lvls=$cfg[0];
            $rate=$cfg[1];
        }
        $rates=str_replace("，",",",$rate);
        $rates=explode(",",$rates);
        $rc=count($rates);
        for($i=1;$i<=$lvls;$i++) {
            $sql.=" when $i then bet * ".$rates[$i>$rc?$rc-1:$i-1];
        }
        if (empty($sql)) {
            $sql=" when 0 then 0 ";//没有配置的时候直接返回0
        }
        return ['sql'=>$sql,'cfg'=>$lvls."|".$rate];
    }
    
    /**
     * 重新生成参与数据统计的内部和外部代理树成员列表
     * TODO 未来看看在符合运营需求的情况下通过生成时间或是是否有变动来决定是否要重新生成，提升效率
     */
    public static function refreshProxyTrees(){
        //删除临时表数据
        $rlt=Db::execute("truncate table dq_temp_fyfy_proxy_users");
        Utils::L('refreshProxyTrees',["删除临时表数据",$rlt]);
        //找到外部代理和下x级
        $lvlnum=env('FYFY_BILLING_LEVEL_OUTSIDE',3);
        //代理统计时不包含自身--效率太低，废弃
        /*$sql="insert into dq_temp_fyfy_proxy_users
select 0,ua.uid,1,ub.id,ub.xc_date,ub.rec_level-uc.rec_level
from dq_user_agent ua
left join dq_user uc on ua.uid=uc.id
left join dq_user ub on ua.uid<>ub.id and ub.tree like concat(uc.tree,'%') and ub.rec_level<=uc.rec_level+{$lvlnum} and ub.lebal=1
where ua.type=0 and ua.status=3 and ub.id is not null";
        $rlt=Db::execute($sql);*/
        //2023年5月12日更改算法
        $sqls=[];
        //找到外部代理的下级
        $sql="insert into dq_temp_fyfy_proxy_users
select 0,ta.uid,1,tu.id,tu.xc_date,1
from dq_user_agent ta,dq_user tu
where tu.rec_id=ta.uid and ta.type=0 and ta.status=3";
        $rlt=Db::execute($sql);
        array_push($sqls,$sql,$rlt);
        //找到外部代理的下x级
        for($i=2;$i<=$lvlnum;$i++){
            $uplvl=$i-1;
            $sql="insert into dq_temp_fyfy_proxy_users
select 0,ta.tfpu_proxysubuid,1,tu.id,tu.xc_date,{$i}
from dq_temp_fyfy_proxy_users ta,dq_user tu
where tu.rec_id=ta.tfpu_uid and ta.tfpu_proxytype=1 and ta.tfpu_comlvl={$uplvl}";
            $rlt=Db::execute($sql);
            array_push($sqls,$sql,$rlt);
            if ($rlt==0) break;
        }
        Utils::L('refreshProxyTrees',["写入外部代理树",$sqls]);
        //找内部代理树用户
        //代理统计时不包含自身
        $sql="insert into dq_temp_fyfy_proxy_users
select uaa.proxy_topuid,uaa.uid,0,ubb.id,ubb.xc_date,0
from dq_user_agent uaa
left join dq_user ubb on uaa.uid<>ubb.id and ubb.tree like concat(uaa.tree,'%') and ubb.lebal=1
where uaa.type=1 and uaa.status=3 and ubb.id is not null
order by uaa.uid";
        $rlt=Db::execute($sql);
        Utils::L('refreshProxyTrees',["写入内部代理树",$rlt,$sql]);
    }

    /** 统计日分佣用户相关数据
     * @param null $jsdt
     */
    public static function jsfy_count_day_data($dt,$ds,$de){
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
                //合并用主呢ID到日志表
                $sql = "insert into dq_third_stats_fyfylog (sffl_dt,sffl_uid)
select distinct $dt,tfpu_uid from dq_temp_fyfy_proxy_users order by tfpu_uid";
                $flag = Db::execute($sql);
                Utils::L('jsfy_water',["合并用户ID到日志表",$flag,$sql]);
                if ($flag) {
                    //统计返水
                    $sql = "update 
(
select sffl_dt,sffl_uid,sum(sb_backmoney) as backmoney from 
dq_third_stats_fyfylog
left join dq_third_stats_back on sffl_dt=sb_dt and sffl_uid=sb_uid
where sffl_dt=$dt and sb_dt is not null group by sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_back=ta.backmoney
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计返水", $rlt, $sql]);
                    //统计优惠一
                    $sql = "update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gd_amount+yhtb.tyj_amount) as money from 
dq_third_stats_fyfylog logtb
left join dq_action_apply yhtb on logtb.sffl_uid=yhtb.uid and yhtb.ps_status=1 and yhtb.ps_deta BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计优惠一", $rlt, $sql]);
                    //统计优惠二
                    $sql = "update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.price) as money from 
dq_third_stats_fyfylog logtb
left join dq_artificial_balance yhtb on logtb.sffl_uid=yhtb.uid and yhtb.a_type>2 and yhtb.add_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=tb.sffl_yh+ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计优惠二", $rlt, $sql]);
                    //统计充值
                    $sql = "update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.arrival_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_offline yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status>=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payin=ta.money,tb.sffl_payinfee=ta.money*" . env('DEPOSIT_FEE') . "
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计充值", $rlt, $sql]);
                    //统计提现
                    $sql = "update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.w_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_withdrawal yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payout=ta.money,tb.sffl_payoutfee=ta.money*" . env('WITHDRAW_FEE') . "
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计提现", $rlt, $sql]);
                    //统计有效投注
                    /*$sql="update
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_validwater) as validwater from
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_overtime BETWEEN $ds and $de
left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_state=1 and gtb.g_fystatus=1
where logtb.sffl_dt=$dt and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";*/
                    list("max"=>$max_id,"min"=>$min_id)=JsTask::getBetsIdRange($ds,$de);
                    /*$sql = "update  //大数据时疑似性能有问题
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_validwater) as validwater from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_overtime BETWEEN $ds and $de
left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_fystatus=1
where logtb.sffl_dt=$dt and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";*/
                    //尝试性能优化
                    $sql = "update 
(
    select sffl_dt,sffl_uid,sum(validwater) as validwater from 
(
select logtb.sffl_dt,logtb.sffl_uid,yhtb.gb_gid,sum(yhtb.{$validWaterField}) as validwater from 
dq_third_stats_fyfylog logtb,dq_game_bets yhtb 
where logtb.sffl_dt=$dt and logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_overtime BETWEEN {$ds} and {$de} group by logtb.sffl_uid,yhtb.gb_gid
) ttb,dq_games ttg
where ttb.gb_gid=ttg.g_id and ttg.g_fystatus=1
group by ttb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计有效投注", $rlt, $sql]);

                    //统计客损
                    $sql = "update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.{$validWaterField}) as validwater,sum(if (yhtb.gb_winlose=2,yhtb.gb_price,0)) as loss,sum(yhtb.gb_otherwin) as win from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_winlose BETWEEN 2 and 3 and yhtb.gb_overtime BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.gb_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_betwin=ta.validwater,tb.sffl_betloss=ta.loss,tb.sffl_ks=ta.win*-1
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                    $rlt = Db::execute($sql);
                    Utils::L('jsfy_water', ["统计客损", $rlt, $sql]);
                }
                return $flag;
    }


    /** 根据env配置决定是按月结算还是按周结算
     * @param null $jsdt
     * @author twt326
     * @date 2023-03-13 22:58
     */
    public static function jsfy($jsdt=null){
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        $yst=$pst-86400;
        $dt=date("Ymd",$yst);
        $dse=date("Y-m-d",$yst);
        $ds=strtotime($dse." 00:00:00");
        $de=$ds+86400-1;
        //统一时区
        $sql="set time_zone='".env("mysql_timezone",'+00:00')."'";//mysql_timezone
        Db::execute($sql);
        //结算昨日返水
        if (JsTask::jsback($jsdt)) {
            Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
            //更新代理树信息
            self::refreshProxyTrees();
            $rlt=Db::query('select sffl_dt from dq_third_stats_fyfylog where sffl_dt='.$dt.' limit 1');
            Utils::L('jsfy_water',["准备结算 $dt 分佣日志",$rlt]);
            if (count($rlt)==0) {
                Utils::L('jsfy_water', ["准备统计日数据", $dt]);
                $flag = false;
                Db::startTrans();
                try {
                    $flag = self::jsfy_count_day_data($dt, $ds, $de);
                    if ($flag) {
                        Db::commit();
                    } else {
                        Db::rollback();
                        Utils::L('jsfy_water', ["统计日数据失败", $flag]);
                        return;
                    }
                } catch (\Exception $ex) {
                    Db::rollback();
                    Utils::L('jsfy_water', ["统计日数据失败", $ex->getMessage()]);
                    return;
                }

                $jsmod = env('FYFY_BILLING_CYCEL', 'W');
                $jsmod = strtoupper($jsmod);
                $jsmodout = env('FYFY_BILLING_CYCEL_OUTSIDE', 'D');
                $jsmodout = strtoupper($jsmodout);
                switch ($jsmodout) {
                    case 'D':
                        self::jsfy_day_out($jsdt);
                        break;
                    case 'M':
                        self::jsfy_month_out($jsdt);
                        break;
                    default:
                        self::jsfy_week_out($jsdt);
                        break;
                }
                switch ($jsmod) {
                    case 'M':
                        self::jsfy_month($jsdt);
                        break;
                    default:
                        self::jsfy_week($jsdt);
                        break;
                }
            }
            Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        }else{
            Utils::L('jsfy_water','结算返水错误');
            Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        }
    }



    /**
     * 外部用户按日结算，内部代理照原样结算
     * @param null $jsdt
     */
    private static function jsfy_day_out($jsdt=null){
        Utils::L('jsfy_water',["执行外部代理按日分佣统计"]);
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        $dt=date("Ymd",$yst);
        $dm=date("Ym",$yst);
        //外部代理按日流水分佣时日期存储于ssf_w字段中
        $rlt=Db::query('select sff_w from dq_third_stats_fyfy where sff_w='.$dt.' and sff_utype=1 limit 1');
        Utils::L('jsfy_water',["xx准备结算 $dt 分佣日志",$rlt]);
        if (count($rlt)==0) {
            Db::startTrans();
            try {
                $casesql=self::getfyfycasesql();
                    //生成用户代理分佣统计
                $sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_fy,sff_exinf)
select $dm as sff_m,$dt as sff_w,sff_uid,$st as sff_statstime,1 as sff_utype,
sum(fy) as sff_fy,'".$casesql['cfg']."'
from
(
	select sff_uid,
	(
		case lvl
		".$casesql['sql']."
		else
		0
		end
	) as fy from
	(
	select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sum(sffl_bet) as bet
	from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
	where tfpu_uid=sffl_uid and tfpu_proxytype=1 and sffl_dt =$dt
	group by tfpu_proxysubuid,tfpu_comlvl
	) tba
) tbb group by sff_uid";
                Utils::L('jsfy_water',["生成外部统计",$sql]);
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["生成外部统计res",$rlt]);

                    Db::commit();

            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsfy_water',[$ex->getMessage(), $ex->getTraceAsString()]);
            }
        }else{
            Utils::L('jsfy_water',["分佣日志已存在，不需重复结算",$dt]);
        }
    }

    /** 负盈分佣模式每日统计-按月
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    private static function jsfy_month_out($jsdt=null){
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        $dm=date("Ym",$yst);


            Db::startTrans();
            try {
                    //清除当月统计
                    $sql="delete from dq_third_stats_fyfy where sff_m=$dm and sff_utype=1 and sff_w is null";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["清除当月统计1",$rlt,$sql]);

                    //不能小于指定分佣统计开始时间
                    $smonthday="00";
                    if ($jsstarttime){
                        $jsstmp=date("Ym",$jsstarttime);
                        if ($jsstmp==$dm){
                            $smonthday=date("d",$jsstarttime);
                        }
                    }
                $casesql=self::getfyfycasesql();
                    //生成外部分佣
                    $sql="insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_fy,sff_exinf)
select $dm as sff_m,sff_uid,$st as sff_statstime,1 as sff_utype,
sum(fy) as sff_fy,'".$casesql['cfg']."'
from
(
	select sff_uid,
	(
		case lvl
		".$casesql['sql']."
		else
		0
		end
	) as fy from
	(
	select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sum(sffl_bet) as bet
	from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
	where tfpu_uid=sffl_uid and tfpu_proxytype=1 and sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxysubuid,tfpu_comlvl
	) tba
) tbb group by sff_uid";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["生成外部统计",$rlt,$sql]);

                    Db::commit();

            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsfy_water', [$ex->getMessage(), $ex->getTraceAsString()]);
            }


    }

    /** 负盈分佣模式每日统计-按月
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    private static function jsfy_month($jsdt=null){
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        $rd=(new ApiRedis())->systemInfo();
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        $dm=date("Ym",$yst);
        $dmyst=date('Ym',strtotime(date("Y-m-01 00:00:00",$yst))-1);//统计月份的上月，用于迁移上月负盈


            Db::startTrans();
            try {
                    //内部下级代理扣减外部分佣统计
                    $jsmodout = env('FYFY_BILLING_CYCEL_OUTSIDE', 'D');
                    $jsmodout = strtoupper($jsmodout);
                    $rlt=JsTask::subOutsideMoneyCount("M",$jsmodout,$dm,$dm);
                Utils::L('jsfy_water',["内部下级代理扣减外部分佣统计",$rlt,$dm]);

                    //清除当月统计
                    $sql="delete from dq_third_stats_fyfy where sff_m=$dm and sff_utype>1 and sff_w is null";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["jsfy_month-清除当月统计",$rlt,$sql]);

                    //不能小于指定分佣统计开始时间
                    $smonthday="00";
                    if ($jsstarttime){
                        $jsstmp=date("Ym",$jsstarttime);
                        if ($jsstmp==$dm){
                            $smonthday=date("d",$jsstarttime);
                        }
                    }

                    //生成内部统计
                    $sql="insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dm as sff_m,tfpu_proxysubuid as sff_uid,$st as sff_statstime,3 as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and tfpu_proxytype=0 and sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxysubuid";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["生成外部和内部非顶级统计",$rlt,$sql]);

                    //统计技术服务费
                    $sql="update
dq_third_stats_fyfy
set sff_tec=(
	case 
	".self::getfyteccasesql()."
	else
		0
	end
	)
where sff_m=$dm";// and sff_utype>1 技术服务费不区分内外部？
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["统计技术服务费",$rlt,$sql]);

                    if ($rd['fyfy_losstype']==1) {//如果设置负盈累积，则上期负盈累计至下个月
                        //统计上期负盈，本期负盈
                        //上期负盈迁移
                        $sql = "update
dq_third_stats_fyfy ta
left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_m=$dmyst
set ta.sff_prelosetotal=tb.sff_losetotal
where ta.sff_m=$dm and tb.sff_m is not null";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["上期负盈迁移",$rlt,$sql]);
                        //本期负盈
                        $loseamountline = intval(env('FYFY_LOSE_LINEAMOUNT'));
                        $loseamountlineabs = abs($loseamountline);
                        //新版本中用客损额算负盈和分佣基数
                        $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec,
sff_losetotal=if(sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec>=$loseamountline,0,sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec+$loseamountlineabs)
where sff_m=$dm";
                        //版本一中按充值提现算负盈和分佣基数
                        /*$sql = "update
dq_third_stats_fyfy
set sff_base=sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec,
sff_losetotal=if(sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec>=$loseamountline,0,sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec+$loseamountlineabs)
where sff_m=$dm";*/
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["计算客损负盈和分佣基数",$rlt,$sql]);
                    }else{//新版本中用客损额算分佣基数
                        $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec
where sff_m=$dm";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["计算客损分佣基数",$rlt,$sql]);
                    }

                    //统计分佣金额（内部代理）-------------------------START
                    //统计每级代理的分佣金额（按比例）
                    $sql="update dq_third_stats_fyfy,dq_user_agent 
set sff_baserate=rate,sff_basereal=sff_base*rate,sff_fy=sff_base*rate
where sff_uid=uid and status=3
and sff_m=$dm and sff_utype>1 and sff_base>0";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["按比例统计每级代理分佣金额（内部）",$rlt,$sql]);
                    //扣减每级代理应分给下级的金额，得到真正的分佣金额
                    $sql="update 
(select tba.uid,sum(tbc.sff_basereal) as subamount from dq_user_agent tba
left join dq_user_agent tbb on tba.uid=tbb.proxy_upuid and tbb.type=1 and tbb.status=3
left join dq_third_stats_fyfy tbc on tbc.sff_uid=tbb.uid and tbc.sff_m=$dm
where tba.type=1 and tba.status=3 and tbb.id is not null and tbc.sff_base>0
group by tba.uid) tbd,dq_third_stats_fyfy tbe
set tbe.sff_fy=tbe.sff_fy-tbd.subamount
where tbd.uid=tbe.sff_uid and tbe.sff_m=$dm";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["扣减分给下级的金额（内部）",$rlt,$sql]);
                //按比例减下级外部代理分佣
                $rlt=JsTask::subOutsideMoneyToFyfy("M",$dm);
                Utils::L('jsfy',["按比例扣减下级外部代理分佣",$rlt]);
                    //统计分佣金额（内部代理）-------------------------END
                    Db::commit();

            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsfy_water', [$ex->getMessage(), $ex->getTraceAsString()]);
            }


    }

    /** 负盈分佣模式每日统计-按周
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    private static function jsfy_week_out($jsdt=null){
        Utils::L('jsfy_water',["准备周结算 $jsdt 分佣日志"]);
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        Utils::L('jsfy_water',["yst",$yst]);
        $week=Utils::nextweek($yst,-1);
        Utils::L('jsfy_water',["周计算结果",$week]);
        $dw=$week['yw'];//本周字符串
        $dms=date('Ym',$week['ys']);//上周一所在月
        $dme=date("Ym",$week['ye']);//上周末所在月


            Db::startTrans();
            try {

                    //清除当周统计
                    $sql="delete from dq_third_stats_fyfy where sff_w=$dw  and sff_utype=1";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["jsfy_week_out-清除当月统计",$rlt,$sql]);

                    //不能小于指定分佣统计开始时间
                    $smonthday=date("d",$week['ys']);//本周一的日期
                    $emonthday=date("d",$week['ye']);//本周末的日期
                    if ($jsstarttime){
                        $jsstmp=date("Ym",$jsstarttime);
                        $jsstmpd=date("d",$jsstarttime);
                        if ($jsstmp==$dms && $jsstmpd>$smonthday){
                            $smonthday=$jsstmpd;
                        }elseif ($jsstmp==$dme && $jsstmpd<$emonthday){
                            $dms=$dme;
                            $smonthday=$jsstmpd;
                        }
                    }

                    //生成外部统计
                    //内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                $casesql=self::getfyfycasesql();
                    $sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_fy,sff_exinf)
select $dms as sff_m,$dw as sff_w,sff_uid,$st as sff_statstime,1 as sff_utype,
sum(fy) as sff_fy,'".$casesql['cfg']."'
from
(
	select sff_uid,
	(
		case lvl
		".$casesql['sql']."
		else
		0
		end
	) as fy from
	(
	select tfpu_proxysubuid as sff_uid,tfpu_comlvl as lvl,sum(sffl_bet) as bet
	from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
	where tfpu_uid=sffl_uid and tfpu_proxytype=1 and sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxysubuid,tfpu_comlvl
	) tba
) tbb group by sff_uid";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["生成外部统计",$rlt,$sql]);

                    Db::commit();

            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsfy_water',[$ex->getMessage(),isset($rlt)?$rlt:null,isset($sql)?$sql:null]);
            }

    }

    /** 负盈分佣模式每日统计-按周
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    private static function jsfy_week($jsdt=null){
        Utils::L('jsfy_water',["准备内部代理周结算 $jsdt 分佣日志"]);
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        $rd=(new ApiRedis())->systemInfo();
        //判断昨日分佣是否已统计
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        Utils::L('jsfy_water',["yst",$yst]);
        $week=Utils::nextweek($yst,-1);
        Utils::L('jsfy_water',["周计算结果",$week]);
        $dmyst=$week['nextyw'];//统计周的上周，用于迁移上周负盈
        $dw=$week['yw'];//本周字符串
        $dms=date('Ym',$week['ys']);//上周一所在月
        $dme=date("Ym",$week['ye']);//上周末所在月
            Db::startTrans();
            try {
                //内部下级代理扣减外部分佣统计
                $jsmodout = env('FYFY_BILLING_CYCEL_OUTSIDE', 'D');
                $jsmodout = strtoupper($jsmodout);
                $rlt=JsTask::subOutsideMoneyCount("W",$jsmodout,$dw,$dms);
                Utils::L('jsfy_water',["内部下级代理扣减外部分佣统计",$rlt,$dw,$dms]);
                    //清除当周统计
                    $sql="delete from dq_third_stats_fyfy where sff_w=$dw and sff_utype>1";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["jsfy_week-清除当月统计",$rlt,$sql]);

                    //不能小于指定分佣统计开始时间
                    $smonthday=date("d",$week['ys']);//本周一的日期
                    $emonthday=date("d",$week['ye']);//本周末的日期
                    if ($jsstarttime){
                        $jsstmp=date("Ym",$jsstarttime);
                        $jsstmpd=date("d",$jsstarttime);
                        if ($jsstmp==$dms && $jsstmpd>$smonthday){
                            $smonthday=$jsstmpd;
                        }elseif ($jsstmp==$dme && $jsstmpd<$emonthday){
                            $dms=$dme;
                            $smonthday=$jsstmpd;
                        }
                    }

                    //生成外部和内部非顶级统计
                    //内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                    $sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dms as sff_m,$dw as sff_w,tfpu_proxysubuid as sff_uid,$st as sff_statstime,3 as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and tfpu_proxytype=0 and sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxysubuid";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["生成外部和内部非顶级统计",$rlt,$sql]);

                    //统计技术服务费
                    $sql="update
dq_third_stats_fyfy
set sff_tec=(
	case 
	".self::getfyteccasesql()."
	else
		0
	end
	)
where sff_w=$dw";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["统计技术服务费",$rlt,$sql]);

                    if ($rd['fyfy_losstype']==1) {//如果设置负盈累积，则上期负盈累计至下个月
                        //统计上期负盈，本期负盈
                        //上期负盈迁移
                        $sql = "update
dq_third_stats_fyfy ta
left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_w=$dmyst
set ta.sff_prelosetotal=tb.sff_losetotal
where ta.sff_w=$dw and tb.sff_w is not null";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["上期负盈迁移",$rlt,$sql]);
                        //本期负盈
                        $loseamountline = intval(env('FYFY_LOSE_LINEAMOUNT'));
                        $loseamountlineabs = abs($loseamountline);
                        //新版本中用客损额算负盈和分佣基数
                        $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec,
sff_losetotal=if(sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec>=$loseamountline,0,sff_prelosetotal+sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec+$loseamountlineabs)
where sff_w=$dw";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["计算客损负盈和分佣基数",$rlt,$sql]);
                    }else{//新版本中用客损额算分佣基数
                        $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec
where sff_w=$dw";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy_water',["计算客损分佣基数",$rlt,$sql]);
                    }

                    //统计分佣金额（内部代理）-------------------------START
                    //统计每级代理的分佣金额（按比例）
                    $sql="update dq_third_stats_fyfy,dq_user_agent 
set sff_baserate=rate,sff_basereal=sff_base*rate,sff_fy=sff_base*rate
where sff_uid=uid and status=3
and sff_w=$dw and sff_utype>1 and sff_base>0";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["按比例统计每级代理分佣金额（内部）",$rlt,$sql]);
                    //扣减每级代理应分给下级的金额，得到真正的分佣金额
                    $sql="update 
(select tba.uid,sum(tbc.sff_basereal) as subamount from dq_user_agent tba
left join dq_user_agent tbb on tba.uid=tbb.proxy_upuid and tbb.type=1 and tbb.status=3
left join dq_third_stats_fyfy tbc on tbc.sff_uid=tbb.uid and tbc.sff_w=$dw
where tba.type=1 and tba.status=3 and tbb.id is not null and tbc.sff_base>0
group by tba.uid) tbd,dq_third_stats_fyfy tbe
set tbe.sff_fy=tbe.sff_fy-tbd.subamount
where tbd.uid=tbe.sff_uid and tbe.sff_w=$dw";
                    $rlt=Db::execute($sql);
                    Utils::L('jsfy_water',["扣减分给下级的金额（内部）",$rlt,$sql]);
                //按比例减下级外部代理分佣
                $rlt=JsTask::subOutsideMoneyToFyfy("W",$dw);
                Utils::L('jsfy',["按比例扣减下级外部代理分佣",$rlt]);
                    //统计分佣金额（内部代理）-------------------------END
                    Db::commit();

            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsfy_water',[$ex->getMessage(),isset($rlt)?$rlt:null,isset($sql)?$sql:null]);
            }

    }

    /** 根据env配置决定是按月结算支付还是按周结算支付
     * @param null $jsdt
     * @author twt326
     * @date 2023-03-13 22:58
     */
    public static function jsfypay($jsdt=null){
        $jsmod=env('FYFY_BILLING_CYCEL','W');
        $jsmod=strtoupper($jsmod);
        $jsmodout = env('FYFY_BILLING_CYCEL_OUTSIDE', 'D');
        $jsmodout = strtoupper($jsmodout);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        switch ($jsmodout) {
            case 'D':
                self::jsfypay_day_out($jsdt);
                break;
            case 'M':
                self::jsfypay_month_out($jsdt);
                break;
            default:
                self::jsfypay_week_out($jsdt);
                break;
        }
        switch ($jsmod){
            case 'M':
                self::jsfypay_month($jsdt);
                break;
            default:
                self::jsfypay_week($jsdt);
                break;
        }

    }

    /**流水模式分佣（外部代理）
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-22 2:57
     */
    private static function jsfypay_day_out($jsdt=null){
        $rd=(new ApiRedis())->systemInfo();
        $autopay=($rd['auto_commission']==1);//自动分佣 仅分用户代理
//自动分佣判断昨日统计是否已完成，完成则分佣
        if ($autopay){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm=strtotime(date("Y-m-d 00:00:00",$pst))-1;
            $dt=date("Ymd",$ystm);
            //判断上月是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            if (count($rlt)>0){
                    $typesql=" sff_utype=1 and ";
                //判断昨日是否已结算（只要有一笔支付则表示上月已结算）
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_w=$dt and sff_payed=2 limit 1");
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        JsTask::limitOutsideFypay("sff_w=$dt",$ystm-86400+1, $ystm);
                        $cjset=SysSets::getSetByCode("sys_cj_support");
                        $cjset=($cjset==1);//是否支持彩金
                        $paytocjset=SysSets::getSetByCode("sys_cj_fypay");
                        $paytocjset=($paytocjset==1);//返佣是否发放到彩金
                        $watertype=($cjset && $paytocjset)?101:3;//流水记录类型值
                        $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';//更新余额字段
                        $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';//更新流水字段
                        $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';//变帐记录表名
                        //变更发放状态 将分佣金额为0或小于0的记录变更为已发放状态
                        /*$sql = "update dq_third_stats_fyfy  //此处保留的是old句型，其它分佣方法中OLD语句未保留
set sff_payed=2,sff_paytime=$st
where $typesql sff_w=$dt and sff_fy>0 and sff_payed=1";*/

                        $sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=if(sff_fy>0,$st,0)
where $typesql sff_w=$dt and sff_payed=1";
                        $flag = Db::execute($sql);
                        if ($flag) {
                            //增加用户余额 和 savings
                            $savingRate=isset($rd['FySavingRateOut'])?floatval($rd['FySavingRateOut']):1;
                            if ($savingRate<0) $savingRate=0;
                            if ($savingRate>0) {
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,{$savingRate} as multiple,sff_fy*{$savingRate} as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_w=$dt and sff_fy>0 and sff_payed=2";
                                $flag = Db::execute($sql);
                                Utils::L('jsfypay_water', ["写入流水记录", $flag, $sql]);
                            }
                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy*{$savingRate}
where $typesql sff_uid=id and sff_w=$dt and sff_fy>0 and sff_payed=2";
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay_water', ["更新余额和流水", $flag, $sql]);
                            //更新变账记录
                            $sql = "insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sff_uid,{$fieldban}-sff_fy,{$fieldban},sff_fy,6,concat(sff_m,'代理返佣发放') 
from dq_third_stats_fyfy,dq_user
where $typesql sff_uid=id and sff_w=$dt and sff_fy>0 and sff_payed=2" ;
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay_water', ["更新变账记录", $flag, $sql]);
                        }


                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                            //return false;
                        }
                    }catch (\Exception $ex){
                        Db::rollback();
                        Utils::L('jsfypay', [$ex->getMessage(), $ex->getTraceAsString()]);
                        //return false;
                    }
                }
            }


        }
    }

    /**流水模式分佣（外部代理）
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-22 2:57
     */
    private static function jsfypay_month_out($jsdt=null){
        $rd=(new ApiRedis())->systemInfo();
        $autopay=($rd['auto_commission']==1);//自动分佣
//自动分佣判断上月最后一天统计是否已完成，完成则分佣
        if ($autopay){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm=strtotime(date("Y-m-01 00:00:00",$pst))-1;
            $dt=date("Ymd",$ystm);
            $dm=date("Ym",$ystm);
            //判断上月是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            if (count($rlt)>0){
                    $typesql=" sff_utype=1 and ";
                //判断上周是否已结算（只要有一笔支付则表示上月已结算）
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_m=$dm and sff_payed=2 limit 1");
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        JsTask::limitOutsideFypay("sff_m=$dm",strtotime(date("Y-m-01 00:00:00",$ystm)), $ystm);
                        $cjset=SysSets::getSetByCode("sys_cj_support");
                        $cjset=($cjset==1);//是否支持彩金
                        $paytocjset=SysSets::getSetByCode("sys_cj_fypay");
                        $paytocjset=($paytocjset==1);//返佣是否发放到彩金
                        $watertype=($cjset && $paytocjset)?101:3;//流水记录类型值
                        $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';//更新余额字段
                        $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';//更新流水字段
                        $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';//变帐记录表名
                        $flag = false;
                        //变更发放状态
                        $sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=if(sff_fy>0,$st,0)
where $typesql sff_m=$dm and sff_payed=1";
                        $flag = Db::execute($sql);
                        if ($flag) {
                            //增加用户余额 和 savings
                            $savingRate=isset($rd['FySavingRateOut'])?floatval($rd['FySavingRateOut']):1;
                            if ($savingRate<0) $savingRate=0;
                            if ($savingRate>0) {
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,{$savingRate} as multiple,sff_fy*{$savingRate} as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_m=$dm and sff_fy>0 and sff_payed=2";
                                $flag = Db::execute($sql);
                                Utils::L('jsfypay_water', ["写入流水记录", $flag, $sql]);
                            }
                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy*{$savingRate}
where $typesql sff_uid=id and sff_m=$dm and sff_fy>0 and sff_payed=2";
                            $flag = Db::execute($sql);
                            //更新变账记录
                            $sql = "insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sff_uid,{$fieldban}-sff_fy,{$fieldban},sff_fy,6,concat(sff_m,'代理返佣发放') 
from dq_third_stats_fyfy,dq_user
where $typesql sff_uid=id and sff_m=$dm and sff_fy>0 and sff_payed=2" ;
                            $flag = Db::execute($sql);
                        }

                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                            //return false;
                        }
                    }catch (\Exception $ex){
                        Db::rollback();
                        Utils::L('jsfypay', [$ex->getMessage(), $ex->getTraceAsString()]);
                        //return false;
                    }
                }
            }


        }
    }

    /**负盈模式分佣（内部代理）
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-22 2:57
     */
    private static function jsfypay_month($jsdt=null){
        $fyauto=intval(env('FYFY_AUTO_COMMISSION_SELF_PROXY',0));//内部代理是否自动分佣
        if (!in_array($fyauto,[0,1])) $fyauto=0;
//自动分佣判断上月最后一天统计是否已完成，完成则分佣
        if ($fyauto>0){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm=strtotime(date("Y-m-01 00:00:00",$pst))-1;
            $dt=date("Ymd",$ystm);
            $dm=date("Ym",$ystm);
            //判断上月是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            if (count($rlt)>0){
                    $typesql=" sff_utype>1 and ";
                //判断上周是否已结算（只要有一笔支付则表示上月已结算）
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_m=$dm and sff_payed=2 limit 1");
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        $cjset=SysSets::getSetByCode("sys_cj_support");
                        $cjset=($cjset==1);//是否支持彩金
                        $paytocjset=SysSets::getSetByCode("sys_cj_fypay");
                        $paytocjset=($paytocjset==1);//返佣是否发放到彩金
                        $watertype=($cjset && $paytocjset)?101:3;//流水记录类型值
                        $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';//更新余额字段
                        $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';//更新流水字段
                        $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';//变帐记录表名
                        $flag = false;
                        //变更发放状态
                        $sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=if(sff_fy>0,$st,0)
where $typesql sff_m=$dm and sff_payed=1";
                        $flag = Db::execute($sql);
                        if ($flag) {
                            //增加用户余额 和 savings

                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,1 as multiple,sff_fy as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_m=$dm and sff_fy>0 and sff_payed=2";
                                $flag = Db::execute($sql);
                                Utils::L('jsfypay_water', ["写入流水记录", $flag, $sql]);

                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy
where $typesql sff_uid=id and sff_m=$dm and sff_fy>0 and sff_payed=2";
                            $flag = Db::execute($sql);
                            //更新变账记录
                            $sql = "insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sff_uid,{$fieldban}-sff_fy,{$fieldban},sff_fy,6,concat(sff_m,'代理返佣发放') 
from dq_third_stats_fyfy,dq_user
where $typesql sff_uid=id and sff_m=$dm and sff_fy>0 and sff_payed=2" ;
                            $flag = Db::execute($sql);
                        }

                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                            //return false;
                        }
                    }catch (\Exception $ex){
                        Db::rollback();
                        Utils::L('jsfypay', [$ex->getMessage(), $ex->getTraceAsString()]);
                        //return false;
                    }
                }
            }


        }
    }

    private static function jsfypay_week_out($jsdt=null){
        $rd=(new ApiRedis())->systemInfo();
        Utils::L('jsfypay',["是否自动分佣",$rd['auto_commission']]);
        //$rd['auto_commission']=1;
        $autopay=($rd['auto_commission']==1);//自动分佣 仅分用户代理  后加：内部代理也一并分佣
//自动分佣判断上周最后一天统计是否已完成，完成则分佣
        if ($autopay){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm= Utils::nextweek($pst,-1);
            $dw=$ystm['nextyw'];//上周年周数
            //$dm=date("Ym",$ystm['nextys']);//上周所在月份，以周一所在为准
            $dt=date("Ymd",$ystm['nextye']);//上周最后一天
            //判断上周是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            Utils::L('jsfypay',["判断上期是否统计完毕",$rlt]);
            if (count($rlt)>0){
                    $typesql=" sff_utype=1 and ";
                //判断上月是否已结算（只要有一笔支付则表示上月已结算）
                //$rlt=Db::query("select * from dq_third_stats_fyfy where sff_m=$dm and sff_utype=1 and sff_payed=2 limit 1");
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_w=$dw and sff_payed=2 limit 1");
                Utils::L('jsfypay',["查询上期结算状态",$rlt]);
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        JsTask::limitOutsideFypay("sff_w=$dw",$ystm['nextys'], $ystm['nextye']);
                        $cjset=SysSets::getSetByCode("sys_cj_support");
                        $cjset=($cjset==1);//是否支持彩金
                        $paytocjset=SysSets::getSetByCode("sys_cj_fypay");
                        $paytocjset=($paytocjset==1);//返佣是否发放到彩金
                        $watertype=($cjset && $paytocjset)?101:3;//流水记录类型值
                        $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';//更新余额字段
                        $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';//更新流水字段
                        $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';//变帐记录表名
                        $flag = false;
                        //变更发放状态
                        /*$sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=$st
where sff_utype=1 and sff_m=$dm and sff_fy>0 and sff_payed=1";*/
                        $sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=if(sff_fy>0,$st,0)
where $typesql sff_w=$dw and sff_payed=1";
                        $flag = Db::execute($sql);
                        Utils::L('jsfypay',["变更发放状态",$flag,$sql]);
                        if ($flag) {
                            //增加用户余额 和 savings
                            /*$sql = "update dq_third_stats_fyfy,dq_user
set balance=balance+sff_fy,savings=savings+sff_fy
where sff_uid=id and sff_utype=1 and sff_m=$dm and sff_fy>0";*/
                            $savingRate=isset($rd['FySavingRateOut'])?floatval($rd['FySavingRateOut']):1;
                            if ($savingRate<0) $savingRate=0;
                            if ($savingRate>0) {
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,{$savingRate} as multiple,sff_fy*{$savingRate} as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_w=$dw and sff_fy>0 and sff_payed=2";
                                $flag = Db::execute($sql);
                                Utils::L('jsfypay_water', ["写入流水记录", $flag, $sql]);
                            }
                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy*{$savingRate}
where $typesql sff_uid=id and sff_w=$dw and sff_fy>0 and sff_payed=2";
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay',["增加余额和更新saving",$flag,$sql]);
                            //更新变账记录
                            /*$sql = "insert into dq_user_balance_mx (add_time,uid,old_price,new_price,price,type,msg)
select
$st,sff_uid,balance-sff_fy,balance,sff_fy,6,concat(sff_m,'代理返佣发放')
from dq_third_stats_fyfy,dq_user
where sff_uid=id and sff_utype=1 and sff_m=$dm and sff_fy>0" ;*/
                            $sql = "insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sff_uid,{$fieldban}-sff_fy,{$fieldban},sff_fy,6,concat(sff_m,'代理返佣发放') 
from dq_third_stats_fyfy,dq_user
where $typesql sff_uid=id and sff_w=$dw and sff_fy>0 and sff_payed=2" ;
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay',["写入变帐记录",$flag,$sql]);
                        }

                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                            //return false;
                        }
                    }catch (\Exception $ex){
                        Db::rollback();
                        Utils::L('jsfypay', [$ex->getMessage(), $ex->getTraceAsString()]);
                        //return false;
                    }
                }
            }


        }
    }

    private static function jsfypay_week($jsdt=null){
        $fyauto=intval(env('FYFY_AUTO_COMMISSION_SELF_PROXY',0));//内部代理是否自动分佣
        if (!in_array($fyauto,[0,1])) $fyauto=0;
//自动分佣判断上周最后一天统计是否已完成，完成则分佣
        if ($fyauto>0){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm= Utils::nextweek($pst,-1);
            $dw=$ystm['nextyw'];//上周年周数
            //$dm=date("Ym",$ystm['nextys']);//上周所在月份，以周一所在为准
            $dt=date("Ymd",$ystm['nextye']);//上周最后一天
            //判断上周是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            Utils::L('jsfypay',["判断上期是否统计完毕",$rlt]);
            if (count($rlt)>0){
                    $typesql=" sff_utype>1 and ";

                //判断上月是否已结算（只要有一笔支付则表示上月已结算）
                //$rlt=Db::query("select * from dq_third_stats_fyfy where sff_m=$dm and sff_utype=1 and sff_payed=2 limit 1");
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_w=$dw and sff_payed=2 limit 1");
                Utils::L('jsfypay',["查询上期结算状态",$rlt]);
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        $cjset=SysSets::getSetByCode("sys_cj_support");
                        $cjset=($cjset==1);//是否支持彩金
                        $paytocjset=SysSets::getSetByCode("sys_cj_fypay");
                        $paytocjset=($paytocjset==1);//返佣是否发放到彩金
                        $watertype=($cjset && $paytocjset)?101:3;//流水记录类型值
                        $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';//更新余额字段
                        $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';//更新流水字段
                        $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';//变帐记录表名
                        $flag = false;
                        //变更发放状态
                        /*$sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=$st
where sff_utype=1 and sff_m=$dm and sff_fy>0 and sff_payed=1";*/
                        $sql = "update dq_third_stats_fyfy
set sff_payed=2,sff_paytime=if(sff_fy>0,$st,0)
where $typesql sff_w=$dw and sff_payed=1";
                        $flag = Db::execute($sql);
                        Utils::L('jsfypay',["变更发放状态",$flag,$sql]);
                        if ($flag) {
                            //增加用户余额 和 savings
                            /*$sql = "update dq_third_stats_fyfy,dq_user
set balance=balance+sff_fy,savings=savings+sff_fy
where sff_uid=id and sff_utype=1 and sff_m=$dm and sff_fy>0";*/
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,1 as multiple,sff_fy as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_w=$dw and sff_fy>0 and sff_payed=2";
                                $flag = Db::execute($sql);
                                Utils::L('jsfypay_water', ["写入流水记录", $flag, $sql]);

                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy
where $typesql sff_uid=id and sff_w=$dw and sff_fy>0 and sff_payed=2";
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay',["增加余额和更新saving",$flag,$sql]);
                            //更新变账记录
                            /*$sql = "insert into dq_user_balance_mx (add_time,uid,old_price,new_price,price,type,msg)
select
$st,sff_uid,balance-sff_fy,balance,sff_fy,6,concat(sff_m,'代理返佣发放')
from dq_third_stats_fyfy,dq_user
where sff_uid=id and sff_utype=1 and sff_m=$dm and sff_fy>0" ;*/
                            $sql = "insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sff_uid,{$fieldban}-sff_fy,{$fieldban},sff_fy,6,concat(sff_m,'代理返佣发放') 
from dq_third_stats_fyfy,dq_user
where $typesql sff_uid=id and sff_w=$dw and sff_fy>0 and sff_payed=2" ;
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay',["写入变帐记录",$flag,$sql]);
                        }

                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                            //return false;
                        }
                    }catch (\Exception $ex){
                        Db::rollback();
                        Utils::L('jsfypay', [$ex->getMessage(), $ex->getTraceAsString()]);
                        //return false;
                    }
                }
            }


        }
    }

}
