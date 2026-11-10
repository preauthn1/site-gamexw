<?php

namespace app\common\lib;

use app\common\FastGame\Base\JsTask;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Utils;
use think\facade\Db;

/**
 * 运营报表数据处理类
 */
class ActiveReport
{
    /**
     * 运行统计数据任务 每天凌晨执行一次，统计昨天的运营数据 执行时间放在所有其它统计类任务之后，切记
     */
    public static function runtask()
    {
        $runflag=Redis::get('ActiveDataRunflag');
        if ($runflag) return; //防止多次执行
        Redis::setex('ActiveDataRunflag',time(),1800);
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        try {
            //统计日运营数据
            $next = self::countDayActiveData();
            if ($next) {
                //统计周运营数据
                self::countWeekActiveData();
                //统计月运营数据
                self::countMonthActiveData();
                //删除过期历史数据（为性能计）
                self::deleteOldTempData();
                //更新范围缓存
                self::getActiveDataRange(true);
            }
            //统计分布类报表
            self::countDistributionReports();
        }catch (\Exception $ex){
            Utils::L('ActiveReport',['统计数据任务出错',$ex->getMessage(),$ex->getTraceAsString()]);
        }
        Redis::del('ActiveDataRunflag');
    }

    /**
     * 统计日活跃数据
     */
    private static function countDayActiveData(){
        $lastdt=false;
        //获取最后统计日期
        $sql="select suv_dt from dq_stat_user_base where suv_dt>999999 and suv_dw=0 order by suv_dt desc limit 1";
        $lastdt=Db::query($sql);
        Utils::L('ActiveReport',['统计表中查询最后统计日期',$sql,$lastdt]);
        if (count($lastdt)>0){
            $lastdt=$lastdt[0]['suv_dt'];
        }else{
            $sql="select add_time from dq_user order by id asc limit 1";
            $rlt=Db::query($sql);
            Utils::L('ActiveReport',['用户表中查询最后统计日期',$sql,$rlt]);
            if (count($rlt)>0){
                $lastdt=date('Ymd',$rlt[0]['add_time']-86400);
            }
        }
        if (!$lastdt) return false;//没有数据，退出
        $newdtstr=date('Y-m-d', strtotime(substr($lastdt,0,4)."-".substr($lastdt,4,2)."-".substr($lastdt,6,2))+86400);
        if ($newdtstr>=date('Y-m-d')) return false;//当天不统计
        $ds=strtotime($newdtstr);
        $de=$ds+86400-1;
        $newdt=date('Ymd',$ds);
        $newdm=date("Ym",$ds);
        Utils::L('ActiveReport',['解析变量',$lastdt,$newdtstr,$ds,$de,$newdt]);
        //删除临时表数据
        $sql="TRUNCATE table dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['删除临时表数据',$sql,$rlt]);
        //建立一天的基础数据
        $sql="insert into dq_stat_user_midtemp_temp (sut_uid,sut_reg,sut_login,sut_active,sut_loss,sut_balance)
select id as uid,
if(add_time BETWEEN {$ds} and {$de},1,0) as reg,
if(login_time BETWEEN {$ds} and {$de},1,0) as login,
if(add_time BETWEEN {$ds} and {$de} or login_time BETWEEN {$ds} and {$de},1,0) as active,
if(add_time BETWEEN {$ds} and {$de} or login_time BETWEEN {$ds} and {$de},0,1) as lose,
balance
 from dq_user where add_time<={$de}";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['建立一天的基础数据',$sql,$rlt]);
        //统计充值
        $sql="update (
select uid,sum(arrival_price) as price,count(*) as num from dq_user_offline where status>=3 and update_time between {$ds} and {$de} group by uid
) ta,dq_stat_user_midtemp_temp tb
set tb.sut_payin=ta.num,
tb.sut_payinprice=ta.price,
tb.sut_active=1,
tb.sut_loss=0
where ta.uid=tb.sut_uid";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计充值',$sql,$rlt]);
        //统计提现
        $sql="update (
select uid,sum(arrival_prices) as price,count(*) as num from dq_user_withdrawal where status>=3 and update_time between {$ds} and {$de} group by uid
) ta,dq_stat_user_midtemp_temp tb
set tb.sut_withraw=ta.num,
tb.sut_withrawprice=ta.price,
tb.sut_active=1,
tb.sut_loss=0
where ta.uid=tb.sut_uid";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计提现',$sql,$rlt]);
        /*//统计游戏
        //分表是否存在
        $sql="select count(*) as num from information_schema.tables where table_name='dq_game_bets_{$newdm}' and table_schema='".env('DATABASE.DATABASE')."'";
        $exed=Db::query($sql)[0]['num'];
        $exed=$exed?"_{$newdm}":"";
        Utils::L('ActiveReport',['分表是否存在',$exed]);
        //获取GBID
        list("max"=>$max_id,"min"=>$min_id)=JsTask::getBetsIdRange($ds,$de,'gb_bettime',$exed);
        $sql="update (
select gb_uid as uid,count(*) as num,sum(gb_price) as price,sum(gb_otherwin) as winlose,sum(if(gb_otherwin>0,1,0)) as win,sum(if(gb_otherwin<0,1,0)) as lose from dq_game_bets{$exed} where gb_bettime between {$ds} and {$de} and gb_id BETWEEN {$min_id} and {$max_id} and gb_winlose>0 group by gb_uid
) ta,dq_stat_user_midtemp_temp tb
set tb.sut_game=ta.num,
tb.sut_playprice=ta.price,
tb.sut_winlose=ta.winlose,
    tb.sut_playwin=ta.win,
    tb.sut_playlose=ta.lose,
tb.sut_active=1,
tb.sut_loss=0
where ta.uid=tb.sut_uid";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计游戏',$sql,$rlt]);*/

        //统计游戏-- 改为从用户统计表统计，性能会好一些
        //分表是否存在
        $sql="select count(*) as num from information_schema.tables where table_name='dq_third_stats_users_{$newdm}' and table_schema='".env('DATABASE.DATABASE')."'";
        $exed=Db::query($sql)[0]['num'];
        $exed=$exed?"_{$newdm}":"";
        Utils::L('ActiveReport',['分表是否存在',$exed]);
        $sql="update dq_third_stats_users{$exed} ta,dq_stat_user_midtemp_temp tb
