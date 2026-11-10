<?php


namespace app\common\FastGame\Base;


use app\api\redis\ApiRedis;
use app\common\model\GameBetsModel;
use app\model\SysSets;
use think\Exception;
use think\facade\Db;

/**
 * 三方平台统一结算任务
 * Class JsTask
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-09-17 12:22
 */
abstract class JsTask
{

/*
 * 20240921新增支持报表有效流水的两种方式，一种是直接按投注金额，一种是按有效流水（派彩金额>投注金额?投注金额:投注金额-派彩金额）
 * 新增ENV：
 * ;有效流水结算模式，0表示按有效流水来，默认，1表示按投注金额来
 * VALIDWATER_MODE=0
 *
 *
 * ==================================================
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

;分佣结算周期模式 w/W表示按周结算，m/M表示按月结算
FYFY_BILLING_CYCEL=w
;分佣结算周期变更开始时间戳 0表示按最早时间或自然周期开始时间 指定时间戳表示按指定时间开始，用于在改变结算周期方式后避免重复统计(此种情况下一定要注意设置的时间戳值必须是更改结算模式的当天)
;且更改模式时一定要保证前一天的结算日志记录已经存在
FYFY_BILLING_START_TIME=0

;内部代理是否自动发放分佣 0 不自动 1自动
FYFY_AUTO_COMMISSION_SELF_PROXY=0

20230612 优化时区导致统计数据不一致的问题，修复综合统计表BUG
;mysql时区设置，请使用=-时区方式字符串
mysql_timezone=-3:00
ALTER TABLE dq_third_stats_global_temp
DROP COLUMN su_proxy,
DROP COLUMN su_proxytop;
ALTER TABLE dq_third_stats_global
DROP COLUMN su_proxy,
DROP COLUMN su_proxytop;

20230615内部代理扣减下级外部代理分佣支持
env + 在FYFY_BILLING_START_TIME的下面
;内部代理分佣是否扣减下级外部代理的分佣 default 0 不扣减 1扣减
FYFY_SUB_OUTSIDE_MONEY=1

ALTER TABLE `dq_user_agent`
ADD INDEX(`type`);

ALTER TABLE `dq_third_stats_fyfy`
ADD COLUMN `sff_subout` decimal(20, 2) NULL DEFAULT 0 COMMENT '扣减内部代理下级外部代理分佣金额' AFTER `sff_exinf`;

CREATE TABLE `dq_third_stats_fyfyinslog` (
  `sfil_dm` int(255) DEFAULT NULL COMMENT '分佣周期月存放在这里',
  `sfil_dw` int(255) DEFAULT NULL COMMENT '分佣周期日周存放在这里',
  `sfil_upproxyid` int(11) NOT NULL DEFAULT '0' COMMENT '上级内部代理ID，0表示为顶级',
  `sfil_proxyid` int(11) NOT NULL DEFAULT '0' COMMENT '记录所属内部代理ID',
  `sfil_downproxyid` int(11) NOT NULL DEFAULT '0' COMMENT '下级内部代理ID，0表示是叶子节点',
  `sfil_subproxyid` int(11) NOT NULL DEFAULT '0' COMMENT '扣减的叶子节点内部代理ID，0表示下面是下级外部代理',
  `sfil_baserate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '内部代理基础分佣比例',
  `sfil_subrate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '下级内部代理分佣比例，0表示没有下级内部代理',
  `sfil_subnum` int(11) NOT NULL DEFAULT '0' COMMENT '扣减汇总记录条数，即扣减了多少人的分佣',
  `sfil_basefy` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '线下外部代理分佣金额汇总（非叶子节点排除了下级内部代理的线下外部代理）',
  `sfil_realrate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '内部代理实际承担的扣减比例',
  `sfil_subfy` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '内部代理实际承担的扣减金额',
  `sfil_proxytype` int(255) NOT NULL DEFAULT '0' COMMENT '0根代理 1中间代理 2叶子代理',
  `sfil_cntflag` int(255) DEFAULT '0' COMMENT '统计标志位，为0表示未统计',
  KEY `sfil_proxyid` (`sfil_proxyid`) USING BTREE,
  KEY `sfil_subproxyid` (`sfil_downproxyid`) USING BTREE,
  KEY `sfil_proxytype` (`sfil_proxytype`) USING BTREE,
  KEY `sfil_dm` (`sfil_dm`),
  KEY `sfil_dw` (`sfil_dw`),
  KEY `sfil_dt` (`sfil_proxyid`,`sfil_downproxyid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内部代理分佣扣减下级外部代理分佣记录表';

CREATE TABLE `dq_third_stats_fyfyinslog_outproxy` (
  `sfilt_dm` int(255) NOT NULL COMMENT '数据所在月份，用于分表',
  `sfilt_cycle` int(25) NOT NULL COMMENT '统计周期',
  `sfilt_outproxyid` int(11) NOT NULL COMMENT '外部代理ID（不分级）',
  `sfilt_proxyid` int(11) NOT NULL DEFAULT '0' COMMENT '所属内部代理ID',
  `sfilt_proxyupid` int(11) NOT NULL COMMENT '所属内部代理的上级ID',
  `sfilt_proxytype` int(255) NOT NULL DEFAULT '0' COMMENT '0根代理 1中间代理 2叶子代理',
  `sfilt_proxyrate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '所属内部代理分佣比例',
  `sfilt_fy` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '外部代理分佣',
  `sfilt_cntflag` int(255) NOT NULL COMMENT '批量统计标志，对业务查询无用',
  UNIQUE KEY `sfilt_cycle` (`sfilt_cycle`,`sfilt_outproxyid`),
  KEY `sfilt_proxyid` (`sfilt_proxyid`),
  KEY `sfilt_proxytype` (`sfilt_proxytype`),
  KEY `sfilt_outproxyid` (`sfilt_outproxyid`),
  KEY `sfilt_cntflag` (`sfilt_cycle`,`sfilt_cntflag`) USING BTREE,
  KEY `sfilt_proxyupid` (`sfilt_proxyupid`),
  KEY `sfilt_dm` (`sfilt_dm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内部代理分佣扣减下级外部代理分佣记录表之代理贡献记录表';

20230703增加流水明细
ALTER TABLE `dq_third_stats_back`
ADD INDEX(`sb_backmoney`);
ALTER TABLE `dq_third_stats_fyfy`
ADD INDEX(`sff_fy`);

20230713 迁移game_bets表betinfo字段，增加冗余的统计有效流水和输赢金额，并进行配套更改
ALTER TABLE `dq_game_bets`
DROP COLUMN `gb_betinfo`,
ADD COLUMN `gb_otherwin` decimal(10, 2) NULL DEFAULT 0 COMMENT '统计输赢金额（派彩-投注）' AFTER `gb_cusfield`,
ADD COLUMN `gb_validwater` decimal(10, 2) NULL DEFAULT 0 COMMENT '统计有效流水 if(派彩>投注，投注，投注-派彩）' AFTER `gb_otherwin`;
CREATE TABLE `dq_game_betsex` (
  `gbe_id` int(11) NOT NULL COMMENT '注单ID',
  `gbe_inmonth` int(11) DEFAULT NULL COMMENT '切表所属月份',
  `gbe_betinfo` text COMMENT '注单原始信息',
  PRIMARY KEY (`gbe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='注单扩展信息表';
ALTER TABLE `dq_third_stats_users`
ADD COLUMN `su_validwater` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效流水' AFTER `su_draw`;
ALTER TABLE `dq_third_stats_users_temp`
ADD COLUMN `su_validwater` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注额' AFTER `su_draw`;

20231008 增加特定活动流水扣减
CREATE TABLE `dq_user_award_cfgs` (
  `uac_id` int(11) NOT NULL AUTO_INCREMENT,
  `uac_awardid` int(11) NOT NULL COMMENT '优惠类型ID',
  `uac_uid` int(11) NOT NULL COMMENT '用户ID',
  `uac_intime` int(11) NOT NULL COMMENT '奖励获得时间',
  `uac_money` decimal(10,4) DEFAULT '0.0000' COMMENT '奖励金额',
  `uac_rate` decimal(10,4) DEFAULT '1.0000' COMMENT '要求流水倍数',
  `uac_water` decimal(10,4) DEFAULT '0.0000' COMMENT '要求流水（冗余）',
  `uac_waterdone` decimal(10,4) DEFAULT '0.0000' COMMENT '已完成流水',
  `uac_gameids` varchar(4000) DEFAULT '' COMMENT '指定游戏统计流水的游戏IDS，用逗号隔开',
  `uac_done` int(255) DEFAULT '0' COMMENT '流水完成状态 0 未完成  |完成时间戳',
  `uac_payed` int(255) DEFAULT '0' COMMENT '奖励发放状态 0 未发放 1已发放 2不予发放',
  `uac_paytime` int(11) DEFAULT '0' COMMENT '奖励发放时间',
  PRIMARY KEY (`uac_id`),
  KEY `uac_awardid` (`uac_awardid`),
  KEY `uac_uid` (`uac_uid`),
  KEY `uac_waterdone` (`uac_waterdone`),
  KEY `uac_done` (`uac_done`),
  KEY `uac_paytime` (`uac_paytime`),
  KEY `uac_payed` (`uac_payed`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='指定游戏流水获得奖励用户记录表（完成可获得奖励或允许提现）';

CREATE TABLE `dq_user_award_logs` (
  `ual_uacid` int(11) NOT NULL COMMENT '记录ID',
  `ual_awardid` int(11) NOT NULL COMMENT '活动ID',
  `ual_uid` int(11) NOT NULL COMMENT '用户ID',
  `ual_gid` int(11) NOT NULL COMMENT '游戏ID',
  `ual_subwater` decimal(10,4) DEFAULT '0.0000' COMMENT '此游戏扣减（贡献）流水',
  UNIQUE KEY `ual_uacid` (`ual_uacid`,`ual_uid`,`ual_gid`),
  KEY `ual_uid` (`ual_uid`,`ual_gid`),
  KEY `ual_gid` (`ual_gid`),
  KEY `ual_awardid` (`ual_awardid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='指定奖励活动流水扣减中间表';

CREATE TABLE `dq_user_award_temp` (
  `uat_uid` int(11) NOT NULL,
  `uat_gid` int(11) NOT NULL,
  `uat_vaildwater` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '阶段有效流水（剩余）',
  `uat_subwater` decimal(10,4) DEFAULT '0.0000' COMMENT '单次统计扣减额中间结果（避免多表更新时前字段值计算结果会影响后值）',
  UNIQUE KEY `uat_uid` (`uat_uid`,`uat_gid`),
  KEY `uat_gid` (`uat_gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='指定活动流水统计临时表';


==============================20231227 增加多时区统计功能
sysconfig add:
;统计数据时区列表，格式：时区标志|时区+-值，每一个时区都会生成一张后缀为时区代码的表（包括切表） 默认表不带后缀，不设置此配置时DEFAULT的时候按env.mysql_timezone，+0：00顺序取值，设置时按env.mysql_timezone,此设置值顺序取值
;如设置此配置则DEFAULT必须定义
COUNT_DATA_TIMEZONE_LIST=DEFAULT|+0:00,TOKYO|+9:00,KOLKATA|+5:30 ;type:list

sql:
create table dq_third_stats_users_temp_js like dq_third_stats_users_temp;
create table dq_third_stats_global_temp_js like dq_third_stats_global_temp;

ALTER TABLE `dq_user_award_temp` CHANGE `uat_vaildwater` `uat_vaildwater` DECIMAL(20,4) NOT NULL DEFAULT '0.0000' COMMENT '阶段有效流水（剩余）';
ALTER TABLE `dq_user_award_temp` CHANGE `uat_subwater` `uat_subwater` DECIMAL(20,4) NULL DEFAULT '0.0000' COMMENT '单次统计扣减额中间结果（避免多表更新时前字段值计算结果会影响后值）';

20231012 双钱包
ALTER TABLE `dq_user`
ADD COLUMN `cjsavings` decimal(20, 2) NULL DEFAULT 0 COMMENT '彩金流水要求' AFTER `is_usdt`,
ADD COLUMN `cjbalance` decimal(20, 2) NULL DEFAULT 0 COMMENT '彩金钱包余额' AFTER `cjsavings`;

ALTER TABLE `dq_games`
ADD COLUMN `g_cj` tinyint(255) NULL DEFAULT 0 COMMENT '是否支持彩金投注 0 双钱包支持 1仅支持现金钱包 2仅支持彩金钱包' AFTER `g_jackpot`;

ALTER TABLE `dq_game_bets`
MODIFY COLUMN `gb_price` decimal(20, 2) NOT NULL COMMENT '投注金额（包含彩金）' AFTER `gb_intime`,
ADD COLUMN `gb_cjprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额（彩金部分）' AFTER `gb_price`,
MODIFY COLUMN `gb_winprice` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '赢的金额（包含彩金）' AFTER `gb_overtime`,
ADD COLUMN `gb_wincjprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）' AFTER `gb_winprice`;

CREATE TABLE `dq_user_cjbalance_mx` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `add_time` bigint(20) DEFAULT '0' COMMENT '添加时间',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `old_price` decimal(20,2) DEFAULT '0.00' COMMENT '旧的金额记录',
  `new_price` decimal(20,2) DEFAULT '0.00' COMMENT '新的金额记录',
  `price` decimal(20,2) DEFAULT '0.00' COMMENT '交易金额',
  `type` int(11) DEFAULT '1' COMMENT '1是增加2减少',
  `msg` varchar(100) DEFAULT NULL COMMENT '备注',
  `source_id` int(11) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_uid` (`uid`) USING BTREE,
  KEY `price` (`price`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=43668980 DEFAULT CHARSET=utf8 COMMENT='用户彩金明细';

-- 此段暂时不需要，后续合并jackpot功能后再统一改
ALTER TABLE `dq_third_stats_users`
ADD COLUMN `su_betcj` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注彩金金额' AFTER `su_validwater`,
ADD COLUMN `su_wincj` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢彩金金额' AFTER `su_betcj`;
ALTER TABLE `t12`.`dq_third_stats_users_temp`
ADD COLUMN `su_betcj` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注彩金金额' AFTER `su_validwater`,
ADD COLUMN `su_wincj` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢彩金金额' AFTER `su_betcj`;
-- 此段结束

*/
    public static function getcasejsnumber($d,$num){
        $number=0;
        for($i=0;$i<count($d)-1;$i++){
            if ($d[$i]['amount']<=$num){
                $number+=$d[$i]['add'];
            }
        }
        return $number;
    }

    /** 生成返水的计算SQL
     * @param $gid
     * @return string
     * @author twt326
     * @date 2022-10-20 11:41
     */
    public static function getbackcasesql($gtid){
        $sql="";
        $sql1="";
        $lc=Db::query("select gbc_viplvl as lvl,gbc_rate as rate from dq_game_back_config where gbc_gtid=$gtid order by gbc_viplvl");

        if (count($lc)>0){
            for($i=0;$i<count($lc);$i++){
                $sql.=" when ".$lc[$i]['lvl']." then sb_backbase * ".$lc[$i]['rate'];
                $sql1.=" when ".$lc[$i]['lvl']." then ".$lc[$i]['rate'];
            }
        }else{
            $sql=" when 0 then 0 ";//没有配置的时候直接返回0
            $sql1=" when 0 then 0";
        }
        return [$sql,$sql1];
    }

    /** 根据时间字段和开始结束时间获取最大和最小注单ID，用于提供查询效率
     * @param $ds 开始时间
     * @param $de 结束时间
     * @param $timefield 时间字段
     * @param $cutex 分表后缀
     * @return array
     */
    public static function getBetsIdRange($ds,$de,$timefield='gb_overtime',$cutex=''){
        $sql="select min(gb_id) as min_id,max(gb_id) as max_id  from dq_game_bets{$cutex} where {$timefield} BETWEEN {$ds} and {$de}";
        $rlt=Db::query($sql);
        $min_id=0;
        $max_id=0;
        if ($rlt && count($rlt)==1){
            $min_id=$rlt[0]['min_id']??$min_id;
            $max_id=$rlt[0]['max_id']??$max_id;
        }
        return ["max"=>$max_id,"min"=>$min_id];
    }

    /** 结算返水
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后用户有效情况，三方平台有效情况，游戏有效情况发生变化 ，比如某用户失效或某游戏失效，那补结算的数据不包括失效用户和失效游戏，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     * 20230705优化-更改为分批次
     */
    public static function jsback($jsdt=null){
        Utils::L('jsback',["准备结算 $jsdt 返水"]);
        $rd=(new ApiRedis())->systemInfo();
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        $autopay=($rd['auto_water']==1);//根据配置决定是否自动返水
        //判断昨日返水是否已生成
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return false;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        $dt=date("Ymd",$yst);
        $dse=date("Y-m-d",$yst);
        $ds=strtotime($dse." 00:00:00");
        $de=$ds+86400-1;
        Utils::L('jsback',[$pst,$yst,$dt,$dse,$ds,$de]);

        $rlt=Db::query('select sb_dt from dq_third_stats_back where sb_dt='.$dt.' limit 1');
        if (count($rlt)==0){
            $flag=false;
            Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
            Db::startTrans();
            try {

                //写入需要返水的用户及投注额
                /*$sql="insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_lose,sb_losenum,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum(gb_validwater) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games,dq_third_configs
where gb_uid=id and gb_gid=g_id and g_tcid=tc_id and tc_status=1 and gb_bettime BETWEEN $ds and $de and gb_winlose>1 and g_state=1 and g_backstatus=1 and status=1 and lebal=1
group by gb_uid,g_type";*/
                /*$sql="insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_lose,sb_losenum,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum(gb_validwater) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games,dq_third_configs
where gb_uid=id and gb_gid=g_id and g_tcid=tc_id and gb_bettime BETWEEN $ds and $de and gb_winlose>1 and g_backstatus=1 and lebal=1
group by gb_uid,g_type";*/
                list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($ds,$de);
                /*统计移到写注单时实时进行了$sql="update dq_game_bets
set gb_otherwin=gb_winprice-gb_price,
gb_validwater=if(gb_winprice>gb_price,gb_price,gb_price-gb_winprice)
where gb_id between {$min_id} and {$max_id} and gb_bettime BETWEEN {$ds} and {$de} and gb_winlose>1";
                $flag=Db::execute($sql);
                Utils::L('jsback',["统计输赢和有效投注额",$flag,$sql]);
                if ($flag) {*/
                    //新的返水统计方法弃用一些字段
                    /*$sql = "insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_lose,sb_losenum,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum(gb_validwater) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games
where gb_uid=id and gb_gid=g_id and gb_id between {$min_id} and {$max_id} and gb_bettime BETWEEN {$ds} and {$de} and gb_winlose>1 and g_backstatus=1 and lebal=1
group by gb_uid,g_type";*/
                    $sql = "insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum({$validWaterField}) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games
where gb_uid=id and gb_gid=g_id and gb_id between {$min_id} and {$max_id} and gb_bettime BETWEEN {$ds} and {$de} and gb_winlose>1 and g_backstatus=1 and lebal=1
group by gb_uid,g_type";
                    $flag = Db::execute($sql);
                    Utils::L('jsback', ["写入返水用户及投注额", $flag, $sql]);
                //}
                if ($flag){
                    //根据游戏类型组织SQL更新返水金额(循环)
                    $sql="select distinct sb_gtid from dq_third_stats_back where sb_dt=$dt";
                    $rlt=Db::query($sql);
                    Utils::L('jsback',["根据游戏类型组织SQL更新返水金额(循环)",$rlt,$sql]);
                    foreach ($rlt as $k=>$v){
                        //获取游戏返佣配置
                        //生成case语句
                        //更新返佣金额
                        /*
                       when 1 then sb_backbase*0.02
	when 2 then sb_backbase*0.03*/
                        $casesql=self::getbackcasesql($v['sb_gtid']);
                        $sql="update 
dq_third_stats_back 
set sb_backmoney=(
	case sb_viplvl
	".$casesql[0]."
	else
	0
	end
),sb_backrate=(
	case sb_viplvl
	".$casesql[1]."
	else
	0
	end
)
where sb_gtid=".$v['sb_gtid']." and sb_dt=$dt";
                        $rlt=Db::execute($sql);
                        Utils::L('jsback',["更新返佣金额",$rlt,$sql]);
                    }
                }
                if ($flag) {
                    Db::commit();
                } else {
                    Db::rollback();
                    //return false;
                }
                Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
                if ($autopay){//如果自动发放
                    Db::startTrans();
                    //判断是否已发放 -- 由于是后续流程，不需要判断，但如果移出单儿封装处理时必须要进行判断
                    $sql="select distinct sb_gtid from dq_third_stats_back where sb_dt=$dt";
                    $rlt=Db::query($sql);
                    Utils::L('jsback',["判断是否已发放",$rlt,$sql]);
                    $cjset=SysSets::getSetByCode("sys_cj_support");
                    $cjset=($cjset==1);//是否支持彩金
                    $paytocjset=SysSets::getSetByCode("sys_cj_backpay");
                    $paytocjset=($paytocjset==1);//返水是否发放到彩金
                    foreach ($rlt as $k=>$v){
                        $flag=false;
                        //变更记录为已返水
                        $sql="update dq_third_stats_back
set sb_payed=2,sb_paytime=$st
where sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                        $flag=Db::execute($sql);
                        Utils::L('jsback',["变更记录为已返水",$flag,$sql]);
                        if ($flag){
                            //增加用户余额 和 savings
                            //20230703增加流水倍率配置支持
                            $savingRate=isset($rd['BackSavingRate'])?floatval($rd['BackSavingRate']):1;
                            if ($savingRate<0) $savingRate=0;
                            if ($savingRate>0) {
                                //写入流水记录
                                $watertype=($cjset && $paytocjset)?101:1;
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time) 
select sb_uid as uid,sb_backmoney as amount,{$savingRate} as multiple,sb_backmoney*{$savingRate} as water,sb_id as origin_id,{$watertype} as type,concat(sb_dt,'日返水加流水') as remark,$st as create_time from dq_third_stats_back
where sb_dt=$dt and sb_backmoney>0 and sb_gtid=" . $v['sb_gtid'];
                                $rlt = Db::execute($sql);
                                Utils::L('jsback', ["写入流水记录", $rlt, $sql]);
                            }
                            //更新余额和流水
                            $fieldban=($cjset && $paytocjset)?'cjbalance':'balance';
                            $fieldsav=($cjset && $paytocjset)?'cjsavings':'savings';
                            $sql="update dq_third_stats_back,dq_user
set {$fieldban}={$fieldban}+sb_backmoney,{$fieldsav}={$fieldsav}+sb_backmoney*{$savingRate}
where sb_uid=id and sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                            $rlt=Db::execute($sql);
                            Utils::L('jsback',["增加用户余额和savings",$rlt,$sql]);
                            //更新变账记录
                            $tablename=($cjset && $paytocjset)?'dq_user_cjbalance_mx':'dq_user_balance_mx';
                            $sql="insert into {$tablename} (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sb_uid,{$fieldban}-sb_backmoney,{$fieldban},sb_backmoney,1,concat(sb_dt,'游戏[',sb_gtid,']类返水') 
from dq_third_stats_back,dq_user
where sb_uid=id and sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                            $rlt=Db::execute($sql);
                            Utils::L('jsback',["变更变帐记录",$rlt,$sql]);
                        }
                    }
                    if ($flag) {
                        Db::commit();
                    } else {
                        Db::rollback();
                        //return false;
                    }
                }
            }catch (\Exception $ex){
                Db::rollback();
                Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
                Utils::L('jsback',$ex->getMessage(), true, true);
                return false;
            }
        }
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        return true;
    }

    /** 结算返水
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后用户有效情况，三方平台有效情况，游戏有效情况发生变化 ，比如某用户失效或某游戏失效，那补结算的数据不包括失效用户和失效游戏，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    public static function jsback_old($jsdt=null){
        Utils::L('jsback',["准备结算 $jsdt 返水"]);
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        $rd=(new ApiRedis())->systemInfo();
        $autopay=($rd['auto_water']==1);//根据配置决定是否自动返水
        //判断昨日返水是否已生成
        $st=time();
        $pst=$jsdt==null?time():strtotime($jsdt);
        if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return false;//不能指定未来日期
        //$yst=time()-86400;
        $yst=$pst-86400;
        $dt=date("Ymd",$yst);
        $dse=date("Y-m-d",$yst);
        $ds=strtotime($dse." 00:00:00");
        $de=$ds+86400-1;
        Utils::L('jsback',[$pst,$yst,$dt,$dse,$ds,$de]);

        $rlt=Db::query('select sb_dt from dq_third_stats_back where sb_dt='.$dt.' limit 1');
        if (count($rlt)==0){
            $flag=false;
            Db::startTrans();
            try {
                //写入需要返水的用户及投注额
                /*$sql="insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_lose,sb_losenum,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum(gb_validwater) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games,dq_third_configs
where gb_uid=id and gb_gid=g_id and g_tcid=tc_id and tc_status=1 and gb_bettime BETWEEN $ds and $de and gb_winlose>1 and g_state=1 and g_backstatus=1 and status=1 and lebal=1
group by gb_uid,g_type";*/
                $sql="insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
select $dt as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
sum(gb_winprice) as sb_win,
sum(if(gb_winprice>0,1,0)) as sb_winnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
sum({$validWaterField}) as sb_backbase,
$st as sb_statstime
from dq_game_bets,dq_user,dq_games,dq_third_configs
where gb_uid=id and gb_gid=g_id and g_tcid=tc_id and gb_bettime BETWEEN $ds and $de and gb_winlose>1 and g_backstatus=1 and lebal=1
group by gb_uid,g_type";
                $flag=Db::execute($sql);
                Utils::L('jsback',["写入返水用户及投注额",$flag,$sql]);
                if ($flag){
                    //根据游戏类型组织SQL更新返水金额(循环)
                    $sql="select distinct sb_gtid from dq_third_stats_back where sb_dt=$dt";
                    $rlt=Db::query($sql);
                    Utils::L('jsback',["根据游戏类型组织SQL更新返水金额(循环)",$rlt,$sql]);
                    foreach ($rlt as $k=>$v){
                        //获取游戏返佣配置
                        //生成case语句
                        //更新返佣金额
                        /*
                       when 1 then sb_backbase*0.02
	when 2 then sb_backbase*0.03*/
                        $casesql=self::getbackcasesql($v['sb_gtid']);
                        $sql="update 
dq_third_stats_back 
set sb_backmoney=(
	case sb_viplvl
	".$casesql[0]."
	else
	0
	end
),sb_backrate=(
	case sb_viplvl
	".$casesql[1]."
	else
	0
	end
)
where sb_gtid=".$v['sb_gtid']." and sb_dt=$dt";
                        $rlt=Db::execute($sql);
                        Utils::L('jsback',["更新返佣金额",$rlt,$sql]);
                    }
                }
                if ($flag) {
                    Db::commit();
                } else {
                    Db::rollback();
                    //return false;
                }
                if ($autopay){//如果自动发放

                    Db::startTrans();
                    //判断是否已发放 -- 由于是后续流程，不需要判断，但如果移出单儿封装处理时必须要进行判断
                    $sql="select distinct sb_gtid from dq_third_stats_back where sb_dt=$dt";
                    $rlt=Db::query($sql);
                    Utils::L('jsback',["判断是否已发放",$rlt,$sql]);
                    foreach ($rlt as $k=>$v){
                        $flag=false;
                        //变更记录为已返水
                        $sql="update dq_third_stats_back
set sb_payed=2,sb_paytime=$st
where sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                        $flag=Db::execute($sql);
                        Utils::L('jsback',["变更记录为已返水",$flag,$sql]);
                        if ($flag){
                            //增加用户余额 和 savings
                            //20230703增加流水倍率配置支持
                            $savingRate=isset($rd['BackSavingRate'])?floatval($rd['BackSavingRate']):1;
                            if ($savingRate<0) $savingRate=0;
                            if ($savingRate>0) {
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time) 
select sb_uid as uid,sb_backmoney as amount,{$savingRate} as multiple,sb_backmoney*{$savingRate} as water,sb_id as origin_id,1 as type,concat(sb_dt,'日返水加流水') as remark,$st as create_time from dq_third_stats_back
where sb_dt=$dt and sb_backmoney>0 and sb_gtid=" . $v['sb_gtid'];
                                $rlt = Db::execute($sql);
                                Utils::L('jsback', ["写入流水记录", $rlt, $sql]);
                            }
                            //更新余额和流水
                            $sql="update dq_third_stats_back,dq_user
set balance=balance+sb_backmoney,savings=savings+sb_backmoney*{$savingRate}
where sb_uid=id and sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                            $rlt=Db::execute($sql);
                            Utils::L('jsback',["增加用户余额和savings",$rlt,$sql]);
                            //更新变账记录
                            $sql="insert into dq_user_balance_mx (add_time,uid,old_price,new_price,price,type,msg)
select 
$st,sb_uid,balance-sb_backmoney,balance,sb_backmoney,5,concat(sb_dt,'游戏[',sb_gtid,']类返水') 
from dq_third_stats_back,dq_user
where sb_uid=id and sb_dt=$dt and sb_gtid=".$v['sb_gtid'];
                            $rlt=Db::execute($sql);
                            Utils::L('jsback',["变更变帐记录",$rlt,$sql]);
                        }
                    }
                    if ($flag) {
                        Db::commit();
                    } else {
                        Db::rollback();
                        //return false;
                    }
                }
            }catch (\Exception $ex){
                Db::rollback();
                Utils::L('jsback',$ex->getMessage(), true, true);
                return false;
            }
        }
        return true;
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

    /** 统计分佣金额的SQL
     * @return array
     * @author twt326
     * @date 2022-10-28 23:02
     */
    public static function getfyfycasesql(){
        $sql="";
        $sql1="";
        $lc=Db::query("select tfc_amount as amount,tfc_validusers as unum,tfc_rate as rate from dq_third_fyfy_lvlconfig where tfc_type=2 order by tfc_amount desc,tfc_validusers desc");
        if (count($lc)>0){
            for($i=0;$i<count($lc);$i++){
                $lc[$i]['add']=0;
                if ($i>0){
                    $lc[$i-1]['add']=($lc[$i-1]['amount']-$lc[$i]['amount'])*$lc[$i]['rate'];
                }
            }
            for($i=0;$i<count($lc);$i++){
                $sql.=" when(sff_validuser>=".$lc[$i]['unum']." and sff_base>".$lc[$i]['amount'].") then (sff_base-".$lc[$i]['amount'].")*".$lc[$i]['rate']." +".self::getcasejsnumber($lc,$lc[$i]['amount']);
                $sql1.=" when(sff_validuser>=".$lc[$i]['unum']." and sff_base>".$lc[$i]['amount'].") then ".$lc[$i]['rate'];
            }
        }else{
            $sql=" when 0 then 0 ";//没有配置的时候直接返回0
            $sql1=" when 0 then 0";
        }
        return [$sql,$sql1];
    }
    
    /**
     * 重新生成参与数据统计的内部和外部代理树成员列表
     * TODO 未来看看在符合运营需求的情况下通过生成时间或是是否有变动来决定是否要重新生成，提升效率
     */
    public static function refreshProxyTrees(){
        //删除临时表数据
        $rlt=Db::execute("truncate table dq_temp_fyfy_proxy_users");
        Utils::L('refreshProxyTrees',["删除临时表数据",$rlt]);
        //找到外部代理和下级
        /*$sql="insert into dq_temp_fyfy_proxy_users
         select 0,ua.uid,1,ub.id,ub.xc_date
         from dq_user_agent ua
         left join dq_user ub on (ua.uid=ub.id or ua.uid=ub.rec_id) and ub.status=1 and ub.lebal=1
         where ua.type=0 and ua.status=3 and ub.id is not null";*/
        
        /*$sql="insert into dq_temp_fyfy_proxy_users
         select 0,ua.uid,1,ub.id,ub.xc_date
         from dq_user_agent ua
         left join dq_user ub on (ua.uid=ub.id or ua.uid=ub.rec_id) and ub.lebal=1
         where ua.type=0 and ua.status=3 and ub.id is not null";*/
        //代理统计时不包含自身
        $sql="insert into dq_temp_fyfy_proxy_users
select 0,ua.uid,1,ub.id,ub.xc_date,0
from dq_user_agent ua
left join dq_user ub on ua.uid=ub.rec_id and ub.lebal=1
where ua.type=0 and ua.status=3 and ub.id is not null";
        $rlt=Db::execute($sql);
        Utils::L('refreshProxyTrees',["写入外部代理树",$rlt,$sql]);
        //找内部代理树用户
        /*$sql="insert into dq_temp_fyfy_proxy_users
         select uaa.proxy_topuid,uaa.uid,0,ubb.id,ubb.xc_date
         from dq_user_agent uaa
         left join dq_user ubb on ubb.tree like concat(uaa.tree,'%') and ubb.status=1 and ubb.lebal=1
         where uaa.type=1 and uaa.status=3 and ubb.id is not null
         order by uaa.uid";*/
        /*$sql="insert into dq_temp_fyfy_proxy_users
         select uaa.proxy_topuid,uaa.uid,0,ubb.id,ubb.xc_date
         from dq_user_agent uaa
         left join dq_user ubb on ubb.tree like concat(uaa.tree,'%') and ubb.lebal=1
         where uaa.type=1 and uaa.status=3 and ubb.id is not null
         order by uaa.uid";*/
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


    /** 根据env配置决定是按月结算还是按周结算
     * @param null $jsdt
     * @author twt326
     * @date 2023-03-13 22:58
     */
    public static function jsfy($jsdt=null){
        $jsmod=env('FYFY_BILLING_CYCEL','W');
        $jsmod=strtoupper($jsmod);
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        switch ($jsmod){
            case 'M':
                self::jsfy_month($jsdt);
                break;
            default:
                self::jsfy_week($jsdt);
                break;
        }
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
    }

    /** 内部代理分佣按比例扣减下级外部代理分佣（顶级承担100%剩余全部）
     * 1.内部代理按周，外部代理按月，扣月尾在本周的外部代理分佣
    2.内部代理按月，外部代理按周，所有周日落在本月的外部代理分佣（因为这部分是统计完一周的数据）
    3.外部代理按日的，都根据内部代理的周期换算成日期段去统计扣减外部代理分佣
     * 4.内外部代理的周期一致的直接根据周期进行一对一扣
     * 建议运营方在设定周期的时候外部代理 的分佣周期不要大于内部代理的分佣周期，这样平台会有损失
     * @param $inmod 内部代理结算周期模式 W M
     * @param $outmode 外部代理结算周期模式 D W M
     * @param $cycle 内部代理结算周期标志 YYYYWW YYYYMM
     * @param $countdm 统计归属月份，用于分表
     * @return true|void
     */
    public static function subOutsideMoneyCount($inmod,$outmode,$cycle,$countdm){
        $run=intval(env('FYFY_SUB_OUTSIDE_MONEY',0));//是否扣减外部代理分佣
        Utils::L('jsfy',["是否扣减外部代理分佣",$run,"inmode"=>$inmod,"outmode"=>$outmode,"cycle"=>$cycle,"countdm"=>$countdm]);
        if ($run!=1) return true;
        $flag=false;
        $cycleflags=null;
        $cycleflage=null;
        $cyclesql=null;
        $cyclefield=$outmode=="M"?'sff_m':'sff_w';
        $logcyclefield=$inmod=="M"?'sfil_dm':'sfil_dw';
        $logcyclefieldOther=$inmod=="M"?'sfil_dw':'sfil_dm';
        //清除当期统计
        $rlt=Db::execute('delete from dq_third_stats_fyfyinslog_outproxy where sfilt_cycle='.$cycle);
        Utils::L('jsfy',["清除扣减下级外部代理统计日志-外部代理扣减统计表",$rlt]);
        if ($inmod=="M") {
            $rlt = Db::execute("delete from dq_third_stats_fyfyinslog where {$logcyclefield}=" . $cycle . " and {$logcyclefieldOther} is null");
        }else{
            $rlt = Db::execute("delete from dq_third_stats_fyfyinslog where {$logcyclefield}=" . $cycle);
        }
        Utils::L('jsfy',["清除扣减下级外部代理统计日志-内部代理扣减记录表",$rlt]);
        if ($inmod==$outmode){
            //如果内外部代理是一样的周期
            $cyclesql=$cyclefield."=".$cycle;
        }else{
            switch ($inmod){
                case 'W'://如果内部代理是按周结算的
                    $rlt=Utils::nextweek($cycle);
                    switch ($outmode){
                        case 'D'://如果外部代理是按日结算，则统计周一到周日的数据
                            $cycleflags=date("Ymd",$rlt['ys']);
                            $cycleflage=date("Ymd",$rlt['ye']);
                            $cyclesql=$cyclefield." between ".$cycleflags." and ".$cycleflage;
                            break;
                        case "M"://如果外部代理是按月结算，则统计月尾在本周的数据
                            $ms=date("m",$rlt['ys']);
                            $me=date("m",$rlt['ye']);
                            $mee=date("m",$rlt['ye']+1);//周末的第二天如果换月的话，也算这周
                            if ($ms!=$me || $ms !=$mee){
                                $ts=strtotime(date("Y-m-01",$rlt['ys']));
                                $cycleflags=date("Ymd",$ts);//月头
                                $cycleflage=date("Ymd",strtotime(date("Y-m-01 00:00:00",$ts+32*24*86400))-3600);//月尾
                                $cyclesql=$cyclefield." between ".$cycleflags." and ".$cycleflage;
                            }else{
                                $flag=true;
                            }
                            break;
                    }
                    break;
                case 'M'://如果内部代理是按月结算的
                    $ts=strtotime(substr($cycle,0,4)."-".substr($cycle,4,2).'-01');
                    $tse=strtotime(date("Y-m-01 00:00:00",$ts+32*24*86400))-3600;//月尾
                    switch ($outmode){
                        case 'D'://如果外部代理是按日结算，则统计1号到月尾的数据
                            $cycleflags=date("Ymd",$ts);//月头
                            $cycleflage=date("Ymd",$tse);//月尾
                            $cyclesql=$cyclefield." between ".$cycleflags." and ".$cycleflage;
                            break;
                        case "W"://如果外部代理是按周结算，则统计所有周末在本月的数据
                            $cycleflags=date("YW",$ts);
                            $week=Utils::nextweek($tse,-1);
                            if (date("m",$week['ye']) !=date("m",$tse)){//周末不在本月
                                $cycleflage=$week['nextyw'];
                            }else{
                                $cycleflage=date("YW",$tse);
                            }
                            $cyclesql=$cyclefield." between ".$cycleflags." and ".$cycleflage;
                            break;
                    }
                    break;
            }
        }
        Utils::L('jsfy',["得到统计外部代理的区间数据",$cycleflags,$cycleflage,$cyclefield,$logcyclefield,$logcyclefieldOther,$cyclesql,$flag]);
        if ($cyclesql==null && !$flag){
            return false;
        }
        if ($cyclesql!=null) {
            /*Db::startTrans();
            try {

                Db::commit();
            } catch (\Exception $ex) {
                Db::rollback();
                return false;
            }*/
            //1.统计所有最下级内部代理的下级外部代理（并综合出分佣金额，最最下级标志）
            $sql="insert into dq_third_stats_fyfyinslog_outproxy
select {$countdm} as sfilt_dm,{$cycle} as sfilt_cycle,te.outid as sfilt_outproxyid,te.inid as sfilt_proxyid,te.rec_id as sfilt_proxyupid,2 as sfilt_proxytype,te.rate as sfilt_proxyrate,sum(tf.sff_fy) as sfilt_fy,1 as sfilt_cntflag from (
select tc.uid as inid,td.uid as outid,tc.rec_id,tc.rate from (
select DISTINCT ta.uid,ta.rec_id,ta.tree,ta.rate  from dq_user_agent ta
left join dq_user_agent tb on ta.uid=tb.rec_id and tb.type=1 and tb.status=3
where ta.type=1 and tb.id is null and ta.rec_id>0 and ta.status=3
) tc 
left join dq_user_agent td on td.tree like concat(tc.tree,'%') and td.status=3 and td.type=0
) te,dq_third_stats_fyfy tf
where te.outid=tf.sff_uid and tf.{$cyclesql}
group by te.outid";
            $rlt=Db::execute($sql);
            Utils::L('jsfy',["1.统计所有最下级内部代理的下级外部代理（并综合出分佣金额，最最下级标志）",$sql,$rlt]);
            $cntflag=1;
            $cntflagup=2;
            $runflag=true;
            while($runflag) {
                //2.1逐级往上统计出内部代理的下级外部代理（排除已被统计的）
                $sql = "insert into dq_third_stats_fyfyinslog_outproxy
select {$countdm} as sfilt_dm,{$cycle} as sfilt_cycle,te.outid as sfilt_outproxyid,te.inid as sfilt_proxyid,te.rec_id as sfilt_proxyupid,if(te.rec_id=0,0,1) as sfilt_proxytype,te.rate as sfilt_proxyrate,sum(tf.sff_fy) as sfilt_fy,{$cntflagup} as sfilt_cntflag from (
select tc.uid as inid,td.uid as outid,tc.rate,tc.rec_id from (
select distinct sfilt_proxyupid from dq_third_stats_fyfyinslog_outproxy 
where sfilt_cycle={$cycle} and sfilt_cntflag={$cntflag}
) ta,dq_user_agent tc
left join dq_user_agent td on td.tree like concat(tc.tree,'%') and td.status=3 and td.type=0
left join dq_third_stats_fyfyinslog_outproxy tfo on tfo.sfilt_outproxyid=td.uid and tfo.sfilt_cycle={$cycle}
where ta.sfilt_proxyupid=tc.uid and tfo.sfilt_outproxyid is null
) te,dq_third_stats_fyfy tf
where te.outid=tf.sff_uid and tf.{$cyclesql}
group by te.outid,te.inid";
                $rlt = Db::execute($sql);
                Utils::L('jsfy', ["2.1逐级往上统计出内部代理的下级外部代理（排除已被统计的）", "cntflag"=>$cntflag,$sql, $rlt]);
                $runflag=$rlt>0;
                $cntflag++;
                $cntflagup++;
            }
            //2.2统计出内部顶级代理的下级外部代理（排除已统计的）
            $sql="insert into dq_third_stats_fyfyinslog_outproxy
select {$countdm} as sfilt_dm,{$cycle} as sfilt_cycle,te.outid as sfilt_outproxyid,te.inid as sfilt_proxyid,te.rec_id as sfilt_proxyupid,0 as sfilt_proxytype,te.rate as sfilt_proxyrate,sum(tf.sff_fy) as sfilt_fy,{$cntflagup} as sfilt_cntflag from (
select td.uid as outid,ta.uid as inid,ta.rec_id,ta.rate  from dq_user_agent ta
left join dq_user_agent td on td.tree like concat(ta.tree,'%') and td.status=3 and td.type=0
left join dq_third_stats_fyfyinslog_outproxy tfo on tfo.sfilt_outproxyid=td.uid and tfo.sfilt_cycle={$cycle}
where ta.type=1 and ta.rec_id=0 and ta.status=3 and tfo.sfilt_outproxyid is null
) te,dq_third_stats_fyfy tf
where te.outid=tf.sff_uid and tf.{$cyclesql}
group by te.outid";
            $rlt=Db::execute($sql);
            Utils::L('jsfy',["1.统计所有最下级内部代理的下级外部代理（并综合出分佣金额，最最下级标志）",$sql,$rlt]);

            //3.1将所有直接下级外部代理的扣减统计到日志表
            $subsql=$inmod=="M"?"null as sfil_dw":"sfilt_cycle as sfil_dw";
            $sql="insert into dq_third_stats_fyfyinslog (sfil_dm,sfil_dw,sfil_upproxyid,sfil_proxyid,sfil_downproxyid,sfil_subproxyid,sfil_baserate,sfil_subrate,sfil_subnum,sfil_basefy,sfil_realrate,sfil_subfy,sfil_proxytype,sfil_cntflag)
select sfilt_dm,{$subsql},sfilt_proxyupid,sfilt_proxyid,0 as downproxyid,0 as subproxyid,sfilt_proxyrate,0 as subrate,count(*) as subnum,
sum(sfilt_fy) as basefy,
if(sfilt_proxytype=0,1,sfilt_proxyrate) as realrate,
sum(sfilt_fy)*if(sfilt_proxytype=0,1,sfilt_proxyrate) as subfy,
sfilt_proxytype,1 as cntflag
 from dq_third_stats_fyfyinslog_outproxy
 where sfilt_cycle={$cycle}
 group by sfilt_proxyid";
            $rlt=Db::execute($sql);
            Utils::L('jsfy',["1.统计所有最下级内部代理的下级外部代理（并综合出分佣金额，最最下级标志）",$sql,$rlt]);
            $cntflag=1;
            $cntflagup=2;
            $runflag=true;
            while($runflag) {
                //3.2从内部代理叶子节点循环往上按比例扣减，直到没有变更记录为止
                $sql = "insert into dq_third_stats_fyfyinslog (sfil_dm,sfil_dw,sfil_upproxyid,sfil_proxyid,sfil_downproxyid,sfil_subproxyid,sfil_baserate,sfil_subrate,sfil_subnum,sfil_basefy,sfil_realrate,sfil_subfy,sfil_proxytype,sfil_cntflag)
select ta.sfil_dm,ta.sfil_dw,tb.rec_id as upproxyid,ta.sfil_upproxyid as proxyid,ta.sfil_proxyid as downproxyid,
if(ta.sfil_subproxyid=0,ta.sfil_proxyid,ta.sfil_subproxyid) as subproxyid,
tb.rate as baserate,ta.sfil_baserate as subrate,1 as subnum,
ta.sfil_basefy,if (tb.rec_id=0,1,tb.rate)-ta.sfil_baserate as realrate,
ta.sfil_basefy*(if (tb.rec_id=0,1,tb.rate)-ta.sfil_baserate) as subfy,
if(tb.rec_id=0,0,1) as proxytype,{$cntflagup} as cntflag
 from dq_third_stats_fyfyinslog ta
left join dq_user_agent tb on ta.sfil_upproxyid=tb.uid and tb.status=3 and tb.type=1
where sfil_dw={$cycle} and sfil_cntflag={$cntflag} and sfil_upproxyid>0 and tb.id is not null";
                $rlt = Db::execute($sql);
                Utils::L('jsfy', ["2.1逐级往上统计出内部代理的下级外部代理（排除已被统计的）", "cntflag"=>$cntflag,$sql, $rlt]);
                $runflag=$rlt>0;
                $cntflag++;
                $cntflagup++;
            }

        }
        return true;
    }

    /**
     *  更新内部代理下级分佣扣减信息到分佣表（未带事务）
     * @param $inmode
     * @param $cycle
     * @return int
     */
    public static function subOutsideMoneyToFyfy($inmode,$cycle){
        $run=intval(env('FYFY_SUB_OUTSIDE_MONEY',0));//是否扣减外部代理分佣
        if ($run!=1) return true;
        $ff=$inmode=='M'?'sfil_dm':'sfil_dw';
        $msqlff=$inmode=='M'?" and sfil_dw is null":'';
        $fs=$inmode=='M'?'sff_m':'sff_w';
        $msql=$inmode=='M'?" and tc.sff_w is null":'';
        $sql="update (
select sfil_proxyid,sum(sfil_subfy) as subfy from dq_third_stats_fyfyinslog
where {$ff}={$cycle} {$msqlff}
group by sfil_proxyid
) tb,dq_third_stats_fyfy tc
set tc.sff_subout=tb.subfy,tc.sff_fy=tc.sff_fy-tb.subfy
where tb.sfil_proxyid=tc.sff_uid and tc.{$fs}={$cycle}".$msql;
        $rlt=Db::execute($sql);
        Utils::L('jsfy',["按比例扣减下级外部代理分佣",$inmode,$cycle,$sql,$rlt]);
        return $rlt;
    }

    /** 负盈分佣模式每日统计-按月
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    public static function jsfy_month($jsdt=null){
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //统计昨日返水
        if (self::jsback($jsdt)) {
            $rd=(new ApiRedis())->systemInfo();
            //判断昨日分佣是否已统计
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            //$yst=time()-86400;
            $yst=$pst-86400;
            $dt=date("Ymd",$yst);
            $dm=date("Ym",$yst);
            $dmyst=date('Ym',strtotime(date("Y-m-01 00:00:00",$yst))-1);//统计月份的上月，用于迁移上月负盈
            $dse=date("Y-m-d",$yst);
            $ds=strtotime($dse." 00:00:00");
            $de=$ds+86400-1;

            $rlt=Db::query('select sffl_dt from dq_third_stats_fyfylog where sffl_dt='.$dt.' limit 1');
            Utils::L('jsfy',["准备结算 $dt 分佣日志",$rlt]);
            if (count($rlt)==0) {
                $flag=false;
                self::refreshProxyTrees();
                Db::startTrans();
                try {

                    //合并用主呢ID到日志表
                    $sql = "insert into dq_third_stats_fyfylog (sffl_dt,sffl_uid)
select distinct $dt,tfpu_uid from dq_temp_fyfy_proxy_users order by tfpu_uid";
                    $flag = Db::execute($sql);
                    Utils::L('jsfy',["合并用户ID到日志表",$flag,$sql]);
                    if ($flag) {
                        //统计返水
                        $sql="update 
(
select sffl_dt,sffl_uid,sum(sb_backmoney) as backmoney from 
dq_third_stats_fyfylog
left join dq_third_stats_back on sffl_dt=sb_dt and sffl_uid=sb_uid
where sffl_dt=$dt and sb_dt is not null group by sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_back=ta.backmoney
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计返水",$rlt,$sql]);
                        //统计优惠一
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gd_amount+yhtb.tyj_amount) as money from 
dq_third_stats_fyfylog logtb
left join dq_action_apply yhtb on logtb.sffl_uid=yhtb.uid and yhtb.ps_status=1 and yhtb.ps_deta BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计优惠一",$rlt,$sql]);
                        //统计优惠二
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.price) as money from 
dq_third_stats_fyfylog logtb
left join dq_artificial_balance yhtb on logtb.sffl_uid=yhtb.uid and yhtb.a_type>2 and yhtb.add_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=tb.sffl_yh+ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计优惠二",$rlt,$sql]);
                        //统计充值
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.arrival_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_offline yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status>=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payin=ta.money,tb.sffl_payinfee=ta.money*".env('DEPOSIT_FEE')."
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计充值",$rlt,$sql]);
                        //统计提现
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.w_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_withdrawal yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payout=ta.money,tb.sffl_payoutfee=ta.money*".env('WITHDRAW_FEE')."
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计提现",$rlt,$sql]);
                        //统计有效投注
                        /*$sql="update
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_validwater) as validwater from
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_bettime BETWEEN $ds and $de
left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_state=1 and gtb.g_fystatus=1
where logtb.sffl_dt=$dt and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";*/
                        list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($ds,$de);
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.{$validWaterField}) as validwater from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_bettime BETWEEN $ds and $de
left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_fystatus=1
where logtb.sffl_dt=$dt and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计有效投注",$rlt,$sql]);

                        //统计客损
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_otherwin) as win from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose > 1 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_bettime BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.gb_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_betwin=ta.win,tb.sffl_ks=ta.win*-1
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计客损",$rlt,$sql]);


                        //内部下级代理扣减外部分佣统计
                        $rlt=self::subOutsideMoneyCount("M","M",$dm,$dm);
                        Utils::L('jsfy',["内部下级代理扣减外部分佣统计",$rlt,$dm]);

                        //清除当月统计
                        $sql="delete from dq_third_stats_fyfy where sff_m=$dm and sff_w is null";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["清除当月统计",$rlt,$sql]);

                        //不能小于指定分佣统计开始时间
                        $smonthday="00";
                        if ($jsstarttime){
                            $jsstmp=date("Ym",$jsstarttime);
                            if ($jsstmp==$dm){
                                $smonthday=date("d",$jsstarttime);
                            }
                        }

                        //生成外部和内部非顶级统计
                        /*$sql="insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dm as sff_m,tfpu_proxysubuid as sff_uid,$st as sff_statstime,if(tfpu_proxytype=1,1,3) as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog 
where tfpu_uid=sffl_uid and tfpu_proxyuid<>tfpu_proxysubuid and  sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxysubuid";*/
                        //内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                        $sql="insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dm as sff_m,tfpu_proxysubuid as sff_uid,$st as sff_statstime,if(tfpu_proxytype=1,1,3) as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxysubuid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["生成外部和内部非顶级统计",$rlt,$sql]);

                        //生成内部顶级统计 内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                        /*$sql="insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dm as sff_m,tfpu_proxyuid as sff_uid,$st as sff_statstime,2 as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog 