set tb.sut_game=ta.su_betnum,
tb.sut_playprice=ta.su_bet,
tb.sut_winlose=ta.su_otherwin,
    tb.sut_playwin=ta.su_winnum,
    tb.sut_playlose=ta.su_betnum-ta.su_winnum,
tb.sut_active=if(ta.su_betnum>0,1,0),
tb.sut_loss=if(ta.su_betnum>0,0,tb.sut_loss)
where ta.su_uid=tb.sut_uid and ta.su_dt={$newdt}";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计游戏',$sql,$rlt]);


        /*-- 与前一天数据对比留存，激活或流失
-- 新增用户保持状态
-- 老用户当前活跃则加上期活跃
-- 当前流失则加上期流失*/
        $sql="update dq_stat_user_midtemp_temp ta
left join dq_stat_user_midtemp tb on tb.sut_uid=ta.sut_uid and tb.sut_dt={$lastdt} and tb.sut_dw=0
set ta.sut_active=if(ta.sut_active>0,ta.sut_active+ifnull(tb.sut_active,0),ta.sut_active),
ta.sut_loss=if(ta.sut_loss>0,ta.sut_loss+ifnull(tb.sut_loss,0),ta.sut_loss),
ta.sut_rekeep=if(ifnull(tb.sut_loss,0)>0 and ta.sut_active=1,1,0),
    ta.sut_gamesum=ta.sut_game+ifnull(tb.sut_gamesum,0),
    ta.sut_payinsum=ta.sut_payin+ifnull(tb.sut_payinsum,0),
    ta.sut_withrawsum=ta.sut_withraw+ifnull(tb.sut_withrawsum,0),
    ta.sut_withrawpricesum=ta.sut_withrawprice+ifnull(tb.sut_withrawpricesum,0),
    ta.sut_payinpricesum=ta.sut_payinprice+ifnull(tb.sut_payinpricesum,0),
    ta.sut_winlosesum=ta.sut_winlose+ifnull(tb.sut_winlosesum,0),
    ta.sut_playpricesum=ta.sut_playprice+ifnull(tb.sut_playpricesum,0),
    ta.sut_playwinsum=ta.sut_playwin+ifnull(tb.sut_playwinsum,0),
    ta.sut_playlosesum=ta.sut_playlose+ifnull(tb.sut_playlosesum,0)";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['与前一天数据对比留存，激活或流失',$sql,$rlt]);
        //写入数据到中间表
        $sql="insert into dq_stat_user_midtemp
select {$newdt} as dt,0 as dw,dq_stat_user_midtemp_temp.* from dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['写入数据到中间表',$sql,$rlt]);
        //统计活跃报表
        $sql="insert into dq_stat_user_base
select 
{$newdt} as suv_dt,0 as suv_dw,
count(*) as suv_count,
sum(if(sut_reg=1,1,0)) as suv_reg,
sum(if(sut_active>0,1,0)) as suv_activity,
sum(if(sut_game>0,1,0)) as suv_game,
sum(sut_game) as suv_gamenum,
sum(if(sut_winlose>0,1,0)) as suv_win,
sum(sut_playwin) as suv_winnum,
sum(if(sut_winlose>0,sut_winlose,0)) as suv_winprice,
sum(if(sut_winlose<0,1,0)) as suv_lose,
sum(sut_playlose) as suv_losenum,
sum(if(sut_winlose<0,abs(sut_winlose),0)) as suv_loseprice,
sum(sut_winlose)*-1 as suv_pwinprice,
sum(if(sut_payin>0,1,0)) as suv_payin,
sum(sut_payin) as suv_payinnum,
sum(sut_payinprice) as suv_payinprice,
sum(if(sut_withraw>0,1,0)) as suv_withraw,
sum(sut_withraw) as suv_withrawnum,
sum(sut_withrawprice) as suv_withrawprice,
sum(sut_playprice) as suv_playprice,
sum(sut_balance) as suv_balance,
sum(sut_payinprice-sut_withrawprice) as suv_ppwc,
sum(if(sut_active>1,1,0)) as suv_keep,
sum(if(sut_rekeep>0,1,0)) as suv_rekeep,
sum(if(sut_loss>0,1,0)) as suv_out,
1 as suv_end
from dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计活跃报表',$sql,$rlt]);
        //统计代理线活跃报表
        $sql="insert into dq_stat_user_base_proxys