where tfpu_uid=sffl_uid and tfpu_proxyuid>0 and  sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxyuid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["生成内部顶级统计",$rlt,$sql]);*/

                        //统计有效客户数（外部代理）
                        $sql="update
(
select tfpu_proxysubuid,sum(if(bet=1 or payin=1,1,0)) as num 
from (
select tfpu_proxysubuid,tfpu_uid,if(sum(sffl_bet)>=".$rd['fyfy_valid_order'].",1,0) as bet,if(sum(sffl_payin)>=".$rd['fyfy_valid_user'].",1,0) as payin
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and (tfpu_scdm=$dm or tfpu_scdm between {$dm}{$smonthday} and {$dm}31) and tfpu_proxytype=1 and (sffl_bet>0 or sffl_payin>0) and sffl_dt between {$dm}{$smonthday} and {$dm}31
group by tfpu_proxysubuid,tfpu_uid
) tb
group by tfpu_proxysubuid
HAVING num>0
) as tc,dq_third_stats_fyfy td
set td.sff_validuser=tc.num
where tc.tfpu_proxysubuid=td.sff_uid and td.sff_m=$dm";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["更新有效客户数（外部代理）",$rlt,$sql]);
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
where sff_m=$dm";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计技术服务费",$rlt,$sql]);

                        if ($rd['fyfy_losstype']==1) {//如果设置负盈累积，则上期负盈累计至下个月
                            //统计上期负盈，本期负盈
                            //上期负盈迁移
                            $sql = "update
dq_third_stats_fyfy ta
left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_m=$dmyst
set ta.sff_prelosetotal=tb.sff_losetotal
where ta.sff_m=$dm and tb.sff_m is not null";
                            $rlt=Db::execute($sql);
                            Utils::L('jsfy',["上期负盈迁移",$rlt,$sql]);
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
                            Utils::L('jsfy',["计算客损负盈和分佣基数",$rlt,$sql]);
                        }else{//新版本中用客损额算分佣基数
                            $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec
where sff_m=$dm";
                            $rlt=Db::execute($sql);
                            Utils::L('jsfy',["计算客损分佣基数",$rlt,$sql]);
                        }

                        //统计分佣金额（外部代理）
                        $casesql=self::getfyfycasesql();
                        $sql="update
dq_third_stats_fyfy
set sff_fy=(
	case
	".$casesql[0]."
	else
	0
	end
),sff_rate=(
    case
    ".$casesql[1]."
    else
    0
    end
)
where sff_m=$dm and sff_utype=1 and sff_base>0";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计分佣金额（外部代理）",$rlt,$sql]);

                        //统计分佣金额（内部代理）-------------------------START
                        //统计每级代理的分佣金额（按比例）
                        $sql="update dq_third_stats_fyfy,dq_user_agent 
set sff_baserate=rate,sff_basereal=sff_base*rate,sff_fy=sff_base*rate
where sff_uid=uid and status=3
and sff_m=$dm and sff_utype>1 and sff_base>0";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["按比例统计每级代理分佣金额（内部）",$rlt,$sql]);
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
                        Utils::L('jsfy',["扣减分给下级的金额（内部）",$rlt,$sql]);
                        //按比例减下级外部代理分佣
                        $rlt=self::subOutsideMoneyToFyfy("M",$dm);
                        Utils::L('jsfy',["按比例扣减下级外部代理分佣",$rlt]);
                        //统计分佣金额（内部代理）-------------------------END
                        Db::commit();

                    } else {
                        Db::rollback();
                    }
                }catch (\Exception $ex){
                    Db::rollback();
                    Utils::L('jsfy',$ex->getMessage(), true, true);
                }
            }
        }
    }

    /** 负盈分佣模式每日统计-按周
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-19 2:43
     */
    public static function jsfy_week($jsdt=null){
        Utils::L('jsfy',["准备周结算 $jsdt 分佣日志"]);
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        $jsstarttime=env('FYFY_BILLING_START_TIME',0);
        //统计昨日返水
        if (self::jsback($jsdt)) {
            $rd=(new ApiRedis())->systemInfo();
            //判断昨日分佣是否已统计
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            //$yst=time()-86400;
            $yst=$pst-86400;
            Utils::L('jsfy',["yst",$yst]);
            $dt=date("Ymd",$yst);//当日
            $week=Utils::nextweek($yst,-1);
            Utils::L('jsfy',["周计算结果",$week]);
            $dmyst=$week['nextyw'];//统计周的上周，用于迁移上周负盈
            $dw=$week['yw'];//本周字符串
            $dms=date('Ym',$week['ys']);//上周一所在月
            $dme=date("Ym",$week['ye']);//上周末所在月
            $dse=date("Y-m-d",$yst);
            $ds=strtotime($dse." 00:00:00");
            $de=$ds+86400-1;

            $rlt=Db::query('select sffl_dt from dq_third_stats_fyfylog where sffl_dt='.$dt.' limit 1');
            Utils::L('jsfy',["准备结算 $dt 分佣日志",$rlt]);
            if (count($rlt)==0) {
                $flag=false;
                self::refreshProxyTrees();
                Db::startTrans();
                try {

                    //合并用主呢ID到日志表
                    $sql = "insert into dq_third_stats_fyfylog (sffl_dt,sffl_dw,sffl_uid)
select distinct $dt,$dw,tfpu_uid from dq_temp_fyfy_proxy_users order by tfpu_uid";
                    $flag = Db::execute($sql);
                    Utils::L('jsfy',["合并用户ID到日志表",$flag,$sql]);
                    if ($flag) {
                        //统计返水
                        $sql="update 
(
select sffl_dt,sffl_uid,sum(sb_backmoney) as backmoney from 
dq_third_stats_fyfylog
left join dq_third_stats_back on sffl_dt=sb_dt and sffl_uid=sb_uid
where sffl_dt=$dt and sb_dt is not null group by sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_back=ta.backmoney
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计返水",$rlt,$sql]);
                        //统计优惠一
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gd_amount+yhtb.tyj_amount) as money from 
dq_third_stats_fyfylog logtb
left join dq_action_apply yhtb on logtb.sffl_uid=yhtb.uid and yhtb.ps_status=1 and yhtb.ps_deta BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计优惠一",$rlt,$sql]);
                        //统计优惠二
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.price) as money from 
dq_third_stats_fyfylog logtb
left join dq_artificial_balance yhtb on logtb.sffl_uid=yhtb.uid and yhtb.a_type>2 and yhtb.add_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_yh=tb.sffl_yh+ta.money
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计优惠二",$rlt,$sql]);
                        //统计充值
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.arrival_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_offline yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status>=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payin=ta.money,tb.sffl_payinfee=ta.money*".env('DEPOSIT_FEE')."
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计充值",$rlt,$sql]);
                        //统计提现
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.w_price) as money from 
dq_third_stats_fyfylog logtb
left join dq_user_withdrawal yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status=3 and yhtb.update_time BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_payout=ta.money,tb.sffl_payoutfee=ta.money*".env('WITHDRAW_FEE')."
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计提现",$rlt,$sql]);
                        list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($ds,$de);
                        //统计有效投注
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.{$validWaterField}) as validwater from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>1 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_bettime BETWEEN $ds and $de
left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_fystatus=1
where logtb.sffl_dt=$dt and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_bet=ta.validwater
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计有效投注",$rlt,$sql]);

                        //统计客损
                        $sql="update 
(
select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_otherwin) as win from 
dq_third_stats_fyfylog logtb
left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose BETWEEN 2 and 3 and yhtb.gb_id between {$min_id} and {$max_id} and yhtb.gb_bettime BETWEEN $ds and $de
where logtb.sffl_dt=$dt and yhtb.gb_id is not null group by logtb.sffl_uid
) ta,dq_third_stats_fyfylog tb
set tb.sffl_betwin=ta.win,tb.sffl_ks=ta.win*-1
where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["统计客损",$rlt,$sql]);


                        //内部下级代理扣减外部分佣统计
                        $rlt=self::subOutsideMoneyCount("W","W",$dw,$dms);
                        Utils::L('jsfy',["内部下级代理扣减外部分佣统计",$rlt,$dw,$dms]);

                        //清除当周统计
                        $sql="delete from dq_third_stats_fyfy where sff_w=$dw";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["清除当月统计",$rlt,$sql]);

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
                        /*$sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dms as sff_m,$dw as sff_w,tfpu_proxysubuid as sff_uid,$st as sff_statstime,if(tfpu_proxytype=1,1,3) as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and tfpu_proxyuid<>tfpu_proxysubuid and  sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxysubuid";*/
                        //内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                        $sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dms as sff_m,$dw as sff_w,tfpu_proxysubuid as sff_uid,$st as sff_statstime,if(tfpu_proxytype=1,1,3) as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and  sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxysubuid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["生成外部和内部非顶级统计",$rlt,$sql]);

                        //生成内部顶级统计 内部代理下级不再起新树的话，不需要区分内部顶级了，否则会导致顶级代理重复记算下级
                        /*$sql="insert into dq_third_stats_fyfy (sff_m,sff_w,sff_uid,sff_statstime,sff_utype,sff_betwin,sff_betloss,sff_ks,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
select $dms as sff_m,$dw as sff_w,tfpu_proxyuid as sff_uid,$st as sff_statstime,2 as sff_utype,
sum(sffl_betwin) as sff_betwin,sum(sffl_betloss) as sff_betloss,sum(sffl_ks) as sff_ks,
sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog 
where tfpu_uid=sffl_uid and tfpu_proxyuid>0 and  sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxyuid";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["生成内部顶级统计",$rlt,$sql]);*/

                        //统计有效客户数（外部代理）
                        $sql="update
(
select tfpu_proxysubuid,sum(if(bet=1 or payin=1,1,0)) as num 
from (
select tfpu_proxysubuid,tfpu_uid,if(sum(sffl_bet)>=".$rd['fyfy_valid_order'].",1,0) as bet,if(sum(sffl_payin)>=".$rd['fyfy_valid_user'].",1,0) as payin
from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
where tfpu_uid=sffl_uid and (tfpu_scdm between {$dms}{$smonthday} and {$dme}{$emonthday}) and tfpu_proxytype=1 and (sffl_bet>0 or sffl_payin>0) and sffl_dt between {$dms}{$smonthday} and {$dme}{$emonthday}
group by tfpu_proxysubuid,tfpu_uid
) tb
group by tfpu_proxysubuid
HAVING num>0
) as tc,dq_third_stats_fyfy td
set td.sff_validuser=tc.num
where tc.tfpu_proxysubuid=td.sff_uid and td.sff_w=$dw";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["更新有效客户数（外部代理）",$rlt,$sql]);
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
                        Utils::L('jsfy',["统计技术服务费",$rlt,$sql]);

                        if ($rd['fyfy_losstype']==1) {//如果设置负盈累积，则上期负盈累计至下个月
                            //统计上期负盈，本期负盈
                            //上期负盈迁移
                            $sql = "update
dq_third_stats_fyfy ta
left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_w=$dmyst
set ta.sff_prelosetotal=tb.sff_losetotal
where ta.sff_w=$dw and tb.sff_w is not null";
                            $rlt=Db::execute($sql);
                            Utils::L('jsfy',["上期负盈迁移",$rlt,$sql]);
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
                            Utils::L('jsfy',["计算客损负盈和分佣基数",$rlt,$sql]);
                        }else{//新版本中用客损额算分佣基数
                            $sql = "update 
dq_third_stats_fyfy
set sff_base=sff_ks-sff_payinfee-sff_payoutfee-sff_yh-sff_back-sff_tec
where sff_w=$dw";
                            $rlt=Db::execute($sql);
                            Utils::L('jsfy',["计算客损分佣基数",$rlt,$sql]);
                        }

                        //统计分佣金额（外部代理）
                        $casesql=self::getfyfycasesql();
                        $sql="update
dq_third_stats_fyfy
set sff_fy=(
	case
	".$casesql[0]."
	else
	0
	end
),sff_rate=(
    case
    ".$casesql[1]."
    else
    0
    end
)
where sff_w=$dw and sff_utype=1 and sff_base>0";
                        $sql=Db::execute($sql);
                        Utils::L('jsfy',["统计分佣金额（外部代理）",$rlt,$sql]);

                        //统计分佣金额（内部代理）-------------------------START
                        //统计每级代理的分佣金额（按比例）
                        $sql="update dq_third_stats_fyfy,dq_user_agent 
set sff_baserate=rate,sff_basereal=sff_base*rate,sff_fy=sff_base*rate
where sff_uid=uid and status=3
and sff_w=$dw and sff_utype>1 and sff_base>0";
                        $rlt=Db::execute($sql);
                        Utils::L('jsfy',["按比例统计每级代理分佣金额（内部）",$rlt,$sql]);
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
                        Utils::L('jsfy',["扣减分给下级的金额（内部）",$rlt,$sql]);
                        //按比例减下级外部代理分佣
                        $rlt=self::subOutsideMoneyToFyfy("W",$dw);
                        Utils::L('jsfy',["按比例扣减下级外部代理分佣",$rlt]);
                        //统计分佣金额（内部代理）-------------------------END
                        Db::commit();

                    } else {
                        Db::rollback();
                    }
                }catch (\Exception $ex){
                    Db::rollback();
                    Utils::L('jsfy',[$ex->getMessage(),isset($rlt)?$rlt:null,isset($sql)?$sql:null], true, true);
                }
            }
        }else{
            Utils::L('jsfy','结算返水错误');
        }
    }


    /** 根据ENV配置限制外部分佣发放
     * @param $fyfywhere 分佣表周期查询条件
     * @param $ds 统计周期开始时间
     * @param $de 统计周期结束时间
     * @return void
     */
    public static function limitOutsideFypay($fyfywhere,$ds,$de){
        $apiRedis = new ApiRedis();
        $sys = $apiRedis->systemInfo();
        $outsidefytype=strtolower(env('FYFY_OUTSIDE_PAY_TYPE','all'));//外部代理分佣限制类型
        if( isset($sys['fyfy_type']) && $sys['fyfy_type'] ){
            $outsidefytype = $sys['fyfy_type'];
        }
        if ($outsidefytype=='all') return;//无限制
        $payin=intval(env('FYFY_OUTSIDE_PAY_PAYIN_SUM',0));//外部代理分佣限制周期内充值金额
        if( isset($sys['fyfy_recharge_condition']) && $sys['fyfy_recharge_condition'] >= 0 ){
            $payin = $sys['fyfy_recharge_condition'];
        }
        if ($payin<=0) return;//无充值金额限制
        $cycel=strtolower(env('FYFY_OUTSIDE_PAY_PAYIN_CYCEL','sametopay'));//外部代理分佣限制统计周期
        if( isset($sys['fyfy_cycle']) && $sys['fyfy_cycle'] ){
            $cycel = $sys['fyfy_cycle'];
        }
        if ($cycel=='sametopay'){
            $timewhere=" and yhtb.update_time BETWEEN $ds and $de";
        }else{
            $timewhere='';
        }
        $sql="update (
select tbfy.sff_id,ifnull(sum(yhtb.arrival_price),0) as money from 
dq_third_stats_fyfy tbfy
left join dq_user_offline yhtb on tbfy.sff_uid=yhtb.uid and yhtb.status>=3 {$timewhere} 
where tbfy.{$fyfywhere} and tbfy.sff_payed=1 and tbfy.sff_utype=1 group by tbfy.sff_uid
HAVING money<{$payin}
) ta,dq_third_stats_fyfy tb
set tb.sff_payed=0
where ta.sff_id=tb.sff_id";
        $flag = Db::execute($sql);
        Utils::L('jsfypay', ["处理外部代理分佣限制", $flag, $sql]);

    }


    /** 根据env配置决定是按月结算支付还是按周结算支付
     * @param null $jsdt
     * @author twt326
     * @date 2023-03-13 22:58
     */
    public static function jsfypay($jsdt=null){
        $jsmod=env('FYFY_BILLING_CYCEL','W');
        /*if (empty($jsmod)){
            $jsmod='W';
        }else{
            $jsmod=strtoupper($jsmod);
            if (!in_array($jsmod,['W','M'])){
                $jsmod='W';
            }
        }*/
        $jsmod=strtoupper($jsmod);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        switch ($jsmod){
            case 'M':
                self::jsfypay_month($jsdt);
                break;
            default:
                self::jsfypay_week($jsdt);
                break;
        }
    }

    /**负盈模式分佣（外部代理）--后加：内部代理一起
     * @param $jsdt 指定结算日期 yyyy-mm-dd 注意由于结算是结算前一天的数据，所以如果要结算指定日期的数据应该传入后一天的日期（仅用于补结算，不会重新结算，特别注意的是，如果补结算日期之后代理线关系发生变化 ，比如代理线中某用户失效，那补结算的数据不包括失效用户，这种情况下会存在误差）
     * @author twt326
     * @date 2022-10-22 2:57
     */
    public static function jsfypay_month($jsdt=null){
        $rd=(new ApiRedis())->systemInfo();
        $autopay=($rd['auto_commission']==1);//自动分佣 仅分用户代理  后加：内部代理也一并分佣
        $fyauto=intval(env('FYFY_AUTO_COMMISSION_SELF_PROXY',0));//内部代理是否自动分佣
        if (!in_array($fyauto,[0,1])) $fyauto=0;
//自动分佣判断上月最后一天统计是否已完成，完成则分佣
        if ($autopay || $fyauto>0){//如果自动发放
            $st=time();
            $pst=$jsdt==null?time():strtotime($jsdt);
            if ($pst>=strtotime(date("Y-m-d 23:59:59"))+1) return;//不能指定未来日期
            $ystm=strtotime(date("Y-m-01 00:00:00",$pst))-1;
            $dt=date("Ymd",$ystm);
            $dm=date("Ym",$ystm);
            //判断上月是否已统计完毕
            $rlt=Db::query("select * from dq_third_stats_fyfylog where sffl_dt=$dt limit 1");
            if (count($rlt)>0){
                if ($autopay && $fyauto>0){
                    $typesql="";
                }elseif ($autopay){
                    $typesql=" sff_utype=1 and ";
                }else{
                    $typesql=" sff_utype>1 and ";
                }
                //判断上周是否已结算（只要有一笔支付则表示上月已结算）
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_m=$dm and sff_payed=2 limit 1");
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        self::limitOutsideFypay("sff_m=$dm",strtotime(date("Y-m-01 00:00:00",$ystm)), $ystm);
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
set sff_payed=2,sff_paytime=$st
where $typesql sff_m=$dm and sff_fy>0 and sff_payed=1";
                        $flag = Db::execute($sql);
                        if ($flag) {
                            //增加用户余额 和 savings
                            //20230703增加流水倍率配置支持
                            $savingRate=isset($rd['FySavingRateOut'])?floatval($rd['FySavingRateOut']):1;
                            if ($savingRate<0) $savingRate=0;
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,if(sff_utype=1,{$savingRate},1) as multiple,sff_fy*if(sff_utype=1,{$savingRate},1) as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_m=$dm and sff_fy>0 and sff_payed=2 HAVING water>0";
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay', ["写入流水记录", $flag, $sql]);
                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy*if(sff_utype=1,{$savingRate},1)
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
                        Utils::L('jsfypay',$ex->getMessage(), true, true);
                        //return false;
                    }
                }
            }


        }
    }

    public static function jsfypay_week($jsdt=null){
        $rd=(new ApiRedis())->systemInfo();
        Utils::L('jsfypay',["是否自动分佣",$rd['auto_commission']]);
        //$rd['auto_commission']=1;
        $autopay=($rd['auto_commission']==1);//自动分佣 仅分用户代理  后加：内部代理也一并分佣
        $fyauto=intval(env('FYFY_AUTO_COMMISSION_SELF_PROXY',0));//内部代理是否自动分佣
        if (!in_array($fyauto,[0,1])) $fyauto=0;
//自动分佣判断上周最后一天统计是否已完成，完成则分佣
        if ($autopay || $fyauto>0){//如果自动发放
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
                if ($autopay && $fyauto>0){
                    $typesql="";
                }elseif ($autopay){
                    $typesql=" sff_utype=1 and ";
                }else{
                    $typesql=" sff_utype>1 and ";
                }
                //判断上月是否已结算（只要有一笔支付则表示上月已结算）
                //$rlt=Db::query("select * from dq_third_stats_fyfy where sff_m=$dm and sff_utype=1 and sff_payed=2 limit 1");
                //不区分内外代理
                $rlt=Db::query("select * from dq_third_stats_fyfy where $typesql sff_w=$dw and sff_payed=2 limit 1");
                Utils::L('jsfypay',["查询上期结算状态",$rlt]);
                if (count($rlt)==0){
                    Db::startTrans();
                    try {
                        self::limitOutsideFypay("sff_w=$dw",$ystm['nextys'], $ystm['nextye']);
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
set sff_payed=2,sff_paytime=$st
where $typesql sff_w=$dw and sff_fy>0 and sff_payed=1";
                        $flag = Db::execute($sql);
                        Utils::L('jsfypay',["变更发放状态",$flag,$sql]);
                        if ($flag) {
                            //增加用户余额 和 savings
                            /*$sql = "update dq_third_stats_fyfy,dq_user
set balance=balance+sff_fy,savings=savings+sff_fy
where sff_uid=id and sff_utype=1 and sff_m=$dm and sff_fy>0";*/
                            $savingRate=isset($rd['FySavingRateOut'])?floatval($rd['FySavingRateOut']):1;
                            if ($savingRate<0) $savingRate=0;
                                //写入流水记录
                                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)  
select sff_uid as uid,sff_fy as amount,if(sff_utype=1,{$savingRate},1) as multiple,sff_fy*if(sff_utype=1,{$savingRate},1) as water,sff_id as origin_id,{$watertype} as type,concat(if(sff_w is null,sff_m,sff_w),if(sff_w is null,'月',if(sff_w<999999,'周','日')),'返佣加流水') as remark,$st as create_time from dq_third_stats_fyfy
where $typesql sff_w=$dw and sff_fy>0 and sff_payed=2 HAVING water>0";
                            $flag = Db::execute($sql);
                            Utils::L('jsfypay', ["写入流水记录", $flag, $sql]);
                            //更新余额和流水
                            $sql = "update dq_third_stats_fyfy,dq_user
set {$fieldban}={$fieldban}+sff_fy,{$fieldsav}={$fieldsav}+sff_fy*if(sff_utype=1,{$savingRate},1)
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
                        Utils::L('jsfypay',$ex->getMessage(), true, true);
                        //return false;
                    }
                }
            }


        }
    }

    /** 创建时区统计表并补统计历史数据
     * @param $zones
     * @param $table
     * @return void
     */
    public static function createCountTables($zones,$table){
        if ($zones) {
            //创建时区统计表
            $buildtables=[];
            for($i=0;$i<count($zones);$i++){
                $ex=strtolower($zones[$i][0]);
                if ($ex!="default"){
                    $full_table_name=$table.'_'.$ex;
                    $rlt=Db::query("show tables like '{$full_table_name}'");
                    if (empty($rlt)){
                        //补充历史数据
                        array_push($buildtables,[$zones[$i],$ex]);
                    }
                }
            }
            //重建历史数据，这里没有做出错处理，请手动确认数据是否正确生成，如果出错，请删除非默认时区统计表并清除对应的redis key ('rebuildcountstat_'.$table)
            Redis::set('rebuildcountstat_'.$table,1);
            if (count($buildtables)>0) {
                //拿到历史flags
                $flagfield = $table == 'dq_third_stats_users' ? 'gb_cflagt' : 'gb_gcflag';
                $flagjsfield = $table == 'dq_third_stats_users' ? 'gb_cflag' : 'gb_gcflagt';
                Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='game_bets' order by ct_m";
                $logrlt = Db::query($sql);
                Utils::L('jscut', ['分表记录', $logrlt]);
                $logrlt = array_column($logrlt, 'ct_m');
                array_push($logrlt,'');//添加主表后缀
                foreach ($logrlt as $ex){
                    $tbex=($ex=='')?'':('_'.$ex);
                    //表是否存在
                    $sql = "show tables like 'dq_game_bets{$tbex}'";
                    $rlt = Db::query($sql);
                    Utils::L('jscut', ['判断表是否存在', "dq_game_bets{$tbex}", $rlt, $sql]);
                    if (count($rlt) == 0) continue;//表不存在则跳过
                    Utils::L('countHisData', ['准备处理分表数据', $tbex], true, true);
                    /*$flags =  GameBetsModel::distinct(true)->order($flagfield)->column($flagfield);
                    $flagsjs = GameBetsModel::distinct(true)->order($flagjsfield)->column($flagjsfield);*/
                    $flags=Db::query("select distinct {$flagfield} from dq_game_bets{$tbex} order by {$flagfield}");
                    $flags=array_column($flags,$flagfield);
                    $flagsjs=Db::query("select distinct {$flagjsfield} from dq_game_bets{$tbex} order by {$flagjsfield}");
                    $flagsjs=array_column($flagsjs,$flagjsfield);
                    Utils::L('countHisData', ['拿到历史FLAGS，显示其中两个', isset($flags[0]) ? $flags[0] : null, isset($flags[1]) ? $flags[1] : null]);
                    Utils::L('countHisData', ['拿到历史FLAGSJS，显示其中两个', isset($flagsjs[0]) ? $flagsjs[0] : null, isset($flagsjs[1]) ? $flagsjs[1] : null]);

                    if (count($flags) > 0) {

                        $idranges = [];
                        $flagnums = [];//投注FLAG标志
                        $flagnumsjs = [];//结算FLAG标志 两个标志段队列要按时间顺序对齐
                        $cnt = 0;
                        $nums = [];
                        $numsjs = [];
                        foreach ($flags as $f) {
                            if ($cnt >= 120) {
                                array_push($flagnums, $nums);
                                $bjnum = $nums[count($nums) - 1];
                                //小于等于投注标识的结算标识写入到对应的结算标识中
                                for ($i = 0; $i < count($flagsjs); $i++) {
                                    if ($flagsjs[$i] <= $bjnum) {
                                        if ($flagsjs[$i] > 0) array_push($numsjs, $flagsjs[$i]);
                                    } else {//结算标志大于投注标识的截断
                                        array_push($flagnumsjs, $numsjs);
                                        $flagsjs = array_slice($flagsjs, $i);
                                        break;
                                    }
                                }
                                $nums = [];
                                $numsjs = [];
                                $cnt = 0;
                            }
                            if ($f > 0) array_push($nums, $f);
                            $cnt++;
                        }
                        if (count($nums) > 0) {//上述处理未达到120个标志的情况下继续处理剩下的部分
                            array_push($flagnums, $nums);
                            $bjnum = $nums[count($nums) - 1];
                            $numsjs = [];
                            //小于等于投注标识的结算标识写入到对应的结算标识中
                            $lasti = 0;
                            for ($i = 0; $i < count($flagsjs); $i++) {
                                if ($flagsjs[$i] <= $bjnum) {
                                    if ($flagsjs[$i] > 0) array_push($numsjs, $flagsjs[$i]);
                                    $lasti = $i;
                                } else {
                                    $lasti = 0;
                                    $flagsjs = array_slice($flagsjs, $i);
                                    break;
                                }
                            }
                            if ($lasti > 0) $flagsjs = [];
                            array_push($flagnumsjs, $numsjs);
                        }
                        if (count($flagsjs) > 0) {
                            //如果结算标志多于投注标志，则补充一个空数据块到投注标志段队列中
                            array_push($flagnums, []);
                            array_push($flagnumsjs, $flagsjs);
                        }
                        Utils::L('countHisData', ['数据块', count($flagnums)], true, true);
                        $starttime = time();
                        foreach ($flagnums as $k => $v) {
                            //建立ID区间缓存
                            if (count($v) == 0) {
                                $minid = 0;
                                $maxid = 0;
                            } else {
                                /*$minid = 0;
                                $maxid = 0;
                                foreach ($v as $vv) {
                                    $sql = "select min(gb_id) as minid,max(gb_id) as maxid from dq_game_bets{$tbex} where {$flagfield}=" . $vv;
                                    list("minid"=>$tmpminid, "maxid"=>$tmpmaxid) = Db::query($sql)[0];
                                    if ($minid == 0 || $tmpminid < $minid) $minid = $tmpminid;
                                    if ($maxid == 0 || $tmpmaxid > $maxid) $maxid = $tmpmaxid;
                                }*/
                                $sql = "select min(gb_id) as minid,max(gb_id) as maxid from dq_game_bets{$tbex} where {$flagfield} in (". implode(',', $v).")";
                                list("minid"=>$minid, "maxid"=>$maxid) = Db::query($sql)[0];
                            }
                            if (count($flagnumsjs[$k]) == 0) {
                                $jsmaxid = 0;
                                $jsminid = 0;
                            } else {
                                /*$jsmaxid = 0;
                                $jsminid = 0;
                                foreach ($flagnumsjs[$k] as $vv) {
                                    $sql = "select min(gb_id) as minid,max(gb_id) as maxid from dq_game_bets{$tbex} where {$flagjsfield}=" . $flagnumsjs[$k][0];
                                    list("minid"=>$tmpminid, "maxid"=>$tmpmaxid) = Db::query($sql)[0];
                                    if ($jsminid == 0 || $tmpminid < $jsminid) $jsminid = $tmpminid;
                                    if ($jsmaxid == 0 || $tmpmaxid > $jsmaxid) $jsmaxid = $tmpmaxid;
                                }*/
                                $sql = "select min(gb_id) as minid,max(gb_id) as maxid from dq_game_bets{$tbex} where {$flagjsfield} in (". implode(',', $flagnumsjs[$k]).")";
                                list("minid"=>$jsminid, "maxid"=>$jsmaxid) = Db::query($sql)[0];
                            }
                            $idranges[$k] = [
                                'minid' => $minid,
                                'maxid' => $maxid,
                                'jsminid' => $jsminid,
                                'jsmaxid' => $jsmaxid
                            ];
                            Utils::L('countHisData', ['IDRANGE', $k, $idranges[$k]], true, true);
                        }
                        foreach ($buildtables as $ck => $v) {
                            $full_table_name = $table . '_' . $v[1];
                            //表是否存在
                            $sql = "show tables like '{$full_table_name}'";
                            $rlt = Db::query($sql);
                            Utils::L('jscut', ['判断表是否存在', "{$full_table_name}", $rlt, $sql]);
                            if (count($rlt) == 0) {
                                Db::execute("create table {$full_table_name} like {$table}");//创建表
                            }
                            $sql = "SELECT NOW();";
                            $mysql_now = Db::query($sql);
                            Utils::L('countHisData', ['当前数据库时间', $mysql_now], true, true);
                            $zone = strtolower($v[0][0]);
                            Utils::L('countHisData', ['准备统计时区', $v[0][0]], true, true);
                            //设置时区
                            if ($zone == "default") {
                                $sql = "set time_zone='" . env("mysql_timezone", $v[0][1]) . "'";//mysql_timezone
                            } else {
                                $sql = "set time_zone='" . $v[0][1] . "'";//mysql_timezone
                            }
                            Db::execute($sql);
                            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
                            $timezone = Db::query($sql);
                            Utils::L('countHisData', ['转换后时区', $timezone], true, true);
                            $sql = "SELECT NOW();";
                            $mysql_now = Db::query($sql);
                            Utils::L('countHisData', ['转换时区后数据库时间', $mysql_now], true, true);
                            //foreach ($idranges as $f) {
                            foreach ($flagnums as $k => $f) {
                                if ($table == 'dq_third_stats_users') {
                                    //self::countUserBetsByZone("_" . $zone, $f['flag'], $f['min'], $f['max'], $f['jsmin'], $f['jsmax']);
                                    self::countUserBetsByZone("_" . $zone, [$f, $flagnumsjs[$k]], $idranges[$k]['minid'], $idranges[$k]['maxid'], $idranges[$k]['jsminid'], $idranges[$k]['jsmaxid'],$tbex);
                                } else {
                                    //self::countStatGlobalByZone('',"_".$zone,$f['flag'],$f['min'], $f['max'], $f['jsmin'], $f['jsmax']);
                                    self::countStatGlobalByZone($tbex, "_" . $zone, [$f, $flagnumsjs[$k]], $idranges[$k]['minid'], $idranges[$k]['maxid'], $idranges[$k]['jsminid'], $idranges[$k]['jsmaxid']);
                                }
                                //sleep(3);
                                Utils::L('countHisData', ['进度', $ck . '/' . count($buildtables), $k . '/' . count($flagnums), '已执行时间：',
                                    number_format((time() - $starttime) / 3600, 2, '.', '') . '小时'
                                    , '预估剩作时间',
                                    number_format(((time() - $starttime) / (($ck * count($flagnums) + $k + 1) / (count($buildtables) * count($flagnums))) - (time() - $starttime)) / 3600, 2, '.', '') . '小时'
                                ], true, true);
                            }

                        }
                    }
                }
                Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
            }
            Redis::del('rebuildcountstat_'.$table);
        }
    }

    /** 创建时区统计表并补统计历史数据 此方法备份，其在热表结算时间过长时会因为获取GBID范围时漏掉部分数据
     * @param $zones
     * @param $table
     * @return void
     */
    public static function createCountTables_old($zones,$table){
        if ($zones) {
            //创建时区统计表
            $buildtables=[];
            for($i=0;$i<count($zones);$i++){
                $ex=strtolower($zones[$i][0]);
                if ($ex!="default"){
                    $full_table_name=$table.'_'.$ex;
                    $rlt=Db::query("show tables like '{$full_table_name}'");
                    if (empty($rlt)){
                        //补充历史数据
                        array_push($buildtables,[$zones[$i],$ex]);
                    }
                }
            }
            //重建历史数据，这里没有做出错处理，请手动确认数据是否正确生成，如果出错，请删除非默认时区统计表并清除对应的redis key ('rebuildcountstat_'.$table)
            Redis::set('rebuildcountstat_'.$table,1);
            if (count($buildtables)>0) {
                //拿到历史flags
                $flagfield = $table == 'dq_third_stats_users' ? 'gb_cflagt' : 'gb_gcflag';
                $flagjsfield = $table == 'dq_third_stats_users' ? 'gb_cflag' : 'gb_gcflagt';
                Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='game_bets' order by ct_m";
                $logrlt = Db::query($sql);
                Utils::L('jscut', ['分表记录', $logrlt]);
                $logrlt = array_column($logrlt, 'ct_m');
                array_push($logrlt,'');//添加主表后缀
                foreach ($logrlt as $ex){
                    $tbex=($ex=='')?'':('_'.$ex);
                    //表是否存在
                    $sql = "show tables like 'dq_game_bets{$tbex}'";
                    $rlt = Db::query($sql);
                    Utils::L('jscut', ['判断表是否存在', "dq_game_bets{$tbex}", $rlt, $sql]);
                    if (count($rlt) == 0) continue;//表不存在则跳过
                    Utils::L('countHisData', ['准备处理分表数据', $tbex], true, true);
                    /*$flags =  GameBetsModel::distinct(true)->order($flagfield)->column($flagfield);
                    $flagsjs = GameBetsModel::distinct(true)->order($flagjsfield)->column($flagjsfield);*/
                    $flags=Db::query("select distinct {$flagfield} from dq_game_bets{$tbex} order by {$flagfield}");
                    $flags=array_column($flags,$flagfield);
                    $flagsjs=Db::query("select distinct {$flagjsfield} from dq_game_bets{$tbex} order by {$flagjsfield}");
                    $flagsjs=array_column($flagsjs,$flagjsfield);
                    Utils::L('countHisData', ['拿到历史FLAGS，显示其中两个', isset($flags[0]) ? $flags[0] : null, isset($flags[1]) ? $flags[1] : null]);
                    Utils::L('countHisData', ['拿到历史FLAGSJS，显示其中两个', isset($flagsjs[0]) ? $flagsjs[0] : null, isset($flagsjs[1]) ? $flagsjs[1] : null]);

                    if (count($flags) > 0) {

                        $idranges = [];
                        $flagnums = [];//投注FLAG标志
                        $flagnumsjs = [];//结算FLAG标志 两个标志段队列要按时间顺序对齐
                        $cnt = 0;
                        $nums = [];
                        $numsjs = [];
                        foreach ($flags as $f) {
                            if ($cnt >= 120) {
                                array_push($flagnums, $nums);
                                $bjnum = $nums[count($nums) - 1];
                                //小于等于投注标识的结算标识写入到对应的结算标识中
                                for ($i = 0; $i < count($flagsjs); $i++) {
                                    if ($flagsjs[$i] <= $bjnum) {
                                        if ($flagsjs[$i] > 0) array_push($numsjs, $flagsjs[$i]);
                                    } else {
                                        array_push($flagnumsjs, $numsjs);
                                        $flagsjs = array_slice($flagsjs, $i);
                                        break;
                                    }
                                }
                                $nums = [];
                                $numsjs = [];
                                $cnt = 0;
                            }
                            if ($f > 0) array_push($nums, $f);
                            $cnt++;
                        }
                        if (count($nums) > 0) {
                            array_push($flagnums, $nums);
                            $bjnum = $nums[count($nums) - 1];
                            $numsjs = [];
                            //小于等于投注标识的结算标识写入到对应的结算标识中
                            $lasti = 0;
                            for ($i = 0; $i < count($flagsjs); $i++) {
                                if ($flagsjs[$i] <= $bjnum) {
                                    if ($flagsjs[$i] > 0) array_push($numsjs, $flagsjs[$i]);
                                    $lasti = $i;
                                } else {
                                    $lasti = 0;
                                    $flagsjs = array_slice($flagsjs, $i);
                                    break;
                                }
                            }
                            if ($lasti > 0) $flagsjs = [];
                            array_push($flagnumsjs, $numsjs);
                        }
                        if (count($flagsjs) > 0) {
                            //如果结算标志多于投注标志
                            array_push($flagnums, []);
                            array_push($flagnumsjs, $flagsjs);
                        }
                        Utils::L('countHisData', ['数据块', count($flagnums)], true, true);
                        $starttime = time();
                        foreach ($flagnums as $k => $v) {
                            //建立ID区间缓存
                            if (count($v) == 0) {
                                $minid = 0;
                                $maxid = 0;
                            } else {
                                $sql = "select min(gb_id) as gbid from dq_game_bets{$tbex} where {$flagfield}=" . $v[0];
                                $rlt = Db::query($sql);
                                $minid = $rlt[0]['gbid'] ?? 0;
                                $sql = "select max(gb_id) as gbid from dq_game_bets{$tbex} where {$flagfield}=" . $v[count($v) - 1];
                                $rlt = Db::query($sql);
                                $maxid = $rlt[0]['gbid'] ?? 0;
                            }
                            if (count($flagnumsjs[$k]) == 0) {
                                $jsmaxid = 0;
                                $jsminid = 0;
                            } else {
                                $sql = "select min(gb_id) as gbid from dq_game_bets{$tbex} where {$flagjsfield}=" . $flagnumsjs[$k][0];
                                $rlt = Db::query($sql);
                                $jsminid = $rlt[0]['gbid'] ?? 0;
                                $sql = "select max(gb_id) as gbid from dq_game_bets{$tbex} where {$flagjsfield}=" . $flagnumsjs[$k][count($flagnumsjs[$k]) - 1];
                                $rlt = Db::query($sql);
                                $jsmaxid = $rlt[0]['gbid'] ?? 0;
                            }
                            $idranges[$k] = [
                                'minid' => $minid,
                                'maxid' => $maxid,
                                'jsminid' => $jsminid,
                                'jsmaxid' => $jsmaxid
                            ];
                            Utils::L('countHisData', ['IDRANGE', $k, $idranges[$k]], true, true);
                        }
                        foreach ($buildtables as $ck => $v) {
                            $full_table_name = $table . '_' . $v[1];
                            //表是否存在
                            $sql = "show tables like '{$full_table_name}'";
                            $rlt = Db::query($sql);
                            Utils::L('jscut', ['判断表是否存在', "{$full_table_name}", $rlt, $sql]);
                            if (count($rlt) == 0) {
                                Db::execute("create table {$full_table_name} like {$table}");//创建表
                            }
                            $sql = "SELECT NOW();";
                            $mysql_now = Db::query($sql);
                            Utils::L('countHisData', ['当前数据库时间', $mysql_now], true, true);
                            $zone = strtolower($v[0][0]);
                            Utils::L('countHisData', ['准备统计时区', $v[0][0]], true, true);
                            //设置时区
                            if ($zone == "default") {
                                $sql = "set time_zone='" . env("mysql_timezone", $v[0][1]) . "'";//mysql_timezone
                            } else {
                                $sql = "set time_zone='" . $v[0][1] . "'";//mysql_timezone
                            }
                            Db::execute($sql);
                            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
                            $timezone = Db::query($sql);
                            Utils::L('countHisData', ['转换后时区', $timezone], true, true);
                            $sql = "SELECT NOW();";
                            $mysql_now = Db::query($sql);
                            Utils::L('countHisData', ['转换时区后数据库时间', $mysql_now], true, true);
                            //foreach ($idranges as $f) {
                            foreach ($flagnums as $k => $f) {
                                if ($table == 'dq_third_stats_users') {
                                    //self::countUserBetsByZone("_" . $zone, $f['flag'], $f['min'], $f['max'], $f['jsmin'], $f['jsmax']);
                                    self::countUserBetsByZone("_" . $zone, [$f, $flagnumsjs[$k]], $idranges[$k]['minid'], $idranges[$k]['maxid'], $idranges[$k]['jsminid'], $idranges[$k]['jsmaxid'],$tbex);
                                } else {
                                    //self::countStatGlobalByZone('',"_".$zone,$f['flag'],$f['min'], $f['max'], $f['jsmin'], $f['jsmax']);
                                    self::countStatGlobalByZone($tbex, "_" . $zone, [$f, $flagnumsjs[$k]], $idranges[$k]['minid'], $idranges[$k]['maxid'], $idranges[$k]['jsminid'], $idranges[$k]['jsmaxid']);
                                }
                                //sleep(3);
                                Utils::L('countHisData', ['进度', $ck . '/' . count($buildtables), $k . '/' . count($flagnums), '已执行时间：',
                                    number_format((time() - $starttime) / 3600, 2, '.', '') . '小时'
                                    , '预估剩作时间',
                                    number_format(((time() - $starttime) / (($ck * count($flagnums) + $k + 1) / (count($buildtables) * count($flagnums))) - (time() - $starttime)) / 3600, 2, '.', '') . '小时'
                                ], true, true);
                            }

                        }
                    }
                }
                Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
            }
            Redis::del('rebuildcountstat_'.$table);
        }
    }



    public static function countUserBetsByZone($ex,$flag,$min_id,$max_id,$jsmin_id,$jsmax_id,$cutex=''){
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //清除临时表数据
        $sql="delete from dq_third_stats_users_temp";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除统计临时表数据：'.$rlt);
        $sql="delete from dq_third_stats_users_temp_js";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除结算临时表数据：'.$rlt);

        Utils::L('countUserBets','执行统计（投注）任务');
        //统计投注情况---------------------------
        if (is_array($flag)){
            if (count($flag[0])>0){
                $where = "gb_id between {$min_id} and {$max_id} and gb_cflagt in (".implode(',',$flag[0]).")";
            }else{
                $where = "gb_id =0";
            }

        }else {
            $where = "gb_cflagt=$flag  and gb_id between {$min_id} and {$max_id}";
        }
        //写入投注统计数据表临时表
        $sql="insert into dq_third_stats_users_temp (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_betnum,su_bet)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,rec_id3,rec_id2,rec_id,id,username,tree,count(*) as betnum,sum(gb_price) as bet
from 
dq_game_bets{$cutex},dq_user
where gb_uid=id and {$where}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),gb_uid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['写入投注统计数据表临时表：',$sql,$rlt]);
        //存在的记录更新 与写入不能互换顺序，更新必须在前
        $sql="update dq_third_stats_users_temp ta,dq_third_stats_users{$ex} tb 