select 
{$newdt} as suv_dt,0 as suv_dw,
tfpu_proxysubuid as suv_proxyid,
count(*) as suv_count,
sum(if(sut_reg=1,1,0)) as suv_reg,
sum(if(sut_active>0,1,0)) as suv_activity,
sum(if(sut_game>0,1,0)) as suv_game,
ifnull(sum(sut_game),0) as suv_gamenum,
sum(if(sut_winlose>0,1,0)) as suv_win,
ifnull(sum(sut_playwin),0) as suv_winnum,
sum(if(sut_winlose>0,sut_winlose,0)) as suv_winprice,
sum(if(sut_winlose<0,1,0)) as suv_lose,
ifnull(sum(sut_playlose),0) as suv_losenum,
sum(if(sut_winlose<0,abs(sut_winlose),0)) as suv_loseprice,
ifnull(sum(sut_winlose),0)*-1 as suv_pwinprice,
sum(if(sut_payin>0,1,0)) as suv_payin,
ifnull(sum(sut_payin),0) as suv_payinnum,
ifnull(sum(sut_payinprice),0) as suv_payinprice,
sum(if(sut_withraw>0,1,0)) as suv_withraw,
ifnull(sum(sut_withraw),0) as suv_withrawnum,
ifnull(sum(sut_withrawprice),0) as suv_withrawprice,
ifnull(sum(sut_playprice),0) as suv_playprice,
ifnull(sum(sut_balance),0) as suv_balance,
ifnull(sum(sut_payinprice-sut_withrawprice),0) as suv_ppwc,
sum(if(sut_active>1,1,0)) as suv_keep,
sum(if(sut_rekeep>0,1,0)) as suv_rekeep,
sum(if(sut_loss>0,1,0)) as suv_out,
1 as suv_end 
from dq_temp_fyfy_proxy_users
left join dq_stat_user_midtemp_temp on tfpu_uid=sut_uid
group by tfpu_proxysubuid";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计代理线活跃报表',$sql,$rlt]);

        return $newdt;
    }

    /**
     * 统计周活跃数据
     */
    private static function countWeekActiveData(){
        //查询中间表最新日统计数据
        $sql="select * from dq_stat_user_midtemp where sut_dw=0 order by sut_dt desc limit 1";
        $midlast=Db::query($sql);
        if (count($midlast)>0){
            $lastdt=$midlast[0]['sut_dt'];
            $lastdtst= strtotime(substr($lastdt,0,4)."-".substr($lastdt,4,2)."-".substr($lastdt,6,2));
            $week=Utils::nextweek($lastdtst,-1);
            //统计上周活跃数据
            self::__countWeekMonthActiveData(0,$week['nextyw'],date("Ymd",$week['nextys']),date("Ymd",$week['nextye']));
            //统计本周活跃数据
            self::__countWeekMonthActiveData(0,$week['yw'],date("Ymd",$week['ys']),date("Ymd",$week['ye']));
        }
    }

    /**
     * 统计月活跃数据
     */
    private static function countMonthActiveData()
    {
        //查询中间表最新日统计数据
        $sql="select * from dq_stat_user_midtemp where sut_dw=0 order by sut_dt desc limit 1";
        $midlast=Db::query($sql);
        if (count($midlast)>0){
            $lastdt=$midlast[0]['sut_dt'];
            $lastdtst= strtotime(substr($lastdt,0,4)."-".substr($lastdt,4,2)."-".substr($lastdt,6,2));
            $ym=date("Ym",$lastdtst);
            $dsstr=date("Y-m-01", $lastdtst);
            $ds=date("Ym01", $lastdtst);
            $de=date("Ymd",strtotime(date("Y-m-01", strtotime("$dsstr +1 month")))-1);
            $yesst=strtotime(date("Y-m-01", $lastdtst))-1;
            $yesym=date("Ym",$yesst);
            $yesde=date("Ymd",$yesst);
            $yesds=date("Ym01",$yesst);
            //统计上月活跃数据
            self::__countWeekMonthActiveData($yesym,0,$yesds,$yesde);
            //统计本月活跃数据
            self::__countWeekMonthActiveData($ym,0,$ds,$de);
        }
    }

    /**
     * 统计周月活跃数据的通用方法
     * 其中 sut_loss sut_active,sut_rekeep 字段的数值大小没有参考 意义
     */
    private static function __countWeekMonthActiveData($dtflag,$dwflag,$startdt,$lastdt){
        Utils::L('ActiveReport',['准备统计数据',$dtflag,$dwflag,$startdt,$lastdt]);
        //周期内数据是否统计完毕
        $sql="select * from dq_stat_user_base where suv_dt={$dtflag} and suv_dw={$dwflag}";
        $rlt=Db::query($sql);
        Utils::L('ActiveReport',['查询统计数据',$sql,$rlt]);
        if (count($rlt)>0){
            if ($rlt[0]['suv_end']==1) {
                Utils::L('ActiveReport',['数据已统计，不需要重复统计']);
                return;
            }
        }
        //删除未统计完的数据
        $sql="delete from dq_stat_user_base where suv_dt={$dtflag} and suv_dw={$dwflag} and suv_end=0";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['删除未结束的统计数据',$sql,$rlt]);
        //查询中间表现存数据
        if ($dtflag==0){
            $where="sut_dt<={$lastdt} and sut_dw={$dwflag} order by sut_dt desc";
        }else{
            $where="sut_dt={$dtflag} and sut_dw<={$lastdt} order by sut_dw desc";
        }
        $sql="select * from dq_stat_user_midtemp where {$where} limit 1";
        $mid=Db::query($sql);
        if (count($mid)>0){
            if ($dtflag==0){
                $oldlastdt=$mid[0]['sut_dt'];//用于定位中间表中的数据
            }else{
                $oldlastdt=$mid[0]['sut_dw'];
            }
            $newstartdt=date('Ymd', strtotime(substr($oldlastdt,0,4)."-".substr($oldlastdt,4,2)."-".substr($oldlastdt,6,2))+86400);//用于定位累加数据的开始日期
        }else{
            $oldlastdt=-1;
            $newstartdt=$startdt;
        }
        //查询中间表最新日统计数据
        $sql="select * from dq_stat_user_midtemp where sut_dw=0 order by sut_dt desc limit 1";
        $midlast=Db::query($sql);
        if (count($midlast)>0){
            $newlastdt=$midlast[0]['sut_dt'];
            if ($newlastdt<$newstartdt) return;//中间表没有新数据
            if ($newlastdt>$lastdt) $newlastdt=$lastdt;
        }else{
            return;//中音表无统计数据
        }
        Utils::L('ActiveReport',['找到最新统计数据',$sql,$midlast,$newlastdt,$lastdt]);
        //删除临时表数据
        $sql="TRUNCATE table dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['删除临时表数据',$sql,$rlt]);
        //新数据写入临时表
        $sql="insert into dq_stat_user_midtemp_temp (sut_uid,sut_reg,sut_game,sut_winlose,sut_payin,sut_payinprice,sut_withraw,sut_withrawprice,sut_playprice,sut_balance,sut_active,sut_rekeep,sut_loss,sut_playwin,sut_playlose,sut_gamesum,sut_payinsum,sut_withrawsum,sut_withrawpricesum,sut_payinpricesum,sut_winlosesum,sut_playpricesum,	sut_playwinsum,sut_playlosesum)
		select 
		sut_uid,
		sum(sut_reg) as sut_reg,
		sum(sut_game) as sut_game,
		sum(sut_winlose) as sut_winlose,
		sum(sut_payin) as sut_payin,
		sum(sut_payinprice) as sut_payinprice,
		sum(sut_withraw) as sut_withraw,
		sum(sut_withrawprice) as sut_withrawprice,
		sum(sut_playprice) as sut_playprice,
		sum(if(sut_dt={$newlastdt},sut_balance,0)) as sut_balance,
		max(sut_active) as sut_active,
		max(sut_rekeep) as sut_rekeep,
		max(sut_loss) as sut_loss,
		sum(sut_playwin) as sut_playwin,
		sum(sut_playlose) as sut_playlose,
		sum(if(sut_dt={$newlastdt},sut_gamesum,0)) as sut_gamesum,
		sum(if(sut_dt={$newlastdt},sut_payinsum,0)) as sut_payinsum,
		sum(if(sut_dt={$newlastdt},sut_withrawsum,0)) as sut_withrawsum,
		sum(if(sut_dt={$newlastdt},sut_withrawpricesum,0)) as sut_withrawpricesum,
		sum(if(sut_dt={$newlastdt},sut_payinpricesum,0)) as sut_payinpricesum,
		sum(if(sut_dt={$newlastdt},sut_winlosesum,0)) as sut_winlosesum,
		sum(if(sut_dt={$newlastdt},sut_playpricesum,0)) as sut_playpricesum,
		sum(if(sut_dt={$newlastdt},sut_playwinsum,0)) as sut_playwinsum,
		sum(if(sut_dt={$newlastdt},sut_playlosesum,0)) as sut_playlosesum
		from dq_stat_user_midtemp
		where sut_dt between {$newstartdt} and {$newlastdt} and sut_dw=0
		group by sut_uid";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['新数据写入临时表',$sql,$rlt]);
        if ($rlt==0){
            Utils::L('ActiveReport',['没有需要统计的数据']);
            return;
        }
        // 与此前数据对比留存，激活或流失
        if ($dtflag==0){
            $where="tb.sut_dt={$oldlastdt} and tb.sut_dw={$dwflag}";
        }else{
            $where="tb.sut_dt={$dtflag} and tb.sut_dw={$oldlastdt}";
        }
        $sql="update dq_stat_user_midtemp_temp ta