set 
tb.su_betnum=tb.su_betnum+ta.su_betnum,
tb.su_bet=tb.su_bet+ta.su_bet
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['存在的记录更新：',$sql,$rlt]);
        //不存在的记录写入
        $sql="insert into dq_third_stats_users{$ex}
select ta.* from dq_third_stats_users_temp ta
left join dq_third_stats_users{$ex} tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid
where tb.su_dt is null";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['不存在的记录写入：',$sql,$rlt]);

        //统计结算情况---------------------------

        Utils::L('countUserBets','执行统计（结算）任务');

        if (is_array($flag)){
            if (count($flag[1])>0){
                $where = "gb_id between {$jsmin_id} and {$jsmax_id} and gb_cflag in (".implode(',',$flag[1]).")";
            }else{
                $where = "gb_id =0";
            }

        }else {
            $where = "gb_cflag=$flag and gb_id between {$jsmin_id} and {$jsmax_id}";
        }
        //写入结算统计数据表临时表 这里的su_bet里存放的是已结算的投注额，用于减savings的
        //su_bet已经无效，去掉以防止投注金额重复统计（在统计投注的时候如果有新的结算订单生成会导致结算统计的最大GB_ID超过投注统计的ID）
        /*$sql="insert into dq_third_stats_users_temp_js (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_bet,su_winnum,su_win,su_drawnum,su_draw,su_validwater,su_otherwin)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,rec_id3,rec_id2,rec_id,id,username,tree,sum(gb_price) as bet,
sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
sum(IF(gb_validwater<gb_price,1,0)) as drawnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw,
sum(gb_validwater) as validwater,
sum(gb_otherwin) as otherwin
from dq_game_bets,dq_user
where gb_uid=id and {$where}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),gb_uid";*/
        $sql="insert into dq_third_stats_users_temp_js (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_winnum,su_win,su_drawnum,su_draw,su_validwater,su_otherwin)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,rec_id3,rec_id2,rec_id,id,username,tree,
sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
sum(IF(gb_validwater<gb_price,1,0)) as drawnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw,
sum({$validWaterField}) as validwater,
sum(gb_otherwin) as otherwin
from dq_game_bets{$cutex},dq_user
where gb_uid=id and {$where}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),gb_uid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['写入结算统计数据表临时表：',$sql,$rlt]);
        //存在的记录更新 与写入不能互换顺序，更新必须在前
        $sql="update dq_third_stats_users_temp_js ta,dq_third_stats_users{$ex} tb 
set 
tb.su_winnum=tb.su_winnum+ta.su_winnum,
tb.su_win=tb.su_win+ta.su_win,
tb.su_drawnum=tb.su_drawnum+ta.su_drawnum,
tb.su_draw=tb.su_draw+ta.su_draw,
tb.su_validwater=tb.su_validwater+ta.su_validwater,
tb.su_otherwin=tb.su_otherwin+ta.su_otherwin
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['存在的记录更新：',$sql,$rlt]);
        //不存在的记录写入
        $sql="insert into dq_third_stats_users{$ex}
select ta.* from dq_third_stats_users_temp_js ta
left join dq_third_stats_users{$ex} tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid
where tb.su_dt is null";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets',['不存在的记录写入：',$sql,$rlt]);
    }

    /** 统计三方平台指定时区用户日报表
     * 最晚统计到当前时间前1分钟，以免出现数据统计遗漏
     * @author twt326
     * @date 2022-10-23 19:04
     */
    public static function countUserBets(){
        $c=Redis::get('rebuildcountstat_dq_third_stats_users');
        if ($c){
            Utils::L('countUserBets',['正在重建数据，本次不执行'], true, true);
            return;
        }
        //读取配置
        $zones=SysSets::getSetByCode('COUNT_DATA_TIMEZONE_LIST');
        if (empty($zones)){
            $zones=[
                [
                    0=>"DEFAULT",
                    1=>'+0:00'
                ]
            ];
        }
        //处理时区，忘记写+号的 以及去重
        $uniquzone=[];
        $havedefault=false;
        foreach ($zones as $k=>$v){
            $v[0]=trim($v[0]);
            $v[1]=trim($v[1]);
            $zones[$k]=$v;
            if (strtolower($v[0])=='default') $havedefault=true;
            if (in_array(strtolower($v[0]),$uniquzone)){
                unset($zones[$k]);
            }else {
                array_push($uniquzone,strtolower($v[0]));
                if (!in_array(substr($v[1], 0, 1), ['+', '-'])) {
                    $zones[$k][1] = '+' . $zones[$k][1];
                }
            }
        }
        if (!$havedefault){
            array_push($zones,[
                0=>"DEFAULT",
                1=>'+0:00'
            ]);
        }//防止忘记配置DEFAULT时区
        $zones=array_values($zones);
        Utils::L('countUserBets',['时区列表', $zones], true, true);
        //创建时区统计表
        self::createCountTables($zones,'dq_third_stats_users');
        $flag=time();
        $delaytime=$flag-env('USER_REPORT_DEALY_TIME',300);
        $step = env('STATS_USER_TIME_STEP', 20000);
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        Db::startTrans();
        try {
            //写标志
            $sql="update dq_game_bets
set gb_cflagt=$flag
where gb_winlose>0 and gb_cflagt=0 and gb_bettime<={$delaytime}
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写投注统计标志：'.$rlt.','.$flag, true, true);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_cflagt');
            //写标志
            $sql="update dq_game_bets
set gb_cflag=$flag
where gb_winlose>1 and gb_cflag=0 and gb_bettime<={$delaytime}
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写结算统计标志：'.$rlt.','.$flag, true, true);
            list("max"=>$jsmax_id,"min"=>$jsmin_id)=self::getBetsIdRange($flag,$flag,'gb_cflag');
            for ($i = 0; $i < count($zones); $i++) {
                $sql = "SELECT NOW();";
                $mysql_now = Db::query($sql);
                Utils::L('countUserBets',['当前数据库时间', $mysql_now], true, true);
                $zone=strtolower($zones[$i][0]);
                Utils::L('countUserBets',['准备统计时区', $zones[$i]], true, true);
                //设置时区
                if ($zone=="default"){
                    $sql="set time_zone='".env("mysql_timezone",$zones[$i][1])."'";//mysql_timezone
                }else{
                    $sql="set time_zone='".$zones[$i][1]."'";//mysql_timezone
                }
                Db::execute($sql);
                $sql = "SELECT @@global.time_zone, @@session.time_zone;";
                $timezone = Db::query($sql);
                Utils::L('countUserBets',['转换后时区', $timezone], true, true);
                $sql = "SELECT NOW();";
                $mysql_now = Db::query($sql);
                Utils::L('countUserBets',['转换时区后数据库时间', $mysql_now], true, true);
                self::countUserBetsByZone($zone=="default"?'':("_".$zone),$flag,$min_id,$max_id,$jsmin_id,$jsmax_id);
            }
            //更新用户表savings  -- 重要提示：如果需要重新统计数据的 话，更新用户SAVING字段的这段代码要去掉
            $cjset=SysSets::getSetByCode("sys_cj_support");
            $cjset=($cjset==1);//是否支持彩金
            $watermode=intval(env('WATER_SUB_RATE_TYPE',0));//流水扣减模式
            Utils::L('countUserBets', ['模式设置：', "cjsupport"=>$cjset,"watermode"=>$watermode]);
            //增加特定流水后的扣减SAVING
            if ($cjset && $watermode==1){
                self::countAwardSavingsMode1($flag, $jsmin_id, $jsmax_id);
            }else {
                self::countAwardSavings($flag, $jsmin_id, $jsmax_id);
            }
            if ($cjset) {
                if ($watermode==0) {//0扣减模式
                    //写彩金流水日志
                    $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,current_savings,type,remark,create_time)
select id,cjsavings,1,if(savings-water>=0,0,if(cjsavings-(water-savings)>0,water-savings,cjsavings))*-1 as subsaving,0, cjsavings - if(savings-water>=0,0,if(cjsavings-(water-savings)>0,water-savings,cjsavings)),99,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减',{$flag}
from
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['写彩金流水记录：', $sql, $rlt]);
                    //写流水日志
                    $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,current_savings,type,remark,create_time)
select id,savings,1,if(savings-water>=0,water,savings)*-1 as subsaving,0,savings - if(savings-water>=0,water,savings),88,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减',{$flag}
from
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['写流水记录：', $sql, $rlt]);
                    //更新双钱包savings
                    $sql = "update
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
set cjsavings=if(savings-water>=0,cjsavings,if(cjsavings-(water-savings)>0,cjsavings-(water-savings),0)),
    savings=if(savings-water>0,savings-water,0)
where uat_uid=id";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['更新用户表双钱包savings：', $sql, $rlt]);
                }else{//1扣减模式
                    //写彩金流水日志
                    $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,current_savings,type,remark,create_time)
select id,cjsavings,1,if(cjsavings-cjwater>=0,cjwater,cjsavings)*-1 as subsaving,0, cjsavings - if(cjsavings-cjwater>=0,cjwater,cjsavings),99,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减',{$flag}
from
(
select uat_uid,sum(uat_validcjwater) as cjwater from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['写彩金流水记录：', $sql, $rlt]);
                    //写流水日志
                    $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,current_savings,type,remark,create_time)
select id,savings,1,if(savings-water>=0,water,savings)*-1 as subsaving,0,savings - if(savings-water>=0,water,savings),88,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减',{$flag}
from
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['写流水记录：', $sql, $rlt]);
                    //更新双钱包savings
                    $sql = "update
(
select uat_uid,sum(uat_vaildwater) as water,sum(uat_validcjwater) as cjwater from dq_user_award_temp group by uat_uid
) ta,dq_user
set cjsavings=if(cjsavings-cjwater>=0,cjsavings-cjwater,0),
    savings=if(savings-water>0,savings-water,0)
where uat_uid=id";
                    $rlt = Db::execute($sql);
                    Utils::L('countUserBets', ['更新用户表双钱包savings：', $sql, $rlt]);
                }
            }else {
                //不支持彩金的单钱包模式
                //写流水日志
                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,current_savings,type,remark,create_time)
select id,savings,1,if(savings-water>=0,water,savings)*-1 as subsaving,0,savings - if(savings-water>=0,water,savings),88,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减',{$flag}
from
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                $rlt = Db::execute($sql);
                Utils::L('countUserBets', ['写流水记录：' ,$sql, $rlt]);
                //更新流水
                $sql="update
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
set savings=if(savings-water>0,savings-water,0)
where uat_uid=id";
                $rlt = Db::execute($sql);
                Utils::L('countUserBets', ['更新用户表savings：',$sql, $rlt]);
            }
            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countUserBets',['------------------ERROR-------------------',$ex->getMessage(),$ex->getTraceAsString(),isset($sql)?$sql:null,'------------------ERROR-------------------'], true, true);
        }
        Utils::L('countUserBets','执行完成：'.$rlt, true, true);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
    }


    /** 统计三方平台用户日报表
     * 最晚统计到当前时间前1分钟，以免出现数据统计遗漏
     * @author twt326
     * @date 2022-10-23 19:04
     */
    public static function countUserBets_old(){
        $flag=time();
        $step = env('STATS_USER_TIME_STEP', 20000);
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //统计投注情况---------------------------
        //清除临时表数据
        $sql="TRUNCATE table dq_third_stats_users_temp";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除临时表数据：'.$rlt);
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        Db::startTrans();
        try {
            Utils::L('countUserBets','执行统计（投注）任务');
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countUserBets',['当前数据库时间', $mysql_now]);
            //统一时区
            $sql="set time_zone='".env("mysql_timezone",'+0:00')."'";//mysql_timezone
            Db::execute($sql);
            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
            $timezone = Db::query($sql);
            Utils::L('countUserBets',['时区', $timezone]);
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countUserBets',['转换时区后数据库时间', $mysql_now]);

            //写标志
            $sql="update dq_game_bets
set gb_cflagt=$flag
where gb_winlose>0 and gb_cflagt=0
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写投注统计标志：'.$rlt);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_cflagt');
            //写入投注统计数据表临时表
            $sql="insert into dq_third_stats_users_temp (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_betnum,su_bet)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,rec_id3,rec_id2,rec_id,id,username,tree,count(*) as betnum,sum(gb_price) as bet
from 
dq_game_bets,dq_user
where gb_uid=id and gb_cflagt=$flag  and gb_id between {$min_id} and {$max_id}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),gb_uid";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写入投注统计数据表临时表：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_users_temp ta,dq_third_stats_users tb 
set 
tb.su_betnum=tb.su_betnum+ta.su_betnum,
tb.su_bet=tb.su_bet+ta.su_bet
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_users
select ta.* from dq_third_stats_users_temp ta
left join dq_third_stats_users tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','不存在的记录写入：'.$rlt);
            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countUserBets',['------------------ERROR-------------------',$ex->getMessage(),$ex->getTraceAsString(),'------------------ERROR-------------------'], true, true);
        }
        //统计结算情况---------------------------
        //清除临时表数据
        $sql="TRUNCATE table dq_third_stats_users_temp";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除临时表数据：'.$rlt);
        Db::startTrans();
        try {
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countUserBets',['当前数据库时间', $mysql_now]);
            //统一时区
            $sql="set time_zone='".env("mysql_timezone",'+0:00')."'";//mysql_timezone
            Db::execute($sql);
            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
            $timezone = Db::query($sql);
            Utils::L('countUserBets',['时区', $timezone]);
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countUserBets',['转换时区后数据库时间', $mysql_now]);
            Utils::L('countUserBets','执行统计（结算）任务');

            //写标志
            $sql="update dq_game_bets
set gb_cflag=$flag
where gb_winlose>1 and gb_cflag=0
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写投注统计标志：'.$rlt);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_cflag');
            //写入结算统计数据表临时表 这里的su_bet里存放的是已结算的投注额，用于减savings的
            $sql="insert into dq_third_stats_users_temp (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_bet,su_winnum,su_win,su_drawnum,su_draw,su_validwater,su_otherwin)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,rec_id3,rec_id2,rec_id,id,username,tree,sum(gb_price) as bet,
sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
sum(IF(gb_validwater<gb_price,1,0)) as drawnum,
sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw,
sum({$validWaterField}) as validwater,
sum(gb_otherwin) as otherwin
from dq_game_bets,dq_user
where gb_uid=id and gb_cflag=$flag and gb_id between {$min_id} and {$max_id}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),gb_uid";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','写入结算统计数据表临时表：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_users_temp ta,dq_third_stats_users tb 
set 
tb.su_winnum=tb.su_winnum+ta.su_winnum,
tb.su_win=tb.su_win+ta.su_win,
tb.su_drawnum=tb.su_drawnum+ta.su_drawnum,
tb.su_draw=tb.su_draw+ta.su_draw,
tb.su_validwater=tb.su_validwater+ta.su_validwater,
tb.su_otherwin=tb.su_otherwin+ta.su_otherwin
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_users
select ta.* from dq_third_stats_users_temp ta
left join dq_third_stats_users tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countUserBets','不存在的记录写入：'.$rlt);
            //更新用户表savings  -- 重要提示：如果需要重新统计数据的 话，更新用户SAVING字段的这段代码要去掉
            /* 增加特定活动流水前
             * $sql="update
(
select su_uid,sum(su_validwater) as water from dq_third_stats_users_temp group by su_uid
) ta,dq_user
set savings=if(savings-water>0,savings-water,0)
where su_uid=id";*/
            //增加特定流水后的扣减SAVING
            self::countAwardSavings($flag,$min_id,$max_id);
            /*再次增加双钱包支持前的$sql="update
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
set savings=if(savings-water>0,savings-water,0)
where uat_uid=id";*/
            $cjset=SysSets::getSetByCode("sys_cj_support");
            $cjset=($cjset==1);//是否支持彩金
            if ($cjset) {
                //写彩金流水日志
                $sql = "insert into dq_user_water (uid,amount,multiple,water,origin_id,type,remark,create_time)
select id,cjsavings,1,if(savings-water>=0,0,if(cjsavings-(water-savings)>0,water-savings,cjsavings))*-1 as subsaving,0,99,'" . date("Y-m-d H:i:s", $flag) . "统计有效投注扣减'),{$flag}
from
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
where uat_uid=id
HAVING subsaving <0";
                $rlt = Db::execute($sql);
                Utils::L('countUserBets', '写彩金流水记录：' . $rlt);
                //更新双钱包savings
                $sql="update
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
set cjsavings=if(savings-water>=0,cjsavings,if(cjsavings-(water-savings)>0,cjsavings-(water-savings),0)),
    savings=if(savings-water>0,savings-water,0)
where uat_uid=id";
                $rlt = Db::execute($sql);
                Utils::L('countUserBets', '更新用户表双钱包savings：' . $rlt);
            }else {
                //不支持彩金的单钱包模式
                $sql="update
(
select uat_uid,sum(uat_vaildwater) as water from dq_user_award_temp group by uat_uid
) ta,dq_user
set savings=if(savings-water>0,savings-water,0)
where uat_uid=id";
                $rlt = Db::execute($sql);
                Utils::L('countUserBets', '更新用户表savings：' . $rlt);
            }

            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countUserBets',['------------------ERROR-------------------',$ex->getMessage(),$ex->getTraceAsString(),'------------------ERROR-------------------'], true, true);
        }
        Utils::L('countUserBets','执行完成：'.$rlt);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
    }

    /**
     *  优先满足特定活动要求的流水（流水扣减模式0，不区分真金彩金流水，比率固定为1）
     * @param $flagandst 需要处理的统计数据标志，同时是时间戳
     * @param $gbids 区间GBID开始
     * @param $gbide 区间GBID结束
     * @return void
     */
    private static function countAwardSavings($flagandst,$gbids,$gbide){
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //清空临时表
        $sql="delete from dq_user_award_temp";//TODO 如果影响性能，可以改为truncate table，且将它移至 countUserBets 方法的事务前执行
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除特定活动流水统计临时表：'.$rlt);
        //写入流水到临时表
        $sql="insert into dq_user_award_temp (uat_uid,uat_gid,uat_vaildwater,uat_subwater)
select gb_uid,gb_gid,sum({$validWaterField}),0 from dq_game_bets
where gb_cflag={$flagandst} and gb_id BETWEEN {$gbids} and {$gbide}
group by gb_uid,gb_gid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','写入流水到临时表：'.$rlt);
        //循环所有未完成活动
        $sql="select distinct uac_awardid as awardid from dq_user_award_cfgs where uac_done=0";
        $recc=Db::query($sql);
        if (count($recc)>0){
            for($i=0;$i<count($recc);$i++){
                Utils::L('countUserBets',['处理活动：'.$recc[$i]['awardid'],$sql]);

                //循环活动中所有的游戏ID
                $sql="select distinct ual_gid as gid from dq_user_award_logs  force index (ual_dis) where ual_awardid=".$recc[$i]['awardid'];
                $recl=Db::query($sql);
                if (count($recl)>0){
                    //临时区分同一个用户的多个未完成的同一活动
                    $cnt=1;
                    $rlt=1;
                    while($rlt>0){
                        $sql="update dq_user_award_cfgs,(select uac_id as uacid from dq_user_award_cfgs where uac_awardid=".$recc[$i]['awardid']." and uac_done=0
group by uac_uid order by uac_id) tb
set uac_done={$cnt}
where uac_id =tb.uacid";
                        $rlt=Db::execute($sql);
                        if ($rlt>0) $cnt++;
                        Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）DONE临时处理：', "cnt" =>$cnt,'rlt'=>$rlt,'sql'=>$sql]);
                    }
                    Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）最多次数处理(值为1表示表明当前活动不存在同一人多次领取未完成的情况)：', "cnt" =>$cnt]);
                    //循环同一未完成活动
                    for($k=1;$k<$cnt;$k++) {
                        //扣减单个游戏流水直至满足流水要求，同时更新待扣减流水和可供通用流水扣减的数值
                        for ($j = 0; $j < count($recl); $j++) {

                            /*set时前一个值更新会影响到后面的计算
                             * $sql = "update dq_user_award_cfgs,dq_user_award_logs,dq_user_award_temp
		set uac_waterdone=if(uac_water-uac_waterdone<=uat_vaildwater,uac_water,uac_waterdone+uat_vaildwater),
		uac_done=if(uac_water-uac_waterdone<=uat_vaildwater,{$flagandst},0),
		ual_subwater=ual_subwater+if(uac_water-uac_waterdone<=uat_vaildwater,uac_water-uac_waterdone,uat_vaildwater),
		uat_vaildwater=uat_vaildwater-if(uac_water-uac_waterdone<=uat_vaildwater,uac_water-uac_waterdone,uat_vaildwater)
		where uac_id=ual_uacid and ual_gid=uat_gid and uac_uid=uat_uid and uac_uid=ual_uid and uac_awardid=" . $recc[$i]['awardid'] . " and uac_done={$k} and ual_gid=" . $recl[$j]['gid'];*/
                            $sql = "update dq_user_award_temp,dq_user_award_cfgs,dq_user_award_logs
		set uat_subwater=if(uac_water-uac_waterdone<=uat_vaildwater,uac_water-uac_waterdone,uat_vaildwater),
		    uac_waterdone=uac_waterdone+uat_subwater,
		uac_done=if(uac_water=uac_waterdone+uat_subwater,{$flagandst},uac_done),
		ual_subwater=ual_subwater+uat_subwater,
		uat_vaildwater=uat_vaildwater-uat_subwater
		where uac_id=ual_uacid and ual_gid=uat_gid and uac_uid=uat_uid and uac_uid=ual_uid and uac_awardid=" . $recc[$i]['awardid'] . " and uac_done={$k} and ual_gid=" . $recl[$j]['gid'];
                            $rlt = Db::execute($sql);
                            if ($rlt>0) Utils::L('countUserBets', ["info" => '扣减单个游戏流水直至满足流水要求，同时更新待扣减流水和可供通用流水扣减的数值(rlt=0为正常情况)：', "gid" => $recl[$j]['gid'], "rlt" => $rlt,"sql"=>$sql]);
                        }
                        //将done的累加值替换回0
                        $sql="update dq_user_award_cfgs set uac_done=0 where uac_awardid=".$recc[$i]['awardid']." and uac_done={$k}";
                        $rlt = Db::execute($sql);
                        Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）处理(本条消息只出现一次表明当前活动不存在同一人多次领取未完成的情况)：', "k" =>$k, "rlt" => $rlt,"sql"=>$sql]);
                    }
                }
            }
        }else{
            Utils::L('countUserBets','没有特定活动流水需要处理');
        }


    }

    /**
     *  优先满足特定活动要求的流水(流水扣减模式1，真金扣真金流水，彩金扣彩金流水，且按dq_game_water_rates设定比例）
     * @param $flagandst 需要处理的统计数据标志，同时是时间戳
     * @param $gbids 区间GBID开始
     * @param $gbide 区间GBID结束
     * @return void
     */
    private static function countAwardSavingsMode1($flagandst,$gbids,$gbide){
        //由于注单表有效流水已经在MODEL中区分了，这里不再需要进行区分
        //$validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //清空临时表
        $sql="delete from dq_user_award_temp";//TODO 如果影响性能，可以改为truncate table，且将它移至 countUserBets 方法的事务前执行
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','清除特定活动流水统计临时表：'.$rlt);
        //写入流水到临时表
        $sql="insert into dq_user_award_temp (uat_uid,uat_gid,uat_vaildwater,uat_subwater,uat_validcjwater,uat_subcjwater)
select gb_uid,gb_gid,if(sum((gb_validwater-ceil(gb_cjprice/gb_price*100*gb_validwater)/100)*IFNULL(gwr_water_rate,1))>0,sum((gb_validwater-ceil(gb_cjprice/gb_price*100*gb_validwater)/100)*IFNULL(gwr_water_rate,1)),0) as subwater,0,
sum(ceil(gb_cjprice/gb_price*100*gb_validwater*ifnull(gwr_cjwater_rate,1))/100) as cjsubwater,0 from dq_game_bets
 left join dq_game_water_rates on gb_gid=gwr_gid
where gb_cflag={$flagandst} and gb_id BETWEEN {$gbids} and {$gbide}
group by gb_uid,gb_gid";
        $rlt=Db::execute($sql);
        Utils::L('countUserBets','写入流水到临时表：'.$rlt);
        //循环所有未完成活动
        $sql="select distinct uac_awardid as awardid from dq_user_award_cfgs where uac_done=0";
        $recc=Db::query($sql);
        if (count($recc)>0){
            for($i=0;$i<count($recc);$i++){
                Utils::L('countUserBets',['处理活动：'.$recc[$i]['awardid'],$sql]);

                //循环活动中所有的游戏ID
                $sql="select distinct ual_gid as gid from dq_user_award_logs  force index (ual_dis) where ual_awardid=".$recc[$i]['awardid'];
                $recl=Db::query($sql);
                if (count($recl)>0){
                    //临时区分同一个用户的多个未完成的同一活动
                    $cnt=1;
                    $rlt=1;
                    while($rlt>0){
                        $sql="update dq_user_award_cfgs,(select uac_id as uacid from dq_user_award_cfgs where uac_awardid=".$recc[$i]['awardid']." and uac_done=0
group by uac_uid order by uac_id) tb
set uac_done={$cnt}
where uac_id =tb.uacid";
                        $rlt=Db::execute($sql);
                        if ($rlt>0) $cnt++;
                        Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）DONE临时处理：', "cnt" =>$cnt,'rlt'=>$rlt,'sql'=>$sql]);
                    }
                    Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）最多次数处理(值为1表示表明当前活动不存在同一人多次领取未完成的情况)：', "cnt" =>$cnt]);
                    //循环同一未完成活动
                    for($k=1;$k<$cnt;$k++) {
                        //扣减单个游戏流水直至满足流水要求，同时更新待扣减流水和可供通用流水扣减的数值
                        for ($j = 0; $j < count($recl); $j++) {
                            $sql = "update dq_user_award_temp,dq_user_award_cfgs,dq_user_award_logs
		set uat_subwater=if(uac_balancetype=0, if(uac_water-uac_waterdone<=uat_vaildwater,uac_water-uac_waterdone,uat_vaildwater),uat_subwater),
		    uat_subcjwater=if(uac_balancetype=1, if(uac_water-uac_waterdone<=uat_validcjwater,uac_water-uat_validcjwater,uat_validcjwater),uat_subcjwater),
		    uac_waterdone=uac_waterdone+if(uac_balancetype=0,uat_subwater,uat_subcjwater),
		uac_done=if(uac_water=uac_waterdone+if(uac_balancetype=0,uat_subwater,uat_subcjwater),{$flagandst},uac_done),
		ual_subwater=if(uac_balancetype=0,ual_subwater+uat_subwater,ual_subwater),
	    ual_subcjwater=if(uac_balancetype=1,ual_subcjwater+uat_subcjwater,ual_subcjwater),
		uat_vaildwater=if(uac_balancetype=0,uat_vaildwater-uat_subwater,uat_vaildwater),
		uat_validcjwater=if(uac_balancetype=1,uat_validcjwater-uat_subcjwater,uat_validcjwater)
		where uac_id=ual_uacid and ual_gid=uat_gid and uac_uid=uat_uid and uac_uid=ual_uid and uac_awardid=" . $recc[$i]['awardid'] . " and uac_done={$k} and ual_gid=" . $recl[$j]['gid'];
                            $rlt = Db::execute($sql);
                            if ($rlt>0) Utils::L('countUserBets', ["info" => '扣减单个游戏流水直至满足流水要求，同时更新待扣减流水和可供通用流水扣减的数值(rlt=0为正常情况)：', "gid" => $recl[$j]['gid'], "rlt" => $rlt,"sql"=>$sql]);
                        }
                        //将done的累加值替换回0
                        $sql="update dq_user_award_cfgs set uac_done=0 where uac_awardid=".$recc[$i]['awardid']." and uac_done={$k}";
                        $rlt = Db::execute($sql);
                        Utils::L('countUserBets', ["info" => '同一活动多次领取（未完成）处理(本条消息只出现一次表明当前活动不存在同一人多次领取未完成的情况)：', "k" =>$k, "rlt" => $rlt,"sql"=>$sql]);
                    }
                }
            }
        }else{
            Utils::L('countUserBets','没有特定活动流水需要处理');
        }


    }


    public static function countStatGlobalByZone($cutex='',$ex,$flag,$min_id,$max_id,$jsmin_id,$jsmax_id){
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        //清除临时表数据
        $sql="delete from dq_third_stats_global_temp";
        $rlt=Db::execute($sql);
        Utils::L('countStatGlobal','清除统计临时表数据：'.$rlt);
        //清除临时表数据
        $sql="delete from dq_third_stats_global_temp_js";
        $rlt=Db::execute($sql);
        Utils::L('countStatGlobal','清除结算临时表数据：'.$rlt);
        if (is_array($flag)){
            if (count($flag[0])>0){
                $where = "gb_id between {$min_id} and {$max_id} and gb_gcflag in (".implode(',',$flag[0]).")";
            }else{
                $where = "gb_id =0";
            }

        }else {
            $where = "gb_gcflag=$flag and gb_id between {$min_id} and {$max_id}";
        }
            //写入投注统计数据表临时表
            $sql="insert into dq_third_stats_global_temp (su_dt,su_tcid,su_gid,su_gtype,rec_id3,rec_id2,rec_id,tree,su_uid,su_label,su_betnum,su_bet)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,g_tcid,g_id,g_type,rec_id3,rec_id2,rec_id,tree,id,lebal,count(*) as betnum,sum(gb_price) as bet
from 
dq_user,dq_game_bets{$cutex}
left join dq_games on gb_gid=g_id
where gb_uid=id and {$where}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),id,gb_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写入综合投注统计数据表临时表：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_global_temp ta,dq_third_stats_global{$ex} tb
set
tb.su_betnum=tb.su_betnum+ta.su_betnum,
tb.su_bet=tb.su_bet+ta.su_bet
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_global{$ex}
select ta.* from dq_third_stats_global_temp ta
left join dq_third_stats_global{$ex} tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','不存在的记录写入：'.$rlt);

        //统计结算情况---------------------------
        if (is_array($flag)){
            if (count($flag[1])>0){
                $where = "gb_id between {$jsmin_id} and {$jsmax_id} and gb_gcflagt in (".implode(',',$flag[1]).")";
            }else{
                $where = "gb_id =0";
            }

        }else {
            $where = "gb_gcflagt=$flag and gb_id between {$jsmin_id} and {$jsmax_id}";
        }
            //写入结算统计数据表临时表
            $sql="insert into dq_third_stats_global_temp_js (su_dt,su_tcid,su_gid,su_gtype,rec_id3,rec_id2,rec_id,tree,su_uid,su_label,su_winnum,su_win,su_drawnum,su_draw,su_valid_bet,su_syswin)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,g_tcid,g_id,g_type,rec_id3,rec_id2,rec_id,tree,id,lebal,
sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
sum(IF(gb_validwater<gb_price,1,0)) as drawnum,sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw,
SUM({$validWaterField}) as bet_valid_amount,
sum(gb_otherwin*-1) as su_syswin
from
dq_user,dq_game_bets{$cutex}
left join dq_games on gb_gid=g_id
where gb_uid=id and {$where}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),id,gb_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写入结算综合统计数据表临时表：'.$rlt);
            // 系统输赢直接统计注单表字段[gb_otherwin*-1]