left join dq_stat_user_midtemp tb on tb.sut_uid=ta.sut_uid and {$where}
set 
    ta.sut_reg=ta.sut_reg+ifnull(tb.sut_reg,0),
    ta.sut_game=ta.sut_game+ifnull(tb.sut_game,0),
    ta.sut_payin=ta.sut_payin+ifnull(tb.sut_payin,0),
    ta.sut_withraw=ta.sut_withraw+ifnull(tb.sut_withraw,0),
    ta.sut_withrawprice=ta.sut_withrawprice+ifnull(tb.sut_withrawprice,0),
    ta.sut_payinprice=ta.sut_payinprice+ifnull(tb.sut_payinprice,0),
    ta.sut_winlose=ta.sut_winlose+ifnull(tb.sut_winlose,0),
    ta.sut_playwin=ta.sut_playwin+ifnull(tb.sut_playwin,0),
    ta.sut_playlose=ta.sut_playlose+ifnull(tb.sut_playlose,0),
    ta.sut_playprice=ta.sut_playprice+ifnull(tb.sut_playprice,0),
ta.sut_active=if(ta.sut_active>0,ta.sut_active+ifnull(tb.sut_active,0),ta.sut_active),
ta.sut_loss=if(ta.sut_loss>0,ta.sut_loss+ifnull(tb.sut_loss,0),ta.sut_loss),
ta.sut_rekeep=if(ta.sut_rekeep>0,ta.sut_rekeep+ifnull(tb.sut_rekeep,0),ta.sut_rekeep)";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['与此前数据对比留存，激活或流失',$sql,$rlt]);
        //写入新数据到中间表
        if ($dtflag==0){
            $where="{$newlastdt} as dt,{$dwflag} as dw";
        }else{
            $where="{$dtflag} as dt,{$newlastdt} as dw";
        }
        $sql="insert into dq_stat_user_midtemp
select {$where},dq_stat_user_midtemp_temp.* from dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['写入新数据到中间表',$sql,$rlt]);
        //删除中间表老数据
        if ($dtflag==0){
            $where="sut_dt between 1 and {$oldlastdt} and sut_dw={$dwflag}";
        }else{
            $where="sut_dt={$dtflag} and sut_dw between 1 and {$oldlastdt}";
        }
        $sql="delete from dq_stat_user_midtemp where {$where}";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['删除中间表老数据',$sql,$rlt]);
        //统计活跃报表
        $where=$newlastdt==$lastdt?1:0;
        $sql="insert into dq_stat_user_base
	select 
	{$dtflag} as suv_dt,{$dwflag} as suv_dw,
	count(*) as suv_count,
	sum(if(sut_reg>0,1,0)) as suv_reg,
	sum(if(sut_active>0,1,0)) as suv_activity,
	sum(if(sut_game>0,1,0)) as suv_game,
	sum(sut_game) as suv_gamenum,
	sum(if(sut_winlose>0,1,0)) as suv_win,
	sum(sut_playwin) as suv_winnum,
	sum(if(sut_winlose>0,sut_winlose,0)) as suv_winprice,
	sum(if(sut_winlose<0,1,0)) as suv_lose,
	sum(sut_playlose) as suv_losenum,
	sum(if(sut_winlose<0,abs(sut_winlose),0)) as suv_loseprice,
	sum(sut_winlose)*-1 as suv_pwinprice,
	sum(if(sut_payin>0,1,0)) as suv_payin,
	sum(sut_payin) as suv_payinnum,
	sum(sut_payinprice) as suv_payinprice,
	sum(if(sut_withraw>0,1,0)) as suv_withraw,
	sum(sut_withraw) as suv_withrawnum,
	sum(sut_withrawprice) as suv_withrawprice,
	sum(sut_playprice) as suv_playprice,
	sum(sut_balance) as suv_balance,
	sum(sut_payinprice-sut_withrawprice) as suv_ppwc,
	sum(if(sut_active>1,1,0)) as suv_keep,
	sum(if(sut_rekeep>0,1,0)) as suv_rekeep,
	sum(if(sut_loss>0 and sut_active=0,1,0)) as suv_out,
	{$where} as suv_end from  
		dq_stat_user_midtemp_temp";
        $rlt=Db::execute($sql);
        Utils::L('ActiveReport',['统计活跃报表',$sql,$rlt]);
    }

    /**
     * 删除三个月以前的中间数据
     * @return void
     */
    private static function deleteOldTempData(){
        //查询中间表最新日统计数据
        $sql="select * from dq_stat_user_midtemp where sut_dw=0 order by sut_dt desc limit 1";
        $midlast=Db::query($sql);
        if (count($midlast)>0){
            $lastdt=$midlast[0]['sut_dt'];
            $lastdtst= strtotime(substr($lastdt,0,4)."-".substr($lastdt,4,2)."-".substr($lastdt,6,2))-3*31*86400;
            $lastdt=date("Ymd",$lastdtst);
            $lastweek=date("YW",$lastdtst);
            $lastmonth=date("Ym",$lastdtst);
            $sql="delete from dq_stat_user_midtemp where sut_dt between 999999 and {$lastdt} and sut_dw=0 or sut_dt between 1 and {$lastmonth} or sut_dw between 1 and {$lastweek}";
            $rlt=Db::execute($sql);
            Utils::L('ActiveReport',['删除旧的临时表数据',$sql,$rlt]);
            //优化表--每30次统计执行一次优化
            $cnt=Redis::get('ActiveDataDeleteCache');
            if ($cnt) {
                $cnt++;
            }else{
                $cnt=1;
            }
            if ($cnt>30) {
                $cnt=0;
                $sql = "OPTIMIZE TABLE `dq_stat_user_midtemp`";
                $rlt = Db::execute($sql);
                Utils::L('ActiveReport', ['优化表', $sql, $rlt]);
            }
            $cnt=Redis::set('ActiveDataDeleteCache',$cnt);
        }
    }

    /** 获取活跃报表时间范围
     * @return array[d[s,e],w[list],m[list]]
     */
    public static function getActiveDataRange($rebuild=false){
        $arr=Redis::get('ActiveDataRangeCache');
        if ($arr && !$rebuild){
            return json_decode($arr,true);
        }
        $arr=[];
        $sql="select min(suv_dt) as mindt,max(suv_dt) as maxdt from dq_stat_user_base where suv_dt>999999";
        $rlt=Db::query($sql);
        if (count($rlt)>0) {
            $arr['d']=['s'=>$rlt[0]['mindt'],'e'=>$rlt[0]['maxdt']];
            $arr['d']['s']=substr($arr['d']['s'],0,4).'-'.substr($arr['d']['s'],4,2).'-'.substr($arr['d']['s'],6,2);
            $arr['d']['e']=substr($arr['d']['e'],0,4).'-'.substr($arr['d']['e'],4,2).'-'.substr($arr['d']['e'],6,2);
        }else{
            $arr['d']=['s'=>null,'e'=>null];
        }
        $sql="select suv_dt from dq_stat_user_base where suv_dt between 1 and 999999 order by suv_dt";
        $rlt=Db::query($sql);
        if (count($rlt)>0) {
            $arr['m']=array_column($rlt,'suv_dt');
        }else{
            $arr['m']=[];
        }
        $sql="select suv_dw from dq_stat_user_base where suv_dw between 1 and 999999 order by suv_dw";
        $rlt=Db::query($sql);
        if (count($rlt)>0) {
            $arr['w']=array_column($rlt,'suv_dw');
        }else{
            $arr['w']=[];
        }
        Redis::set('ActiveDataRangeCache',json_encode($arr));
        return $arr;
    }



    /** 获取活跃度数据
     * @param $type d 日数据  w周数据 m月数据
     * @param $ds yyyymmdd yw ym
     * @param $de yyyymmdd yw ym
     * @param $fields 返回字段 ,分隔 不用包含suv_dt suv_dw
     * @param string|array $sumFields 统计字段，返回字段中需要循环累积的数据字段，返回字段名加后缀sum
     * @param int $proxyid 代理用户ID，用于查询代理线报表
     * @return array[dt,fieldds]
     */
    public static function getActiveDatas($type,$ds,$de,$fields='*',$sumFields=[],$proxyid=null){
        $ex='';
        $andwhere='';
        if ($proxyid){
            $ex='_proxys';
            $andwhere=" and suv_proxyid={$proxyid}";
            $type='d';//代理线报表只支持日报
        }
        if (is_string($sumFields)) $sumFields=explode(',',$sumFields);
        switch ($type){
            case "w":
                $fields="suv_dw as dt,".($fields=='*'?"dq_stat_user_base{$ex}.":"")."{$fields}";
                $where="suv_dw between {$ds} and {$de} {$andwhere} order by suv_dw";
                break;
            case "m":
                $fields="suv_dt as dt,".($fields=='*'?"dq_stat_user_base{$ex}.":"")."{$fields}";
                $where="suv_dt between {$ds} and {$de} {$andwhere} order by suv_dt";
                break;
            default:
                $fields="suv_dt as dt,".($fields=='*'?"dq_stat_user_base{$ex}.":"")."{$fields}";
                $where="suv_dt between {$ds} and {$de} {$andwhere} order by suv_dt";
                break;
        }
        $sql="select {$fields} from dq_stat_user_base{$ex} where {$where}";
        $rlt=Db::query($sql);
        if (count($rlt)>0) {
            $arr=[];
            foreach ($rlt as $k=>$v){//处理统计字段
                unset($v['suv_dt'],$v['suv_dw']);
                foreach ($sumFields as $f){
                    if ($k>0){
                        $v["{$f}sum"]=$v["{$f}"]+$arr[$k-1]["{$f}sum"];
                    }else{
                        $v["{$f}sum"]=$v["{$f}"];
                    }
                }
                array_push($arr,$v);
            }
        }else{
            $arr=[];
        }
        return $arr;
    }


    /** 获取分布类报表列表（countDistributionReports方法中每增加一个都要在这里新增一个数组项）
     * @return array([title=>'',unit=>'',info=>'',parts=>[0~9]，subtype=>[[title=>'',unit=>'',info=>'']],]])
     */
    public static function getDistributionReportsClass($type=null,$subtype=null){
        $arr=[
            [
                'title'=>'用户生命周期人数(不含未充值)',
                'unit'=>'人数',
                'info'=>'用户生命周期天数分段，统计包含未充值的人',
                'parts'=>['1天','3天','1周','2周','3周','1月','2月','3月','4月','4月以上'],
                'subtype'=>[
                    [
                        'title'=>'平均贡献',
                        'unit'=>'金额',
                        'info'=>'(充值金额-提现金额)/统计人数'
                    ],
                    [
                        'title'=>'平均充值次数',
                        'unit'=>'次数',
                        'info'=>'充值次数/统计人数'
                    ],
                    [
                        'title'=>'平均充值金额',
                        'unit'=>'金额',
                        'info'=>'充值总额/统计人数'
                    ],
                    [
                        'title'=>'平均提现次数',
                        'unit'=>'次数',
                        'info'=>'提现次数/统计人数'
                    ]
                ]
            ],
            [
                'title'=>'用户生命周期人数(含未充值)',
                'unit'=>'人数',
                'info'=>'用户生命周期天数分段，统计不含未充值的人',
                'parts'=>['1天','3天','1周','2周','3周','1月','2月','3月','4月','4月以上'],
                'subtype'=>[
                    [
                        'title'=>'平均贡献',
                        'unit'=>'金额',
                        'info'=>'(充值金额-提现金额)/统计人数',
                    ],
                    [
                        'title'=>'平均充值次数',
                        'unit'=>'次数',
                        'info'=>'充值次数/统计人数'
                    ],
                    [
                        'title'=>'平均充值金额',
                        'unit'=>'金额',
                        'info'=>'充值总额/统计人数'
                    ],
                    [
                        'title'=>'平均提现次数',
                        'unit'=>'次数',
                        'info'=>'提现次数/统计人数'
                    ]
                ]
            ],
            [
                'title'=>'用户首充时长分段',
                'unit'=>'人数',
                'info'=>'用户首充时长按天数分段统计',
                'parts'=>['未充值','当天','第二天','第三天','第四天','第五天','第六天','第七天','七天至两周','两周以上'],
                'subtype'=>[
                    [
                        'title'=>'平均充值金额',
                        'unit'=>'金额',
                        'info'=>'首充金额汇总/分段统计人数'
                    ],
                    [
                        'title'=>'人数占比',
                        'unit'=>'占比%',
                        'info'=>'分段人数/总统计人数'
                    ]
                ]
            ]
        ];
        if ($type==null) {
            return $arr;
        }elseif($subtype==null){
            foreach ($arr as $k=>$v){
                if ($v['title']==$type){
                    return $v;
                }
            }
            return $arr;
        }else{
            foreach ($arr as $k=>$v){
                if ($v['title']==$type){
                    foreach ($v['subtype'] as $k2=>$v2){
                        if ($v2['title']==$subtype){
                            $v['subtype']=$v2;
                            return $v;
                        }
                    }
                }
            }
            return $arr;
        }
    }

    /** 获取分布类报表数据
     * @param string|array $type 指标对(格式 主指标:副指标)，数组形式返回多个指标数据
     * @param int $ds 开始日期
     * @param int|null $de 结束日期，为空则表示只返回开始日期的数据
     * @return array
     */
    public static function getDistributionReportDatas($type,$ds,$de=null){
        $arr=[];
        if (is_string($type)) $type=[$type];
        if (strpos($ds,'-')!==false) $ds=str_replace('-','',$ds);
        $where="";
        foreach ($type as $k=>$v){
            $type[$k]=explode(':',$type[$k]);
            $where.=(empty($where)?'':' or ')."(sd_type='{$type[$k][0]}' and sd_subtype='{$type[$k][1]}')";
        }
        if (empty($where)) return $arr;
        if ($de==null){
            $where.=" and sd_dt={$ds}";
        }else{
            $where.=" and sd_dt between {$ds} and {$de}";
        }
        $sql="select * from dq_stat_distribution where {$where} order by sd_dt";
        $rlt=Db::query($sql);
        foreach ($rlt as $d) {
            if (!isset($arr[$d['sd_type']])) $arr[$d['sd_type']]=[
                'info'=>self::getDistributionReportsClass($d['sd_type']),
                'data'=>[]
            ];
            if (!isset($arr[$d['sd_type']]['data'][$d['sd_subtype']])) {
                $arr[$d['sd_type']]['data'][$d['sd_subtype']]=[
                    'info'=>self::getDistributionReportsClass($d['sd_type'],$d['sd_subtype']),
                    'data'=>[]
                ];
            }
            array_push($arr[$d['sd_type']]['data'][$d['sd_subtype']]['data'],$d);
        }
        return $arr;
    }

    /** 获取分布类报表的多行转单行SQL
     * @param $dt 日期
     * @param $type 主指标名
     * @param $subtype 副指标名
     * @param $sql 生成数据的SQL，只能返回 part（1-10的分段标识） typeval（主指标值） subtypeval（副指标值） 三个字段
     * @return string
     */
    private static function __getDistributionReportsSql($dt,$type,$subtype,$sql){
        return "insert into dq_stat_distribution
select 
{$dt} as sd_dt,
'{$type}' as sd_type,
'{$subtype}' as sd_subtype,
ifnull(sum(sd_interval_a),0) as sd_interval_a,ifnull(sum(sd_interval_av),0) as sd_interval_av,
ifnull(sum(sd_interval_b),0) as sd_interval_b,ifnull(sum(sd_interval_bv),0) as sd_interval_bv,
ifnull(sum(sd_interval_c),0) as sd_interval_c,ifnull(sum(sd_interval_cv),0) as sd_interval_cv,
ifnull(sum(sd_interval_d),0) as sd_interval_d,ifnull(sum(sd_interval_dv),0) as sd_interval_dv,
ifnull(sum(sd_interval_e),0) as sd_interval_e,ifnull(sum(sd_interval_ev),0) as sd_interval_ev,
ifnull(sum(sd_interval_f),0) as sd_interval_f,ifnull(sum(sd_interval_fv),0) as sd_interval_fv,
ifnull(sum(sd_interval_g),0) as sd_interval_g,ifnull(sum(sd_interval_gv),0) as sd_interval_gv,
ifnull(sum(sd_interval_h),0) as sd_interval_h,ifnull(sum(sd_interval_hv),0) as sd_interval_hv,
ifnull(sum(sd_interval_i),0) as sd_interval_i,ifnull(sum(sd_interval_iv),0) as sd_interval_iv,
ifnull(sum(sd_interval_j),0) as sd_interval_j,ifnull(sum(sd_interval_jv),0) as sd_interval_jv
from
(
select 
if(part=1,typeval,0) as sd_interval_a,if(part=1,subtypeval,0) as sd_interval_av,
if(part=2,typeval,0) as sd_interval_b,if(part=2,subtypeval,0) as sd_interval_bv,
if(part=3,typeval,0) as sd_interval_c,if(part=3,subtypeval,0) as sd_interval_cv,
if(part=4,typeval,0) as sd_interval_d,if(part=4,subtypeval,0) as sd_interval_dv,
if(part=5,typeval,0) as sd_interval_e,if(part=5,subtypeval,0) as sd_interval_ev,
if(part=6,typeval,0) as sd_interval_f,if(part=6,subtypeval,0) as sd_interval_fv,
if(part=7,typeval,0) as sd_interval_g,if(part=7,subtypeval,0) as sd_interval_gv,
if(part=8,typeval,0) as sd_interval_h,if(part=8,subtypeval,0) as sd_interval_hv,
if(part=9,typeval,0) as sd_interval_i,if(part=9,subtypeval,0) as sd_interval_iv,
if(part=10,typeval,0) as sd_interval_j,if(part=10,subtypeval,0) as sd_interval_jv
from 
(
{$sql}
) tmpd
) tmpe";
    }

    /** 获取分布类报表的待统计日期
     * @param $type 主指标名
     * @param $subtype 副指标名
     * @return array(dt,dtst,dtstr)
     */
    private static function __getDistributionReportsDt($type,$subtype){
        $add=0;
        //获取需要统计数据的日期
        $sql="select max(sd_dt) as dt from dq_stat_distribution where sd_type='{$type}' and sd_subtype='{$subtype}'";
        $rlt=Db::query($sql);
        Utils::L('ActiveReport',['最后日期',$rlt]);
        if ($rlt && $rlt[0]['dt']) {
            $add=86400;
            $dt=$rlt[0]['dt'];
        }else{
            $sql="select min(suv_dt) as dt from dq_stat_user_base where suv_dt>999999 and suv_dw=0";
            $rlt=Db::query($sql);
            if ($rlt && $rlt[0]['dt']) {
                $dt=$rlt[0]['dt'];
            }else{
                return [
                    'dt'=>false,
                    'dtst'=>false,
                    'dtstr'=>false
                ];;//没有数据，退出
            }
        }
        $dtst=strtotime(substr($dt,0,4).'-'.substr($dt,4,2).'-'.substr($dt,6,2))+$add;
        $dtstr=date("Y-m-d",$dtst);
        if ($dtstr>=date('Y-m-d')) {
            Utils::L('ActiveReport',['当天不统计',$dtstr]);
            return [
                'dt'=>false,
                'dtst'=>false,
                'dtstr'=>false
            ];
        }//当天不统计
        $dt=date("Ymd",$dtst);
        return [
            'dt'=>$dt,
            'dtst'=>$dtst,
            'dtstr'=>$dtstr
        ];
    }

    /** 统计与最终日数据相关的分布类报表
     * @return void
     */
    public static function countDistributionReports(){
        Utils::L('ActiveReport',['统计分布类报表']);
        //用户生命周期人数(不含未充值):平均贡献
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(不含未充值)','平均贡献');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(不含未充值)', '平均贡献', "
        select part,count(*) as typeval,floor(sum(gx)/count(*)) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,ta.sut_payinpricesum-ta.sut_withrawpricesum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0 and ta.sut_payinsum>0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(不含未充值):平均贡献', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(含未充值):平均贡献
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(含未充值)','平均贡献');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(含未充值)', '平均贡献', "
        select part,count(*) as typeval,if(count(*)=0,0,floor(sum(gx)/count(*))) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,ta.sut_payinpricesum-ta.sut_withrawpricesum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(含未充值):平均贡献', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(不含未充值):平均充值次数
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(不含未充值)','平均充值次数');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(不含未充值)', '平均充值次数', "
        select part,count(*) as typeval,floor(sum(gx)/count(*)) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,sut_payinsum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0 and ta.sut_payinsum>0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(不含未充值):平均充值次数', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(含未充值):平均充值金额
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(含未充值)','平均充值次数');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(含未充值)', '平均充值次数', "
        select part,count(*) as typeval,if(count(*)=0,0,floor(sum(gx)/count(*))) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,ta.sut_payinsum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(含未充值):平均充值次数', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(不含未充值):平均充值次数
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(不含未充值)','平均充值金额');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(不含未充值)', '平均充值金额', "
        select part,count(*) as typeval,floor(sum(gx)/count(*)) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,sut_payinpricesum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0 and ta.sut_payinsum>0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(不含未充值):平均充值金额', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(含未充值):平均充值金额
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(含未充值)','平均充值金额');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(含未充值)', '平均充值金额', "
        select part,count(*) as typeval,if(count(*)=0,0,floor(sum(gx)/count(*))) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,ta.sut_payinpricesum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(含未充值):平均充值金额', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(不含未充值):平均提现次数
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(不含未充值)','平均提现次数');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(不含未充值)', '平均提现次数', "
        select part,count(*) as typeval,floor(sum(gx)/count(*)) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,sut_withrawsum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0 and ta.sut_payinsum>0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(不含未充值):平均提现次数', $dt, $sql, $rlt]);
        }
        //用户生命周期人数(含未充值):平均提现次数
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户生命周期人数(含未充值)','平均提现次数');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户生命周期人数(含未充值)', '平均提现次数', "
        select part,count(*) as typeval,if(count(*)=0,0,floor(sum(gx)/count(*))) as subtypeval from 