//            //更新系统输赢 TODO su_win中统计的是否包含投注额，如果包含则需要减掉
//            $sql="update dq_third_stats_global_temp_js set su_syswin=su_valid_bet-su_win";
//            $rlt=Db::execute($sql);
//            Utils::L('countStatGlobal','更新系统输赢：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_global_temp_js ta,dq_third_stats_global{$ex} tb
set
tb.su_losenum=tb.su_losenum+ta.su_losenum,
tb.su_lose=tb.su_lose+ta.su_lose,
tb.su_winnum=tb.su_winnum+ta.su_winnum,
tb.su_win=tb.su_win+ta.su_win,
tb.su_drawnum=tb.su_drawnum+ta.su_drawnum,
tb.su_draw=tb.su_draw+ta.su_draw,
tb.su_valid_bet=tb.su_valid_bet+ta.su_valid_bet,
tb.su_syswin=tb.su_syswin+ta.su_syswin
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_global{$ex}
select ta.* from dq_third_stats_global_temp_js ta
left join dq_third_stats_global{$ex} tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','不存在的记录写入：'.$rlt);


    }


    /** 统计三方平台综合报表
     * 最晚统计到当前时间前1分钟，以免出现数据统计遗漏
     * @param string $cutex 带_的分表记录后缀，用于统计分表记录
     * @author twt326
     * @date 2022-10-23 19:04
     */
    public static function countStatGlobal($cutex=''){
        $c=Redis::get('rebuildcountstat_dq_third_stats_global');
        if ($c){
            Utils::L('countStatGlobal',['正在重建数据，本次不执行'], true, true);
            return;
        }
        //读取配置
        $zones=SysSets::getSetByCode('COUNT_DATA_TIMEZONE_LIST');
        if (empty($zones)){
            $zones=[
                [
                    0=>"DEFAULT",
                    1=>'+0:00'
                ]
            ];
        }
        //处理时区，忘记写+号的 以及去重
        $uniquzone=[];
        $havedefault=false;
        foreach ($zones as $k=>$v){
            $v[0]=trim($v[0]);
            $v[1]=trim($v[1]);
            $zones[$k]=$v;
            if (strtolower($v[0])=='default') $havedefault=true;
            if (in_array(strtolower($v[0]),$uniquzone)){
                unset($zones[$k]);
            }else {
                array_push($uniquzone,strtolower($v[0]));
                if (!in_array(substr($v[1], 0, 1), ['+', '-'])) {
                    $zones[$k][1] = '+' . $zones[$k][1];
                }
            }
        }
        if (!$havedefault){
            array_push($zones,[
                0=>"DEFAULT",
                1=>'+0:00'
            ]);
        }//防止忘记配置DEFAULT时区
        $zones=array_values($zones);
        Utils::L('countUserBets',['时区列表', $zones], true, true);
        //创建时区统计表
        self::createCountTables($zones,'dq_third_stats_global');
        $flag=time();
        $delaytime=$flag-env('USER_REPORT_DEALY_TIME',300);
        $step = env('STATS_USER_TIME_STEP', 20000);
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        Db::startTrans();

        try {
            //写标志
            $sql="update dq_game_bets{$cutex} 
set gb_gcflag=$flag
where gb_winlose>0 and gb_gcflag=0 and gb_bettime<={$delaytime}
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写综合投注统计标志：'.$rlt, true, true);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_gcflag',$cutex);
            //写标志
            $sql="update dq_game_bets{$cutex}
set gb_gcflagt=$flag
where gb_winlose>1 and gb_gcflagt=0 and gb_bettime<={$delaytime}
limit $step";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写综合投注结算统计标志：'.$rlt, true, true);
            list("max"=>$jsmax_id,"min"=>$jsmin_id)=self::getBetsIdRange($flag,$flag,'gb_gcflagt',$cutex);
            for ($i = 0; $i < count($zones); $i++) {
                $sql = "SELECT NOW();";
                $mysql_now = Db::query($sql);
                Utils::L('countStatGlobal',['当前数据库时间', $mysql_now], true, true);
                $zone=strtolower($zones[$i][0]);
                Utils::L('countStatGlobal',['准备统计时区', $zones[$i]], true, true);
                //设置时区
                if ($zone=="default"){
                    $sql="set time_zone='".env("mysql_timezone",$zones[$i][1])."'";//mysql_timezone
                }else{
                    $sql="set time_zone='".$zones[$i][1]."'";//mysql_timezone
                }
                Db::execute($sql);
                $sql = "SELECT @@global.time_zone, @@session.time_zone;";
                $timezone = Db::query($sql);
                Utils::L('countStatGlobal',['转换后时区', $timezone], true, true);
                $sql = "SELECT NOW();";
                $mysql_now = Db::query($sql);
                Utils::L('countStatGlobal',['转换时区后数据库时间', $mysql_now], true, true);
                self::countStatGlobalByZone($cutex,$zone=="default"?'':("_".$zone),$flag,$min_id,$max_id,$jsmin_id,$jsmax_id);
            }

            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countStatGlobal',['------------------ERROR-------------------',$ex->getMessage(),$flag,'------------------ERROR-------------------'], true, true);
        }
        Utils::L('countStatGlobal','执行完成：'.$rlt, true, true);

    }

    public static function countStatGlobal_old($cutex=''){
        $validWaterField=intval(env('VALIDWATER_MODE',0))==1?'gb_price':'gb_validwater';
        $flag=time();
        $step = env('STATS_USER_TIME_STEP', 20000);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
        //统计投注情况---------------------------
        Db::startTrans();
        try {
            Utils::L('countStatGlobal','执行综合统计（投注）任务');
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countStatGlobal',['当前数据库时间', $mysql_now]);
            //统一时区
            $sql="set time_zone='".env("mysql_timezone",'+0:00')."'";//mysql_timezone
            Db::execute($sql);
            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
            $timezone = Db::query($sql);
            Utils::L('countStatGlobal',['时区', $timezone]);
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countStatGlobal',['转换时区后数据库时间', $mysql_now]);
            //清除临时表数据
            $sql="TRUNCATE table dq_third_stats_global_temp";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','清除临时表数据：'.$rlt);
            //写标志
            $sql="update dq_game_bets{$cutex} 
set gb_gcflag=$flag
where gb_winlose>0 and gb_gcflag=0
limit $step";
            $rlt=Db::execute($sql);
            if (!$rlt){
                throw new Exception('没有待统计的注单记录');
                Utils::L('countStatGlobal','没有待统计的注单记录');
                return;
            }
            Utils::L('countStatGlobal','写综合投注统计标志：'.$rlt);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_gcflag',$cutex);
            //写入投注统计数据表临时表
            $sql="insert into dq_third_stats_global_temp (su_dt,su_tcid,su_gid,su_gtype,rec_id3,rec_id2,rec_id,tree,su_uid,su_label,su_betnum,su_bet)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,g_tcid,g_id,g_type,rec_id3,rec_id2,rec_id,tree,id,lebal,count(*) as betnum,sum(gb_price) as bet
from 
dq_user,dq_game_bets{$cutex}
left join dq_games on gb_gid=g_id
where gb_uid=id and gb_gcflag=$flag and gb_id between {$min_id} and {$max_id}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),id,gb_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写入综合投注统计数据表临时表：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_global_temp ta,dq_third_stats_global tb
set
tb.su_betnum=tb.su_betnum+ta.su_betnum,
tb.su_bet=tb.su_bet+ta.su_bet
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_global
select ta.* from dq_third_stats_global_temp ta
left join dq_third_stats_global tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','不存在的记录写入：'.$rlt);
            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countStatGlobal',['------------------ERROR-------------------',$ex->getMessage(),$flag,'------------------ERROR-------------------'], true, true);
        }
        //统计结算情况---------------------------
        Db::startTrans();
        try {
            Utils::L('countStatGlobal','执行综合统计（结算）任务');
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countStatGlobal',['当前数据库时间', $mysql_now]);
            //统一时区
            $sql="set time_zone='".env("mysql_timezone",'+0:00')."'";//mysql_timezone
            Db::execute($sql);
            $sql = "SELECT @@global.time_zone, @@session.time_zone;";
            $timezone = Db::query($sql);
            Utils::L('countStatGlobal',['时区', $timezone]);
            $sql = "SELECT NOW();";
            $mysql_now = Db::query($sql);
            Utils::L('countStatGlobal',['转换时区后数据库时间', $mysql_now]);
            //清除临时表数据
            $sql="TRUNCATE table dq_third_stats_global_temp";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','清除临时表数据：'.$rlt);
            //写标志
            $sql="update dq_game_bets{$cutex}
set gb_gcflagt=$flag
where gb_winlose>1 and gb_gcflagt=0
limit $step";
            $rlt=Db::execute($sql);
            if (!$rlt){
                throw new Exception('没有待统计的注单记录');
                Utils::L('countStatGlobal','没有待统计的注单记录');
                return;
            }
            Utils::L('countStatGlobal','写综合投注结算统计标志：'.$rlt);
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($flag,$flag,'gb_gcflagt',$cutex);
            //写入结算统计数据表临时表
            $sql="insert into dq_third_stats_global_temp (su_dt,su_tcid,su_gid,su_gtype,rec_id3,rec_id2,rec_id,tree,su_uid,su_label,su_winnum,su_win,su_drawnum,su_draw,su_valid_bet,su_syswin)
select FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,g_tcid,g_id,g_type,rec_id3,rec_id2,rec_id,tree,id,lebal,
sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
sum(IF(gb_validwater<gb_price,1,0)) as drawnum,sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw,
SUM({$validWaterField}) as bet_valid_amount,
sum(gb_otherwin*-1) as su_syswin
from
dq_user,dq_game_bets{$cutex}
left join dq_games on gb_gid=g_id
where gb_uid=id and gb_gcflagt=$flag and gb_id between {$min_id} and {$max_id}
group by FROM_UNIXTIME(gb_bettime,'%Y%m%d'),id,gb_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','写入结算综合统计数据表临时表：'.$rlt);
            // 系统输赢直接统计注单表字段[gb_otherwin*-1]
//            //更新系统输赢 TODO su_win中统计的是否包含投注额，如果包含则需要减掉
//            $sql="update dq_third_stats_global_temp set su_syswin=su_valid_bet-su_win";
//            $rlt=Db::execute($sql);
//            Utils::L('countStatGlobal','更新系统输赢：'.$rlt);
            //存在的记录更新 与写入不能互换顺序，更新必须在前
            $sql="update dq_third_stats_global_temp ta,dq_third_stats_global tb
set
tb.su_losenum=tb.su_losenum+ta.su_losenum,
tb.su_lose=tb.su_lose+ta.su_lose,
tb.su_winnum=tb.su_winnum+ta.su_winnum,
tb.su_win=tb.su_win+ta.su_win,
tb.su_drawnum=tb.su_drawnum+ta.su_drawnum,
tb.su_draw=tb.su_draw+ta.su_draw,
tb.su_valid_bet=tb.su_valid_bet+ta.su_valid_bet,
tb.su_syswin=tb.su_syswin+ta.su_syswin
where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','存在的记录更新：'.$rlt);
            //不存在的记录写入
            $sql="insert into dq_third_stats_global
select ta.* from dq_third_stats_global_temp ta
left join dq_third_stats_global tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_gid=tb.su_gid
where tb.su_dt is null";
            $rlt=Db::execute($sql);
            Utils::L('countStatGlobal','不存在的记录写入：'.$rlt);

            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('countUserBets',['------------------ERROR-------------------',$ex->getMessage(),$flag,'------------------ERROR-------------------'], true, true);
        }
        Utils::L('countStatGlobal','执行完成：'.$rlt);
        Db::execute("set @@session.transaction_isolation='REPEATABLE-READ'");
    }


    /** 根据指定的查询条件构建综合统计KEY
     * @param null $tcid
     * @param null $proxyid
     * @param null $label
     * @param null $gid
     * @param null $gtype
     * @param null $zone 时区标识
     * @return bool|string
     * @author twt326
     * @date 2023-01-25 3:51
     */
    public static function getCountStatGlobalBetUsersKey($tcid=null,$proxyid=null,$label=null,$gid=null,$gtype=null,$zone=null){
        /*if(!$tcid && !$proxyid  && !$label && !$gid && !$gtype){
            return false;
        }*/
        return
            ($tcid===null?"000":$tcid).'-'.
            ($proxyid===null?"000":$proxyid).'-'.
            ($label===null?"000":$label).'-'.
            ($gid===null?"000":$gid).'-'.
            ($gtype===null?"000":$gtype).'-'.
            ($zone===null?"000":$zone);
    }

    /**
     * 根据指定的条件对投注人数进行统计缓存 需要查询时直接按key和日期查询即可
     * @param string $key 由三方平台id-代理用户id(不区分内外部)-用户标签-游戏id-游戏类型组成的字符串，无条件的用000代替
     * @param string|int $dm 月份,格式支持同\app\common\FastGame\Base\Utils::getYmDate
     */
    public static function countStatGlobalBetUsers($key,$dm){
        $timers=time();
        $dm=\app\common\FastGame\Base\Utils::getYmDate($dm);
        if (!$dm){
            Utils::L('countStatGlobalBetUsers', '时间格式指定错误');
            return;
        }
        if (!$key){
            Utils::L('countStatGlobalBetUsers', 'KEY格式指定错误');
            return;
        }
        Utils::L('countStatGlobalBetUsers',['准备生成投注缓存',$key,$dm]);
        //是否从分表中拿数据
        $cutex=\app\common\FastGame\Base\Utils::getCutTableEx('game_bets',$dm);
        Utils::L('countStatGlobalBetUsers',['分表后缀',$cutex]);
        $keys=explode('-', $key);
        $tcid=($keys[0]=="000"?false:$keys[0]);
        $proxy=($keys[1]=="000"?false:$keys[1]);
        $label=($keys[2]=="000"?false:$keys[2]);
        $gid=($keys[3]=="000"?false:$keys[3]);
        $gtype=($keys[4]=="000"?false:$keys[4]);
        $zone=isset($keys[5])?($keys[5]=="000"?false:strtolower($keys[5])):false;
        $zones=SysSets::getSetByCode('COUNT_DATA_TIMEZONE_LIST');
        if (empty($zones)){
            $zones=[
                [
                    0=>"DEFAULT",
                    1=>'+0:00'
                ]
            ];
        }
        $zones=array_column($zones,1,0);
        foreach ($zones as $k=>$v) {
            $zones[strtolower($k)]=$v;
        }
        if ($zone && isset($zones[$zone])){
            if ($zone=='default'){
                $sql="set time_zone='".env("mysql_timezone",$zones[$zone])."'";//mysql_timezone
            }else{
                $sql = "set time_zone='" . $zones[$zone] . "'";//mysql_timezone
            }
        }else{
            $sql="set time_zone='".env("mysql_timezone",'+0:00')."'";//mysql_timezone
        }
        $rlt=Db::execute($sql);
        //删除未完整统计的数据
        $sql="delete from dq_third_stats_global_betusernum where sus_key='$key' and sus_done=0 and sus_dt>={$dm}01 and sus_dt<{$dm}32";
        $rlt=Db::execute($sql);
        Utils::L('countStatGlobalBetUsers','删除未完整统计的数据：'.$rlt);
        //获取应统计数据的时间段开始时间
        $sql="select max(sus_dt) as dt from dq_third_stats_global_betusernum where sus_key='$key' and sus_dt>={$dm}01 and sus_dt<{$dm}32";
        $rlt=Db::query($sql);
        if($rlt && count($rlt)==1 && $rlt[0]['dt']){
            $rlt=$rlt[0]['dt'];
            $s=substr($rlt,0,4)."-".substr($rlt,4,2)."-".substr($rlt,6);
            $stime=strtotime($s." 23:59:59")+1;
            $etime=JsTaskCut::nextmonth(substr($rlt,0,6),1);
            $etime=$etime['st'];
        }else{
            $t=JsTaskCut::nextmonth($dm,0);
            $stime=$t['st'];
            $etime=$t['et']+1;
        }
        if ($stime!=$etime) {
            list("max"=>$max_id,"min"=>$min_id)=self::getBetsIdRange($stime,$etime,'gb_bettime',$cutex);
            //昨日 用于确定数据统计日是否已完整（昨日和今日的数据设置为未完整统计
            $yst = date("Ymd", time() - 86400 * 24);
            Utils::L('countStatGlobalBetUsers', ['获取应统计数据的时间段开始时间：', $stime, $etime, $yst]);
            $sql="insert into dq_third_stats_global_betusernum
select dt,'{$key}' as skey,count(*) as num,if(dt>{$yst},0,1) as sdone from (
select distinct FROM_UNIXTIME(gb_bettime,'%Y%m%d') as dt,gb_uid
from 
dq_user,dq_game_bets{$cutex} ";
            if ($gtype !==false) {
                $sql .= " left join dq_games on gb_gid=g_id";
            }
            if ($proxy !==false) {
                $sql .= " left join dq_temp_fyfy_proxy_users on gb_uid=tfpu_uid";
            }
            $sql.=" where gb_uid=id and gb_bettime>=$stime and gb_bettime<$etime and gb_id between {$min_id} and {$max_id}";
            if ($tcid !==false) {
                $sql .= " and gb_tcid=$tcid ";
            }
            if ($proxy !==false) {
                $sql .= " and (tfpu_proxyuid=$proxy or tfpu_proxysubuid=$proxy) ";
            }
            if ($label !==false) {
                $sql .= " and lebal=$label ";
            }
            if ($gid !==false) {
                $sql .= " and gb_gid=$gid ";
            }
            if ($gtype !==false) {
                $sql .= " and g_type=$gtype";
            }
            $sql.=" ) tb group by dt";
            $rlt=Db::execute($sql);
            $timere=time();
            Utils::L('countStatGlobalBetUsers',['完成统计数据：'.$rlt,$timers,$timere,$timere-$timers]);
        }else{
            Utils::L('countStatGlobalBetUsers','时间段内不需要重新统计');
        }

    }


    /**
     * 热表记录迁移  -- 分钟任务
     * @author twt326
     * @date 2023-01-30 19:53
     */
    public static function transHotBets(){
        /**
         * 复杂业务处理逻辑--作为参考
         * -- 热表
        -- 将bets model 表名改为热表 dq_game_bets_hot
        -- 分钟任务：
        -- -- 写标志后再执行下述操作
        -- -- bak表有字段记录原状态和新状态及时间
        -- 失效注单：
        -- -- bets表中不存在的写入
        -- -- bets表中存在的且为失效的 存到bak表
        -- -- bets表中存在的，且不为失效的 更新为失效，并写入bak表
        -- 未结算的注单且超过X小时的
        -- -- bets表中不存在的，写入
        -- -- bets表中存在且为失效的，更新，并存到bak表
        -- -- bets表中存在且为未结算的 存到bak表
        -- -- bets表中存在且为结算的 存到bak表
        -- 已结算的注单
        -- -- 已结算但bets表中存在未结算记录的更新
        -- -- 已结算但bets表中不存在记录的写入
        -- -- 已结算但bets表中存在结算记录的
        -- -- -- 结算状态一致的存到bak
        -- -- -- 结算状态不一致的更新，并存到bak
        -- -- 已结算但bets表中存在失效记录的更新，并存到bak表
        -- 删除（已有标志的部分）
        -- 已结算订单保留X分钟
        -- 已失效订单保留X小时
        -- 未结算订单保留X天
         */

        /**
         * 初始化热表数据（需停机执行）
         *insert into dq_game_bets_hot (gb_gid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid)
        select ta.gb_gid,ta.gb_uid,ta.gb_intime,ta.gb_price,ta.gb_sn,ta.gb_winlose,ta.gb_bettime,ta.gb_overtime,ta.gb_winprice,ta.gb_backstateks,ta.gb_cusfield,ta.gb_betinfo,ta.gb_tcid from dq_game_bets ta
        left join dq_game_bets_hot tb on ta.gb_gid=tb.gb_gid and ta.gb_sn=tb.gb_sn
        where ta.gb_winlose=1 and tb.gb_id is null
         */

        $state=Redis::get('task_transHotBets_state');
        $statetime=Redis::get('task_transHotBets_state_time');
        Utils::L('transHotBets','热表数据迁移----------------开始');
        if ($state==null || $statetime==null || $state==0 || $statetime<time()-600) {
            Redis::set('task_transHotBets_state',1);
            Redis::set('task_transHotBets_state_time',time());
            //经业务了解后的简化逻辑
            //TODO 运营后观测看是否要降低删除的频率
            //已结算注单处理 -- 直接迁移到bets，重复的更新(老记录写入back)，不重复的写入
            Utils::L('transHotBets','热表数据迁移----已结算的');
            try {
                Db::startTrans();
                //统一时区
                $sql="set time_zone='".env("mysql_timezone",'-3:00')."'";//mysql_timezone
                Db::execute($sql);
                $st=time();
                $st1="20".$st;
                $st2="21".$st;
                //写记录写标志，区分重复
                $sql="update dq_game_bets_hot ta left join dq_game_bets tb on ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
set ta.gb_transflag=if(tb.gb_id is null,{$st1},{$st2})
where ta.gb_transflag=0 and ta.gb_winlose>1";
                $rlt=Db::execute($sql);
                if ($rlt>0) {
                    Utils::L('transHotBets','需要处理的数据：'.$rlt);
                    //重复写入back
                    $sql="insert into dq_game_bets_backex (gb_oldid,gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater,gb_transtime)
select tb.gb_id,tb.gb_gid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_cjprice,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_wincjprice,tb.gb_backstateks,tb.gb_cusfield,tb.gb_betinfo,tb.gb_tcid,tb.gb_cflag,tb.gb_cflagt,tb.gb_gcflag,tb.gb_gcflagt,tb.gb_trans_no,tb.gb_otherwin,tb.gb_validwater,{$st} from dq_game_bets_hot ta,dq_game_bets tb where ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn and ta.gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','备份注单表数据：'.$rlt);
                    //更新记录
                    $sql="update dq_game_bets_hot ta,dq_game_bets tb
set
tb.gb_price=ta.gb_price,
tb.gb_cjprice=ta.gb_cjprice,
tb.gb_winlose=ta.gb_winlose,
tb.gb_overtime=ta.gb_overtime,
tb.gb_winprice=ta.gb_winprice,
tb.gb_wincjprice=ta.gb_wincjprice,
tb.gb_cusfield=ta.gb_cusfield,
tb.gb_betinfo=CONCAT(tb.gb_betinfo,'|||',ta.gb_betinfo),
tb.gb_trans_no=ta.gb_trans_no,
tb.gb_otherwin=ta.gb_otherwin,
tb.gb_validwater=ta.gb_validwater 
where ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn and ta.gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','迁移（更新）热表数据：'.$rlt);
                    //不重复迁移
                    $sql="insert into dq_game_bets (gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater)
select gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater from dq_game_bets_hot
where gb_transflag={$st1}";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','迁移（新增）热表数据：'.$rlt);
                    //写附加表
                    //更新
                    $sql="update  
dq_game_bets_hot ta,dq_game_bets tb
left join dq_game_betsex tc on tc.gbe_id=tb.gb_id
set tc.gbe_betinfo=concat(tc.gbe_betinfo,tb.gb_betinfo)
where ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
and (ta.gb_transflag={$st1} or ta.gb_transflag={$st2})
and tc.gbe_id is not null";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','更新betinfo到附表：'.$rlt);
                    //插入
                    $sql="insert into dq_game_betsex
select tb.gb_id,FROM_UNIXTIME(tb.gb_bettime,'%Y%m') as gbe_inmonth,tb.gb_betinfo
from dq_game_bets_hot ta,dq_game_bets tb
left join dq_game_betsex tc on tc.gbe_id=tb.gb_id
where ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
and (ta.gb_transflag={$st1} or ta.gb_transflag={$st2})
and tc.gbe_id is null";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','写入BETINFO到附表：'.$rlt);
                    //清除主表字段
                    $sql="update  
dq_game_bets_hot ta,dq_game_bets tb
left join dq_game_betsex tc on tc.gbe_id=tb.gb_id
set tb.gb_betinfo=null
where ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
and (ta.gb_transflag={$st1} or ta.gb_transflag={$st2})";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','清除主表BETINFO字段：'.$rlt);
                    //写附加表结束==========================================
                    //删除
                    $sql="delete from dq_game_bets_hot where gb_transflag={$st1} or gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Db::commit();
                    Utils::L('transHotBets','删除热表数据：'.$rlt);
                }else{
                    Db::rollback();
                    Utils::L('transHotBets','热表数据迁移----已结算的--没有需要迁移的数据');
                }
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L('transHotBets','热表数据迁移----已结算的--错误：'.$ex->getMessage());
                Utils::L('transHotBetsErr', ['已结算注单迁移错误', $ex->getMessage()]);
            }
            //------------------已结算注单处理完毕
            //失效注单处理 X小时前的直接迁移到bets，不重复的写入，重复的进back
            //TODO 先定义为3小时，后续根据运营实际数据再进行调整（主要是调小），因失效订单激活不像结算那样会隔太长时间
            Utils::L('transHotBets','热表数据迁移----无效的');

            try {
                Db::startTrans();

                $st=time();
                $tst = $st - 3 * 3600;
                $st1="90".$st;
                $st2="91".$st;
                //写记录写标志，区分重复
                $sql="update dq_game_bets_hot ta left join dq_game_bets tb on ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
set ta.gb_transflag=if(tb.gb_id is null,{$st1},{$st2})
where ta.gb_transflag=0 and ta.gb_winlose=0 and ta.gb_bettime<{$tst}";
                $rlt=Db::execute($sql);
                if ($rlt>0) {
                    Utils::L('transHotBets','需要处理的数据：'.$rlt);
                    //重复写入back(bets表保留)
                    $sql="insert into dq_game_bets_backex (gb_oldid,gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater,gb_transtime)
select tb.gb_id,tb.gb_gid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_cjprice,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_wincjprice,tb.gb_backstateks,tb.gb_cusfield,tb.gb_betinfo,tb.gb_tcid,tb.gb_cflag,tb.gb_cflagt,tb.gb_gcflag,tb.gb_gcflagt,tb.gb_trans_no,tb.gb_otherwin,tb.gb_validwater,{$st} from dq_game_bets_hot tb
where tb.gb_transflag={$st2}";

                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','备份热表数据：'.$rlt);
                    //不重复迁移
                    $sql="insert into dq_game_bets (gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater)
select gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater from dq_game_bets_hot
where gb_transflag={$st1}";

                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','迁移（新增）热表数据：'.$rlt);
                    //删除
                    $sql="delete from dq_game_bets_hot where gb_transflag={$st1} or gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Db::commit();
                    Utils::L('transHotBets','删除热表数据：'.$rlt);
                }else{
                    Db::rollback();
                    Utils::L('transHotBets','热表数据迁移----无效的--没有需要迁移的数据');
                }
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L('transHotBets','热表数据迁移----无效的--错误：'.$ex->getMessage());
                Utils::L('transHotBetsErr', ['失效注单迁移错误', $ex->getMessage()]);
            }
            //------------------失效注单处理完毕
            //未结算注单处理 X天前的直接迁移到bets，不重复的写入，重复的进back
            //TODO 根据分表逻辑，先定义为超过27天的进行迁移（如果超过27天的情况可能会导致分表数据无法对齐）
            Utils::L('transHotBets','热表数据迁移----未结算的');
            try {
                Db::startTrans();
                $st=time();
                $tst = $st - 27 * 86400;
                $st1="10".$st;
                $st2="11".$st;
                //写记录写标志，区分重复
                $sql="update dq_game_bets_hot ta left join dq_game_bets tb on ta.gb_tcid=tb.gb_tcid and ta.gb_sn=tb.gb_sn
set ta.gb_transflag=if(tb.gb_id is null,{$st1},{$st2})
where ta.gb_transflag=0 and ta.gb_winlose=1 and ta.gb_bettime<{$tst}";
                $rlt=Db::execute($sql);
                if ($rlt>0) {
                    Utils::L('transHotBets','需要处理的数据：'.$rlt);
                    //重复写入back(bets表保留)
                    $sql="insert into dq_game_bets_backex (gb_oldid,gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater,gb_transtime)
select tb.gb_id,tb.gb_gid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_cjprice,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_wincjprice,tb.gb_backstateks,tb.gb_cusfield,tb.gb_betinfo,tb.gb_tcid,tb.gb_cflag,tb.gb_cflagt,tb.gb_gcflag,tb.gb_gcflagt,tb.gb_trans_no,tb.gb_otherwin,tb.gb_validwater,{$st} from dq_game_bets_hot tb
where tb.gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','备份热表数据：'.$rlt);
                    //不重复迁移
                    $sql="insert into dq_game_bets (gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater)
select gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater from dq_game_bets_hot
where gb_transflag={$st1}";
                    $rlt=Db::execute($sql);
                    Utils::L('transHotBets','迁移（新增）热表数据：'.$rlt);
                    //删除
                    $sql="delete from dq_game_bets_hot where gb_transflag={$st1} or gb_transflag={$st2}";
                    $rlt=Db::execute($sql);
                    Db::commit();
                    Utils::L('transHotBets','删除热表数据：'.$rlt);
                }else{
                    Db::rollback();
                    Utils::L('transHotBets','热表数据迁移----未结算的--没有需要迁移的数据');
                }
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L('transHotBets','热表数据迁移----未结算的--错误：'.$ex->getMessage());
                Utils::L('transHotBetsErr', ['未结算注单迁移错误', $ex->getMessage()]);
            }
            //------------------未结算注单处理完毕
            Redis::set('task_transHotBets_state',0);
            Utils::L('transHotBets','热表数据迁移----------------结束');
        }
    }
    /*
    select * from dq_third_stats_back

    -- 用户返水
    -- 写入需要返水的用户及投注额
    insert into dq_third_stats_back (sb_dt,sb_uid,sb_viplvl,sb_gtid,sb_win,sb_winnum,sb_draw,sb_drawnum,sb_backbase,sb_statstime)
    select 20201020 as sb_dt,gb_uid as sb_uid,level_id as sb_viplvl,g_type as sb_gtid,
    sum(gb_winprice) as sb_win,
    sum(if(gb_winprice>0,1,0)) as sb_winnum,
    sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as sb_draw,
    sum(IF(gb_validwater<gb_price,1,0)) as sb_drawnum,
    sum(gb_validwater) as sb_backbase,
    132546 as sb_statstime
    from dq_game_bets,dq_user,dq_games,dq_third_configs
    where gb_uid=id and gb_gid=g_id and g_tcid=tc_id and tc_status=1 and gb_bettime BETWEEN 1664264277 and 1665990461 and gb_winlose>1 and g_state=1 and g_backstatus=1 and status=1 and lebal=1
    group by gb_uid,g_type
    -- 根据游戏组织SQL更新返佣金额(循环)

    select distinct sb_gtid from dq_third_stats_back where sb_dt=20201020
    select
    (
        case sb_viplvl
        when 0 then 0
        when 1 then (
            case
            when sb_backbase>1000 then (sb_backbase-100)*0.05+500*0.01+400*0.005+100*0.001
            when sb_backbase>500 then (sb_backbase-500)*0.01+400*0.005+100*0.001
            when sb_backbase>100 then (sb_backbase-100)*0.005+100*0.001
            else
            sb_backbase*0.001
            end
        )
        when 8 then (
        case
            when sb_backbase>1000 then (sb_backbase-100)*0.05+500*0.01+400*0.005+100*0.001
            when sb_backbase>500 then (sb_backbase-500)*0.01+400*0.005+100*0.001
            when sb_backbase>100 then (sb_backbase-100)*0.005+100*0.001
            else
            sb_backbase*0.001
            end
        )
        else
        0
        end
    )
    as backmoney
    ,dq_third_stats_back.*
    from
    dq_third_stats_back where sb_gid=4 and sb_dt=20201020


    update
    dq_third_stats_back
    set sb_backmoney=(
        case sb_viplvl
        when 0 then 0
        when 1 then (
            case
            when sb_backbase>1000 then (sb_backbase-100)*0.05+500*0.01+400*0.005+100*0.001
            when sb_backbase>500 then (sb_backbase-500)*0.01+400*0.005+100*0.001
            when sb_backbase>100 then (sb_backbase-100)*0.005+100*0.001
            else
            sb_backbase*0.001
            end
        )
        when 8 then (
        case
            when sb_backbase>1000 then (sb_backbase-100)*0.05+500*0.01+400*0.005+100*0.001
            when sb_backbase>500 then (sb_backbase-500)*0.01+400*0.005+100*0.001
            when sb_backbase>100 then (sb_backbase-100)*0.005+100*0.001
            else
            sb_backbase*0.001
            end
        )
        else
        0
        end
    )
    where sb_gid=4 and sb_dt=20201020

    -- 不分阶梯和金额区间
    update
    dq_third_stats_back
    set sb_backmoney=(
        case sb_viplvl
        when 1 then sb_backbase*0.02
        when 2 then sb_backbase*0.03
        else
        0
        end
    )
    where sb_gtid=4 and sb_dt=20201020

    -- 自动发放用户返水部分（每日）事务 ,按游戏循环
    -- 变更记录为已返水
    update dq_third_stats_back
    set sb_payed=2,sb_paytime=123456
    where sb_dt=20201020 and sb_gtid=4

    -- 增加用户余额
    update dq_third_stats_back,dq_user
    set balance=balance+sb_backmoney
    where sb_uid=id and sb_dt=20201020 and sb_gtid=4
    -- 更新变账记录(type=?)
    select * from dq_user_balance_mx

    insert into dq_user_balance_mx (add_time,uid,old_price,new_price,price,type,msg)
    select
    123456,sb_uid,balance-sb_backmoney,balance,sb_backmoney,5,concat(sb_dt,'游戏[',sb_gtid,']返水')
    from dq_third_stats_back,dq_user
    where sb_uid=id and sb_dt=20201020 and sb_gtid=4




    select gbc_viplvl as lvl,gbc_rate as rate from dq_game_back_config where gbc_gtid=8 order by gbc_viplvl



    -- 三方代理分佣

    -- 找到所有需要进入分佣统计的用户ID
    select ua.uid,ua.tree,ub.id,ub.rec_id,ub.tree
    -- select ub.id
    from dq_user_agent ua
    left join dq_user ub on (ua.uid=ub.id or ua.uid=ub.rec_id) and ub.status=1 and ub.lebal=1
    where ua.type=0 and ua.status=3 and ub.id is not null
    union all
    select uaa.uid,uaa.tree,ubb.id,ubb.rec_id,ubb.tree
    -- select ub.id
    from dq_user_agent uaa
    left join dq_user ubb on ubb.tree like concat(uaa.tree,'%') and ubb.status=1 and ubb.lebal=1
    where uaa.type=1 and uaa.status=3 and ubb.id is not null
    order by uaa.uid
    -- 找外部代理的自身和下级
    insert into dq_temp_fyfy_proxy_users
    select 0,ua.uid,1,ub.id
    from dq_user_agent ua
    left join dq_user ub on (ua.uid=ub.id or ua.uid=ub.rec_id) and ub.status=1 and ub.lebal=1
    where ua.type=0 and ua.status=3 and ub.id is not null
    -- 找内部代理树用户
    insert into dq_temp_fyfy_proxy_users
    select uaa.proxy_topuid,uaa.uid,0,ubb.id
    from dq_user_agent uaa
    left join dq_user ubb on ubb.tree like concat(uaa.tree,'%') and ubb.status=1 and ubb.lebal=1
    where uaa.type=1 and uaa.status=3 and ubb.id is not null
    order by uaa.uid
    -- 判断是否需要生成本日统计

    -- 合并用户ID到日志表
    -- select 20221020,tb.tfpu_uid
    from (
    select distinct tfpu_uid from dq_temp_fyfy_proxy_users order by tfpu_uid
    ) tb

    insert into dq_third_stats_fyfylog (sffl_dt,sffl_uid)
    select distinct 20221020,tfpu_uid from dq_temp_fyfy_proxy_users order by tfpu_uid

    -- show index from dq_temp_fyfy_proxy_users

    -- 统计返水
    update
    -- select * from
    (
    select sffl_dt,sffl_uid,sum(sb_backmoney) as backmoney from
    dq_third_stats_fyfylog
    left join dq_third_stats_back on sffl_dt=sb_dt and sffl_uid=sb_uid
    where sffl_dt=20221020 and sb_dt is not null group by sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    set tb.sffl_back=ta.backmoney
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid

    -- 统计优惠
    select * from dq_action_apply where ps_status=1 and ps_deta BETWEEN 1661194578 and 1666006283

    update
    (
    select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gd_amount+yhtb.tyj_amount) as money from
    dq_third_stats_fyfylog logtb
    left join dq_action_apply yhtb on logtb.sffl_uid=yhtb.uid and yhtb.ps_status=1 and yhtb.ps_deta BETWEEN 1661194578 and 1666006283
    where logtb.sffl_dt=20221020 and yhtb.id is not null group by logtb.sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    set tb.sffl_yh=ta.money
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid

    select * from dq_artificial_balance where a_type>2 and add_time BETWEEN 1661194578 and 1666256407

    update
    (
    select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.price) as money from
    dq_third_stats_fyfylog logtb
    left join dq_artificial_balance yhtb on logtb.sffl_uid=yhtb.uid and yhtb.a_type>2 and yhtb.add_time BETWEEN 1661194578 and 1666256407
    where logtb.sffl_dt=20221020 and yhtb.id is not null group by logtb.sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    set tb.sffl_yh=tb.sffl_yh+ta.money
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid
    -- 统计充值
    select * from dq_user_offline where status>=3 and update_time BETWEEN 1664445070 and 1666194314

    update
    -- select * from
    (
    select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.arrival_price) as money from
    dq_third_stats_fyfylog logtb
    left join dq_user_offline yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status>=3 and yhtb.update_time BETWEEN 1664445070 and 1666194314
    where logtb.sffl_dt=20221020 and yhtb.id is not null group by logtb.sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    -- set tb.sffl_payin=ta.money,tb.sffl_payinfee=ta.money*0.02
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid

    -- 统计提现
    select * from dq_user_withdrawal where status=3 and update_time BETWEEN 1666254732 and 1666255183

    update
    (
    select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.w_price) as money,sum(yhtb.w_service) as fee from
    dq_third_stats_fyfylog logtb
    left join dq_user_withdrawal yhtb on logtb.sffl_uid=yhtb.uid and yhtb.status=3 and yhtb.update_time BETWEEN 1666254732 and 1666255183
    where logtb.sffl_dt=20221020 and yhtb.id is not null group by logtb.sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    set tb.sffl_payout=ta.money,tb.sffl_payoutfee=ta.fee
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid

    -- 统计有效投注
    select * from dq_game_bets,dq_games
    where gb_gid=g_id and gb_bettime BETWEEN 1664264277 and 1665990461 and gb_winlose>0 and g_state=1 and g_fystatus=1

    update
    (
    select logtb.sffl_dt,logtb.sffl_uid,sum(yhtb.gb_validwater) as gb_validwater from
    dq_third_stats_fyfylog logtb
    left join dq_game_bets yhtb on logtb.sffl_uid=yhtb.gb_uid and yhtb.gb_winlose>0 and yhtb.gb_bettime BETWEEN 1664264277 and 1665990461
    left join dq_games gtb on gtb.g_id=yhtb.gb_gid and gtb.g_state=1 and gtb.g_fystatus=1
    where logtb.sffl_dt=20221020 and yhtb.gb_id is not null and gtb.g_id is not null group by logtb.sffl_uid
    ) ta,dq_third_stats_fyfylog tb
    set tb.sffl_bet=ta.gb_validwater
    where ta.sffl_dt=tb.sffl_dt and ta.sffl_uid=tb.sffl_uid

    -- 生成统计记录
    delete from dq_third_stats_fyfy where sff_m=202210

    -- 生成内部和内部非顶级统计
    insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
    select 202210 as sff_m,tfpu_proxysubuid as sff_uid,123456 as sff_statstime,if(tfpu_proxytype=1,1,3) as sff_utype,
    sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
    sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
    from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
    where tfpu_uid=sffl_uid and tfpu_proxyuid<>tfpu_proxysubuid and  sffl_dt between 20221000 and 20221031
    group by tfpu_proxysubuid

    -- 生成内部顶级统计
    insert into dq_third_stats_fyfy (sff_m,sff_uid,sff_statstime,sff_utype,sff_payin,sff_payinfee,sff_payout,sff_payoutfee,sff_yh,sff_back)
    select 202210 as sff_m,tfpu_proxyuid as sff_uid,123456 as sff_statstime,2 as sff_utype,
    sum(sffl_payin) as sff_payin,sum(sffl_payinfee) as sff_payinfee,sum(sffl_payout) as sff_payout,sum(sffl_payoutfee) as sff_payoutfee,
    sum(sffl_yh) as sff_yh,sum(sffl_back) as sff_back
    from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
    where tfpu_uid=sffl_uid and tfpu_proxyuid>0 and  sffl_dt between 20221000 and 20221031
    group by tfpu_proxyuid

    -- 统计有效客户数（外部代理）
    update
    -- select * from
    (
    select tfpu_proxysubuid,sum(if(bet=1 or payin=1,1,0)) as num
    from (
    select tfpu_proxysubuid,tfpu_uid,if(sum(sffl_bet)>40,1,0) as bet,if(sum(sffl_payin)>300,1,0) as payin
    from dq_temp_fyfy_proxy_users,dq_third_stats_fyfylog
    where tfpu_uid=sffl_uid and tfpu_proxytype=1 and (sffl_bet>0 or sffl_payin>0) and sffl_dt between 20221000 and 20221031
    group by tfpu_proxysubuid,tfpu_uid
    ) tb
    group by tfpu_proxysubuid
    HAVING num>0
    ) as tc,dq_third_stats_fyfy td
    set td.sff_validuser=tc.num
    where tc.tfpu_proxysubuid=td.sff_uid and td.sff_m=202210

    -- 统计技术服务费(技术服务费为负数的处理)

    select *,(sff_payin-sff_payout) as sss,(
    case
    when (sff_payin-sff_payout)<0 then 0
    when (sff_payin-sff_payout)<=100 then (sff_payin-sff_payout)*0.01
    when (sff_payin-sff_payout)<=400 then (sff_payin-sff_payout)*0.05
    when (sff_payin-sff_payout)<=10000 then (sff_payin-sff_payout)*0.1
    else
    100*0.01+300*0.05+9600*0.1+(sff_payin-sff_payout-10000)*0.2
    end
    ) as tec from
    dq_third_stats_fyfy
    where sff_m=202210

    update
    dq_third_stats_fyfy
    set sff_tec=(
    case
    when (sff_payin-sff_payout)>10000 then (sff_payin-sff_payout-10000)*0.2+9600*0.1+300*0.05+100*0.01
    when (sff_payin-sff_payout)>400 then (sff_payin-sff_payout-400)*0.05+300*0.05+100*0.01
    when (sff_payin-sff_payout)>100 then (sff_payin-sff_payout-100)*0.05+100*0.01
    when (sff_payin-sff_payout)>0 then (sff_payin-sff_payout-0)*0.01
    else
    0
    end
    )
    where sff_m=202210

    -- 统计上期负盈，本期负盈
    select * from
    dq_third_stats_fyfy ta
    left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_m=202209
    where ta.sff_m=202210 and tb.sff_m is not null

    update
    dq_third_stats_fyfy ta
    left join dq_third_stats_fyfy tb on ta.sff_uid=tb.sff_uid and tb.sff_m=202209
    set ta.sff_prelosetotal=tb.sff_losetotal
    where ta.sff_m=202210 and tb.sff_m is not null

    -- 统计分佣基数(负盈超过底线时的处理是全额还是去掉底线额，这里去掉了底线额？)
    select
    sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec as base,
    if(sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec>=-10,0,sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec+10) as lose
    ,dq_third_stats_fyfy.*
    from
    dq_third_stats_fyfy
    where sff_m=202210

    update
    dq_third_stats_fyfy
    set sff_base=sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec,
    sff_losetotal=if(sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec>=-10,0,sff_prelosetotal+sff_payin-sff_payinfee-sff_payout-sff_payoutfee-sff_yh-sff_back-sff_tec+10)
    where sff_m=202210

    -- 统计分佣金额（外部代理）
    select
    (
    case
    when(sff_validuser>=10 and sff_base>20000) then (sff_base-20000)*0.01+10000*0.005+5000*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=5 and sff_base>10000) then (sff_base-10000)*0.005+5000*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=2 and sff_base>5000) then (sff_base-5000)*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=1 and sff_base>1000) then (sff_base-1000)*0.001+1000*0.0005
    else
    sff_base*0.0005
    end
    ) as basereal
    ,dq_third_stats_fyfy.*
    from
    dq_third_stats_fyfy
    where sff_m=202210 and sff_utype=1 and sff_base>0

    update
    dq_third_stats_fyfy
    set sff_basereal=(
    case
    when(sff_validuser>=10 and sff_base>20000) then (sff_base-20000)*0.01+10000*0.005+5000*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=5 and sff_base>10000) then (sff_base-10000)*0.005+5000*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=2 and sff_base>5000) then (sff_base-5000)*0.02+4000*0.001+1000*0.0005
    when(sff_validuser>=1 and sff_base>1000) then (sff_base-1000)*0.001+1000*0.0005
    else
    sff_base*0.0005
    end
    )
    where sff_m=202210 and sff_utype=1 and sff_base>0

    -- 自动发放用户代理部分（月底）事务
    -- 发放状态
    update dq_third_stats_fyfy
    set sff_payed=2,sff_paytime=123456
    where sff_utype=1 and sff_m=202210 and sff_basereal>0
    -- 增加用户余额
    update dq_third_stats_fyfy,dq_user
    set balance=balance+sff_basereal
    where sff_uid=id and sff_utype=1 and sff_m=202210 and sff_basereal>0
    -- 更新变账记录
    insert into dq_user_balance_mx (add_time,uid,old_price,new_price,price,type,msg)
    select
    123456,sff_uid,balance-sff_basereal,balance,sff_basereal,6,concat(sff_m,'代理返佣发放')
    from dq_third_stats_fyfy,dq_user
    where sff_uid=id and sff_utype=1 and sff_m=202210 and sff_basereal>0















    -- 用户单日统计报表
    -- 投注使用投注时间，输赢使用结算时间
    TRUNCATE table dq_third_stats_users_temp

    -- 统计时间段内用户数据
    insert into dq_third_stats_users_temp (su_dt,rec_id3,rec_id2,rec_id,su_uid,username,tree,su_betnum,su_bet,su_losenum,su_lose,su_winnum,su_win,su_drawnum,su_draw)
    select dt,rec_id3,rec_id2,rec_id,id,username,tree,betnum,bet,losenum,lose,winnum,win,drawnum,draw from (
    select 20221023 as dt,rec_id3,rec_id2,rec_id,id,username,tree,count(*) as betnum,sum(gb_price) as bet
    from dq_game_bets,dq_user
    where gb_uid=id and gb_bettime BETWEEN 1664264277 and 1665990461 and gb_winlose>0
    group by gb_uid
    ) ta left join (
    select gb_uid,
    sum(if(gb_winprice>0,1,0)) as winnum,sum(gb_winprice) as win,
    sum(IF(gb_validwater<gb_price,1,0)) as drawnum,sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as draw
    from dq_game_bets
    where gb_bettime BETWEEN 1664264277 and 1665990461 and gb_winlose>0
    group by gb_uid
    ) tb on ta.id=tb.gb_uid

    -- 存在的记录更新  与写入不能互换顺序，更新必须在前
    select * from dq_third_stats_users_temp ta,dq_third_stats_users tb
    where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_dt=20221023

    update dq_third_stats_users_temp ta,dq_third_stats_users tb
    set
    tb.su_betnum=tb.su_betnum+ta.su_betnum,
    tb.su_bet=tb.su_bet+ta.su_bet,
    tb.su_losenum=tb.su_losenum+ta.su_losenum,
    tb.su_lose=tb.su_lose+ta.su_lose,
    tb.su_winnum=tb.su_winnum+ta.su_winnum,
    tb.su_win=tb.su_win+ta.su_win,
    tb.su_drawnum=tb.su_drawnum+ta.su_drawnum,
    tb.su_draw=tb.su_draw+ta.su_draw
    where ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid and ta.su_dt=20221023

    -- 不存在的记录写入
    insert into dq_third_stats_users
    select ta.* from dq_third_stats_users_temp ta
    left join dq_third_stats_users tb on ta.su_dt=tb.su_dt and ta.su_uid=tb.su_uid
    where ta.su_dt=20221023 and tb.su_dt is null

    */
}