(
select
case  
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=1 then 1
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=3 then 2
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=7 then 3
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=14 then 4
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=21 then 5
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=30 then 6
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=60 then 7
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=90 then 8
when ceil(({$dtst}-tb.add_time)/86400-ta.sut_loss)<=120 then 9
else 10 
end
as part,ta.sut_withrawsum as gx from dq_stat_user_midtemp ta,dq_user tb where ta.sut_uid=tb.id and ta.sut_dt={$dt} and ta.sut_dw=0
) tc
group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户生命周期人数(含未充值):平均提现次数', $dt, $sql, $rlt]);
        }
        //用户首充时长分段:平均充值金额
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户首充时长分段','平均充值金额');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql = self::__getDistributionReportsSql($dt, '用户首充时长分段', '平均充值金额', "
        select part,count(*) as typeval,floor(sum(gx)/count(*)) as subtypeval from
(
select 
case
when tb.update_time is null then 1
when floor((tb.update_time-ta.add_time)/86400) <1 then 2
when floor((tb.update_time-ta.add_time)/86400) <2 then 3
when floor((tb.update_time-ta.add_time)/86400) <3 then 4
when floor((tb.update_time-ta.add_time)/86400) <4 then 5
when floor((tb.update_time-ta.add_time)/86400) <5 then 6
when floor((tb.update_time-ta.add_time)/86400) <6 then 7
when floor((tb.update_time-ta.add_time)/86400) <7 then 8
when floor((tb.update_time-ta.add_time)/86400) <14 then 9
else 10
end
as part,ifnull(arrival_price,0) as gx from dq_user ta
left join 
dq_user_offline tb on ta.id=tb.uid and tb.first=1
 where ta.add_time<={$dtst}
 ) tc group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户首充时长分段:平均充值金额', $dt, $sql, $rlt]);
        }
        //用户首充时长分段:人数占比
        list('dt'=>$dt,'dtst'=>$dtst,'dtstr'=>$dtstr)=self::__getDistributionReportsDt('用户首充时长分段','人数占比');
        if ($dt) {
            $dtst += 86399;
            Utils::L('ActiveReport', ['准备统计日期', $dt]);
            $sql="select count(*) as num from dq_user where add_time<={$dtst}";
            $rlt = Db::query($sql);
            if ($rlt && $rlt[0]['num']) {
                $total = $rlt[0]['num'];
            }else{
                $total=0;
            }
            $sql = self::__getDistributionReportsSql($dt, '用户首充时长分段', '人数占比', "
        select part,count(*) as typeval,if({$total}=0,0,count(*)/{$total}*100) as subtypeval from
(
select 
case
when tb.update_time is null then 1
when floor((tb.update_time-ta.add_time)/86400) <1 then 2
when floor((tb.update_time-ta.add_time)/86400) <2 then 3
when floor((tb.update_time-ta.add_time)/86400) <3 then 4
when floor((tb.update_time-ta.add_time)/86400) <4 then 5
when floor((tb.update_time-ta.add_time)/86400) <5 then 6
when floor((tb.update_time-ta.add_time)/86400) <6 then 7
when floor((tb.update_time-ta.add_time)/86400) <7 then 8
when floor((tb.update_time-ta.add_time)/86400) <14 then 9
else 10
end
as part from dq_user ta
left join 
dq_user_offline tb on ta.id=tb.uid and tb.first=1
 where ta.add_time<={$dtst}
 ) tc group by part
        ");
            $rlt = Db::execute($sql);
            Utils::L('ActiveReport', ['用户首充时长分段:人数占比', $dt, $sql, $rlt]);
        }
    }



}