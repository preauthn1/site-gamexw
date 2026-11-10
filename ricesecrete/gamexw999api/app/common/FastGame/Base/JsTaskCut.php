<?php


namespace app\common\FastGame\Base;


use app\api\redis\ApiRedis;
use app\common\model\ThirdStatsBackModel;
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
 * 三方平台统一结算任务
 * Class JsTaskCut
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-09-17 12:22
 */
class JsTaskCut
{
    /**
     * 需要 env 配置支持
     * ;分表删数据单次条数，根据MYSQL服务器的性能进行调整配置
    CUT_TABLES_DEL_BATCH_NUM=50000
     */

    /** 分表任务，每月初择机执行，建议一天一次，执行两次
     * @author twt326
     * @date 2023-01-12 16:56
     */
    public static function cuttables()
    {
        $state=Redis::limitCache('globalcache','cuttables',null,false,60*60*24*2);
        if ($state) {//避免2天内重复执行
            echo  '任务已在执行中，如确认任务已经停止，请redis操作 select 6;hdel globalcache.cuttables';
            Utils::L('jscut','任务已在执行中，如确认任务已经停止，请redis操作 select 6;hdel globalcache.cuttables');
            return;
        }
        Redis::limitCache('globalcache','cuttables',1);
        try {
            Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
            self::cut_gamebets();
//            self::cut_stats_back();
//            self::cut_third_stats_fyfy();
//            self::cut_third_stats_fyfylog();
//            self::cut_third_stats_users();
//            self::cut_third_stats_global();
            //读取多时区配置
            $zones=SysSets::getSetByCode('COUNT_DATA_TIMEZONE_LIST');
            if (!empty($zones)) {
                //处理时区
                $uniquzone = [];
                foreach ($zones as $v) {
                    $v[0] = trim($v[0]);
                    if (!in_array(strtolower($v[0]), $uniquzone)) {
                        array_push($uniquzone, strtolower($v[0]));
                    }
                }
//                foreach ($uniquzone as $v){
//                    self::cut_third_stats_users('_'.$v);
//                    self::cut_third_stats_global('_'.$v);
//                }
            }

        }catch(\Exception $ex){
            Utils::L('jscut',['任务执行错误',$ex->getMessage(),$ex->getTraceAsString()]);
        }
        Redis::limitCache('globalcache','cuttables',0);
    }


    public static function test()
    {

        //$rlt=ThirdStatsBackModel::where(['month'=>202211,'args'=>['sb_dt'=>20221103]])->limit(1)->select();
        $rlt = ThirdStatsBackModel::where(202211, [['sb_dt', '=', '20221103']])->limit(0, 1)->select()->toArray();
        echo '-------------------ssssssss---------------sssssssss----------------\n';
        print_r($rlt);
        echo '-------------------eeeeeeee-------------eeeeeeeeee----------------\n';

        $rlt = ThirdStatsBackModel::where(202212, 'sb_dt', '=', '20221203')->limit(0, 1)->select()->toArray();
        echo '-------------------ssssssss---------------sssssssss----------------\n';
        print_r($rlt);
        echo '-------------------eeeeeeee-------------eeeeeeeeee----------------\n';


        $rlt = ThirdStatsBackModel::limit(202211, 0, 1)->select()->toArray();
        echo '-------------------ssssssss---------------sssssssss----------------\n';
        print_r($rlt);
        echo '-------------------eeeeeeee-------------eeeeeeeeee----------------\n';


        $m = new ThirdStatsBackModel(202211);
        //$rlt=$m->where('sb_dt',20221103)->limit(1)->select()->toArray();
        $rlt = $m->find(25)->toArray();
        echo '-------------------sssssssssssssssss----------------\n';
        print_r($rlt);
        echo '-------------------eeeeeeeeeeeeeeeeee----------------\n';
        die();
        //die(Utils::getCutTableEx('game_bets',202211));
        self::cut_gamebets();
        self::cut_stats_back();
        self::cut_third_stats_fyfy();
        self::cut_third_stats_fyfylog();
        self::cut_third_stats_users();
        //self::cut_third_stats_global();
    }

    public static function nextmonth($ym, $num = 1)
    {
        //Utils::L('jscut', ['nextmonth',$ym,$num]);
        if ($ym > 999999) $ym = date("Ym", $ym);
        $next = mktime(0, 0, 0, intval(substr($ym, 4, 2)) + $num, 1, intval(substr($ym, 0, 4)));
        //Utils::L('jscut', ['nextmonth',$ym,$next]);
        $ym = intval(date("Ym", $next));
        $st = strtotime(date("Y-m-01 00:00:00", $next));
        $et = strtotime(date("Y-m-01 00:00:00", $next + 86400 * 32)) - 1;
        //Utils::L('jscut', ['nextmonth',$ym,$st,$et]);
        return [
            'ym' => $ym,
            'st' => $st,
            'et' => $et
        ];
    }

    public static function getTableIndex($tablename){
        $sql="SELECT INDEX_NAME,COLUMN_NAME 
FROM information_schema.STATISTICS 
WHERE table_schema = '".env('database.database')."' AND table_name = '{$tablename}' and index_name<>'PRIMARY'";
        $indexs=Db::query($sql);
        Utils::L('jscut', ['找到表索引', "{$tablename}", $indexs, $sql]);
        return $indexs;
    }

    /** 添加分表索引
     * @param $tablename 分表名称
     * @param $indexs 主表原索引列表
     * @param $cindexs 分表当前索引列表
     * @param $notindexs 分表不需要索引的字段
     * @return void
     */
    public static function addTableIndex($tablename,$indexs,$cindexs,$notindexs){
        $sql=[];
        if (count($indexs)>0 && count($cindexs)==0){
            $tis=array_unique(array_column($indexs,'COLUMN_NAME'));
            foreach ($tis as $v) {
                if (!in_array($v,$notindexs)) {
                    array_push($sql, 'add index (' . $v . ')');
                }
            }
            $sql="alter table {$tablename} ".implode(',',$sql);
            Utils::L('jscut', ['准备增加表索引', "{$tablename}", $sql]);
            $rlt=Db::execute($sql);
            Utils::L('jscut', ['增加表索引', "{$tablename}", $rlt, $sql]);
        }
    }

    /** 有主键的表迁移数据
     * @param $table 原表
     * @param $ctable 切表
     * @param $prikey 主键字段名
     * @param $tablemaxidwhere 获取主表中要迁移数据的最大主键字段的值，如果是整型，则直接使用（用于有子表的情况）
     * @param $stepnum 单次写入数据量
     * @param string $datawhere 附加数据筛选条件，一般用于主键ID顺序与数据时间顺序存在不一致的可能的情况下,需要加AND
     * @return int|mixed 要切表的最大主键的值，一般用于给分表使用，避免重复获取最大ID，浪费资源
     */
    public static function transferDataWithPrimKey($table,$ctable, $prikey,$tablemaxidwhere,$stepnum,$datawhere=''){
        //主表中最大GBID
        Utils::L('jscut', ['准备获取原表中要分表的最大ID']);
        if (is_int($tablemaxidwhere)){
            $omaxid=$tablemaxidwhere;
        }else {
            $sql = "select max({$prikey}) as maxid from {$table} where {$tablemaxidwhere}";
            $rlt = Db::query($sql);
            Utils::L('jscut', ['原表中要分表的最大ID', $rlt, $sql]);
            $omaxid = $rlt[0]['maxid'] ?? 0;
        }
        //分表中最大GBID
        Utils::L('jscut', ['准备获取分表中最大ID']);
        $sql="select max({$prikey}) as maxid from {$ctable}";
        $rlt = Db::query($sql);
        Utils::L('jscut', ['切表中已迁移数据的最大ID', "{$ctable}", $rlt, $sql]);
        $cmaxid=$rlt[0]['maxid']??0;
        if ($omaxid>0 && $cmaxid<$omaxid){
            //还有数据没有迁移完成
            $znum = 1;
            $zcnt=0;
            while($znum>0){
                $sql = "insert into {$ctable} 
select * from {$table} where {$prikey}>{$cmaxid} and {$prikey}<={$omaxid} {$datawhere} order by {$prikey} limit {$stepnum}";
                $znum = Db::execute($sql);
                $zcnt += $znum;
                Utils::L('jscut', ['写入数据', $znum, $zcnt, $sql,$cmaxid,$omaxid]);
                $sql="select max({$prikey}) as maxid from {$ctable}";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['切表中已迁移数据的最大GBID', "{$ctable}", $rlt, $sql]);
                $cmaxid=$rlt[0]['maxid']??0;
            }
        }
        return $omaxid>$cmaxid?$omaxid:$cmaxid;
    }

    /** 无主键的表迁移数据
     * @param $table 原表
     * @param $ctable 切表
     * @param $datawhere 数据筛选条件
     * @return void
     */
    public static function transferDataWithoutPrimKey($table,$ctable,$datawhere){
        //判断原表数据数量
        $sql="select count(1) as num from {$table} where {$datawhere}";
        $rlt = Db::query($sql);
        $onum = $rlt[0]['num'] ?? 0;
        Utils::L('jscut', ['原表数据条数',  $rlt, $sql]);
        //判断切表数据数量
        $sql="select count(1) as num from {$ctable}";
        $rlt = Db::query($sql);
        $cnum = $rlt[0]['num'] ?? 0;
        Utils::L('jscut', ['切表据条数',  $rlt, $sql]);
        //如果切表数据数量小于原表数量则表示数据迁移没有完成，需要清表重新迁移
        if ($cnum < $onum && $cnum>0) {
            //清空切表
            $sql = "truncate table {$ctable}";
            $rlt = Db::execute($sql);
            Utils::L('jscut', ['清空切表',  $rlt, $sql]);
        }
        //迁移数据
        $sql = "insert into {$ctable} select * from {$table} where {$datawhere}";
        $r = Db::execute($sql);
        Utils::L('jscut', ['写入数据', $r,$sql]);
    }

    public static function delTableDatas($sql){
        $zfnum=1;
        $zfcnt=0;
        Utils::L('jscut', ['准备删除表数据']);
        while($zfnum>0){
            $zfnum = Db::execute($sql);
            $zfcnt += $zfnum;
            Utils::L('jscut', ['删除表数据', $zfnum, $zfcnt, $sql]);
        }
    }

    public static function createCutTable($tablename,$tableindexs,$cuttablename){
        $sql = "show tables like '{$cuttablename}'";
        $rlt = Db::query($sql);
        Utils::L('jscut', ['判断表是否存在', "{$cuttablename}", $rlt, $sql]);
        if (count($rlt) == 0) {
            $sql = "create table if not exists {$cuttablename} like {$tablename}";
            $rlt = Db::execute($sql);
            Utils::L('jscut', ['创建主表分表', "{$cuttablename}", $sql]);
            $sql = "ALTER TABLE {$cuttablename} ENGINE = MyISAM";
            $rlt = Db::execute($sql);
            Utils::L('jscut', ['更改主表类型为MYISAM', "{$cuttablename}", $sql]);
            //删除索引
            $sql = [];
            if (count($tableindexs) > 0) {
                $tis = array_unique(array_column($tableindexs, 'INDEX_NAME'));
                foreach ($tis as $v) {
                    array_push($sql, 'drop index ' . $v);
                }
                $sql = "alter table {$cuttablename} " . implode(',', $sql);
                Utils::L('jscut', ['准备删除主表索引', "{$cuttablename}", $sql]);
                $rlt = Db::execute($sql);
                Utils::L('jscut', ['删除主表索引', "{$cuttablename}", $rlt, $sql]);
            }

        }
    }


    public static function cut_gamebets()
    {
        //error_reporting(E_ALL ^ E_NOTICE);
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数 100W
        $stepnuminsert=1500000;//写数据倍速
        Utils::L('jscut', ['单次迁移数据条数',$stepnum]);
        $tbnamepre = 'dq_game_bets';
        $tbname = 'game_bets';
        $tbnamepreex = 'dq_game_betsex';
        Utils::L('jscut', '准备注单分表');
        //拿到注单表最早时间
        $sql = "select min(gb_bettime) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? date("Ym", $rlt[0]["ym"]) : false;
        Utils::L('jscut', ['拿到注单表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的注单数据');
        } else {
            $timestart=time();
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth( $betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的注单数据------------');
            } else {
                $m = $thism['ym'];
                $cts = $thism['st'];
                $cte = $thism['et'];
                //获取原表索引
                $indexs=self::getTableIndex($tbnamepre);
                $indexsex=self::getTableIndex($tbnamepreex);
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='$m'";
                $logrlt = Db::query($sql);
                Utils::L('jscut', ['分表记录', $logrlt]);
                try {

                        //创建表并删除索引
                        self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");
                        self::createCutTable($tbnamepreex, $indexsex, "{$tbnamepreex}_{$m}");

                        //迁移数据
                        $omaxid = self::transferDataWithPrimKey($tbnamepre, "{$tbnamepre}_{$m}", 'gb_id', 'gb_bettime<=' . $cte, $stepnuminsert,' and gb_bettime<=' . $cte);
                        $ocmaxid = self::transferDataWithPrimKey($tbnamepreex, "{$tbnamepreex}_{$m}", 'gbe_id', $omaxid, $stepnuminsert,' and gbe_inmonth=' . $m);
                        //增加索引
                        //找分表索引
                        $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                        $cindexsex = self::getTableIndex("{$tbnamepreex}_{$m}");
                        //增加索引
                        self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, ['gb_intime', 'gb_cflag', 'gb_cflagt', 'gb_gcflag', 'gb_gcflagt']);
                        self::addTableIndex("{$tbnamepreex}_{$m}", $indexsex, $cindexsex, []);
                    //如果迁移完成则更新分表记录状态
                    $timeend=time();
                    if (count($logrlt) == 0) {
                        $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$cts,$cte,$timestart,$timeend)";
                        Db::execute($sql);
                    }
                    //删除数据
                    self::delTableDatas("delete from {$tbnamepreex} where gbe_id<={$ocmaxid} and gbe_inmonth={$m} order by gbe_id limit $stepnum");
                    self::delTableDatas("delete from {$tbnamepre} where gb_id<={$omaxid} and gb_bettime<={$cte} order by gb_id limit $stepnum");


                    $timeend=time();
                    Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
                } catch (\Exception $ex) {
                    Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
                }
            }

        }

    }

    //切分注单表历史数据(两个月前)
    public static function cut_gamebets_bak()
    {
        //error_reporting(E_ALL ^ E_NOTICE);
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',300000); //每次转移注表条数 100W
        $tbnamepre = 'dq_game_bets';
        $tbname = 'game_bets';
        $tbnamepreex = 'dq_game_betsex';
        Utils::L('jscut', '准备注单分表');
        //拿到注单表最早时间
        $sql = "select min(gb_bettime) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? date("Ym", $rlt[0]["ym"]) : false;
        Utils::L('jscut', ['拿到注单表最早时间', $betmin]);
        //拿到注单表结算最早时间
        $sql = "select min(gb_overtime) as ym from dq_game_bets where gb_overtime>0";
        $rlt = Db::query($sql);
        $overmin = $rlt[0]["ym"] ? date("Ym", $rlt[0]["ym"]) : false;
        Utils::L('jscut', ['拿到注单表结算最早时间', $overmin]);
        if (!$betmin && !$overmin) {
            Utils::L('jscut', '当前没有需要分表的注单数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            //判断分表是否存在
            $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
            $rlt = Db::query($sql);
            Utils::L('jscut', ['根据最小注单时间获取分表', $rlt]);
            $betcut = (count($rlt) > 0);
            /*$sql="select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='".$overmin[0]['ym']."'";
            $overcut=count(Db::query($sql))>0;*/
            $thism = self::nextmonth($betcut ? $overmin : $betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的注单数据------------');
            } else {
                $m = $thism['ym'];
                $cts = $thism['st'];
                $cte = $thism['et'];
                $timestart = time();
                $cnt = 0;
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='$m'";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['分表记录', $rlt]);
                if (count($rlt) == 0) {
                    $runflag = true;
                    try {
                        //创建表 索引与基础表并不一样
                        /*$sql = "CREATE TABLE if not EXISTS `{$tbnamepre}_{$m}`  (
  `gb_id` bigint(11) unsigned NOT NULL,
    `gb_tcid` int(11) DEFAULT NULL COMMENT '三方平台ID',
  `gb_gid` int(11) NOT NULL COMMENT '投注游戏（为NULL表示此投注记录失去用户联系）',
  `gb_uid` int(11) NOT NULL DEFAULT '0' COMMENT '投注用户（为NULL表示此投注记录失去用户关系）',
  `gb_intime` int(11) NOT NULL COMMENT '系统获取记录时间',
  `gb_price` decimal(20,2) NOT NULL COMMENT '投注金额（包含彩金）',
    `gb_cjprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）' AFTER `gb_price`,
  `gb_sn` varchar(64) DEFAULT NULL COMMENT '投注单号或KEY',
  `gb_winlose` int(11) DEFAULT '0' COMMENT '输赢状态 0无效 1未结算 2输 3赢 4平',
  `gb_bettime` int(11) DEFAULT NULL COMMENT '投注时间',
  `gb_overtime` int(11) DEFAULT NULL COMMENT '结算时间',
  `gb_winprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢的金额（包含彩金）',
    `gb_wincjprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）' AFTER `gb_winprice`,
  `gb_backstateks` varchar(64) DEFAULT NULL COMMENT '代理结算状态（结算方法使用）',
  `gb_cusfield` varchar(64) DEFAULT NULL COMMENT '一个带索引的自定义字段，对于特殊的三方注单列表处理时可以用于存放特殊的值',
  `gb_otherwin` decimal(10,2) DEFAULT '0.00' COMMENT '统计输赢金额（派彩-投注）',
  `gb_validwater` decimal(10,2) DEFAULT '0.00' COMMENT '统计有效流水 if(派彩>投注，投注，投注-派彩）',
  `gb_cflag` int(11) DEFAULT '0' COMMENT '注单统计标志，有值表示已经被统计到用户报表中了',
  `gb_cflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志，有值表示已经被统计到用户报表中了',
  `gb_gcflag` int(11) DEFAULT '0' COMMENT '注单结算统计标志(综合)，有值表示已经被统计到用户报表中了',
  `gb_gcflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志（综合），有值表示已经被统计到用户报表中了'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户投注记录表';";*/
                        $sql="create table if not exists {$tbnamepre}_{$m} like {$tbnamepre}";
                        $rlt=Db::execute($sql);
                        Utils::L('jscut', ['创建主表分表',"{$tbnamepre}_{$m}",$rlt,$sql]);
                        $sql="ALTER TABLE {$tbnamepre}_{$m} ENGINE = MyISAM";
                        $rlt=Db::execute($sql);
                        Utils::L('jscut', ['更改主表类型为MYISAM',"{$tbnamepre}_{$m}",$rlt,$sql]);
                        //创建表 索引与基础表并不一样
                        /*$sql = "CREATE TABLE `{$tbnamepreex}_{$m}` (
  `gbe_id` int(11) NOT NULL COMMENT '注单ID',
  `gbe_inmonth` int(11) DEFAULT NULL COMMENT '切表所属月份',
  `gbe_betinfo` text COMMENT '注单原始信息'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='注单扩展信息表';";*/
                        $sql="create table if not exists {$tbnamepreex}_{$m} like {$tbnamepreex}";
                        Db::execute($sql);
                        $sql="ALTER TABLE {$tbnamepreex}_{$m} ENGINE = MyISAM";
                        Db::execute($sql);

                        //清空可能存在的数据 TODO 未来可能需要补充删掉表索引（在清空数据后）的语句
                        Db::execute("truncate table {$tbnamepre}_{$m}");
                        Db::execute("truncate table {$tbnamepreex}_{$m}");

                        //Db::startTrans();

                        /*$have = true;
                        do {
                            //写数据  TODO 在有可能出现后补数据进入历史表时，要判断gb_id是否重复，目前暂时还没有这样的需求
                        //TODO 记：如果要采用分批写入数据的话，则需要使用ID做为每次的标记，因为数据的删除逻辑与转移逻辑有差别
                            $sql="insert into {$tbnamepre}_{$m}
select * from dq_game_bets where gb_bettime>=$cts and gb_bettime<$cte or gb_overtime>=$cts and gb_overtime<$cte order by gb_id limit $stepnum";
                            $r=Db::execute($sql);
                            $cnt+=$r;
                            Utils::L('jscut',['写入数据',$r,'总：',$cnt]);
                            //删除数据
                            $sql="delete from dq_game_bets where gb_bettime>=$cts and gb_bettime<$cte or gb_overtime>=$cts and gb_overtime<$cte order by gb_id limit $stepnum";
                            $r=Db::execute($sql);
                            Utils::L('jscut',['删除数据',$r]);
                            //判断数据是否已迁移完成
                            $sql = "select * from dq_game_bets where gb_bettime>=$cts and gb_bettime<$cte or gb_overtime>=$cts and gb_overtime<$cte order by gb_id limit 1";
                            $have = count(Db::query($sql)) > 0;
                        } while ($have);*/

                        //写数据  TODO 在有可能出现后补数据进入历史表时，要判断gb_id是否重复，目前暂时还没有这样的需求
                        // or gb_overtime=0 超过一个月还没有结算的也迁移过来
                        /*$sql = "insert into {$tbnamepre}_{$m} (gb_id,gb_tcid,gb_gid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_otherwin,gb_validwater,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt)
select gb_id,gb_tcid,gb_gid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_otherwin,gb_validwater,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt from {$tbnamepre} where (gb_bettime>=$cts and gb_bettime<$cte) or (gb_overtime>=$cts and gb_overtime<$cte) or (gb_bettime>=$cts and gb_bettime<$cte and gb_overtime=0)";*/
                        $sql = "insert into {$tbnamepre}_{$m} 
select * from {$tbnamepre} where (gb_bettime>=$cts and gb_bettime<$cte) or (gb_overtime>=$cts and gb_overtime<$cte) or (gb_bettime<$cte and gb_overtime=0)";
                        $runflag = Db::execute($sql);
                        $cnt += $runflag;
                        Utils::L('jscut', ['写入数据', $runflag, '总：', $cnt, '时间：', time() - $timestart,$sql]);
                        $timestart=time();
                        $cnt=0;
                        /*$sql = "insert into {$tbnamepreex}_{$m} (gbe_id,gbe_inmonth,gbe_betinfo)
select gbe_id,gbe_inmonth,gbe_betinfo from {$tbnamepreex} where gbe_inmonth={$m}";*/
                        $sql = "insert into {$tbnamepreex}_{$m} 
select * from {$tbnamepreex} where gbe_inmonth={$m}";
                        $runflag = Db::execute($sql);
                        $cnt += $runflag;
                        Utils::L('jscut', ['写入附表数据', $runflag, '总：', $cnt, '时间：', time() - $timestart,$sql]);


                        //表加索引
                        /*$idxtime = time();
                        $sql = "ALTER TABLE `{$tbnamepre}_{$m}`
  ADD PRIMARY KEY (`gb_id`) USING BTREE,
  ADD KEY `b_gid` (`gb_gid`) USING BTREE,
  ADD KEY `b_uid` (`gb_uid`) USING BTREE,
  ADD KEY `b_intime` (`gb_intime`) USING BTREE,
  ADD KEY `b_bettime` (`gb_bettime`) USING BTREE,
  ADD KEY `gb_overtime` (`gb_overtime`) USING BTREE,
  ADD KEY `gb_cusfield` (`gb_cusfield`) USING BTREE,
  ADD KEY `idx_tcid` (`gb_tcid`) USING BTREE";
                        Db::execute($sql);
                        Utils::L('jscut', ['表加索引', '时间：', time() - $idxtime]);
                        $idxtime = time();
                        $sql = "ALTER TABLE `{$tbnamepreex}_{$m}`
  ADD PRIMARY KEY (`gbe_id`) USING BTREE";
                        Db::execute($sql);
                        Utils::L('jscut', ['附表加索引', '时间：', time() - $idxtime]);*/

                    } catch (\Exception $ex) {
                        $runflag = false;

                        Utils::L('jscut', ['写入数据阶段错误', $ex->getMessage()]);
                    }

                    if ($runflag) {
                        try {

                            Db::startTrans();

                            //删除数据 分批 本地实测速度52W数据，单次50000条1.64倍，15W条1.2倍，30W条时间大体相当，但样本数据量太小，不足以作为确定的依据，还需要进行大数据测试
                            $deltime = time();
                            $on = 1;
                            $cnt = 0;
                            do {
                                $delstime = time();
                                //删除数据
                                $sql = "delete from {$tbnamepre} where (gb_bettime>=$cts and gb_bettime<$cte) or (gb_bettime<$cte and gb_overtime=0) or (gb_overtime>=$cts and gb_overtime<$cte) order by gb_id limit $stepnum";
                                $on = Db::execute($sql);
                                $cnt += $on;
                                Utils::L('jscut', ['删除数据', $on, 'count:', $cnt, '本次时间：', time() - $delstime, '总时间：', time() - $deltime]);
                                //删除附表数据
                                $delstime = time();
                                $sql = "delete from {$tbnamepreex} where gbe_inmonth={$m} order by gbe_id limit $stepnum";
                                $on = Db::execute($sql);
                                $cnt += $on;
                                Utils::L('jscut', ['删除附表数据', $on, 'count:', $cnt, '本次时间：', time() - $delstime, '总时间：', time() - $deltime]);
                            } while ($on > 0);

                            //删除数据(一次性)
                            /*$deltime=time();
                            $sql = "delete from dq_game_bets where gb_bettime>=$cts and gb_bettime<$cte and gb_overtime=0 or gb_overtime>=$cts and gb_overtime<$cte";
                            $r = Db::execute($sql);
                            Utils::L('jscut', ['删除数据', $r,'时间：',time()-$deltime]);*/


                            $timeend = time();
                            Utils::L('jscut', ['迁移数据完成', $cnt, $timestart, $timeend, '总执行时间：', $timeend - $timestart]);
                            //如果迁移完成则更新分表记录状态

                            $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$cts,$cte,$timestart,$timeend)";
                            Db::execute($sql);

                            Db::commit();
                            Utils::L('jscut', '-------分表完成-----------');
                        } catch (\Exception $ex) {
                            Db::rollback();

                            Utils::L('jscut', ['迁移数据出错(删除阶段)', $thism, $ex->getMessage()]);
                        }
                    }

                } else {
                    //TODO 处理这种情况
                    Utils::L('jscut', '----------ERROR:----------当月数据表已经生成，但注单表中还存在遗留数据');
                }

            }
        }


        //拿到最后生成表时间
        /*$sql="select max(ct_m) as m from dq_cut_tables where ct_table='game_bets'";
        $rlt=Db::query($sql);
        if (!$rlt[0]['m']){
            $sql="select min(gb_bettime) as m from dq_game_bets";
            $rlt=Db::query($sql);
            if (!$rlt[0]['m']){
                Utils::L('jscut','当前没有需要分表的注单数据');
            }else{
                Utils::L('jscut','当前没有需要分表的注单数据');
            }
        }else{
            $m=self::nextmonth($rlt[0]['m']);
        }*/

    }

    public static function cut_stats_back()
    {
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数
        $stepnuminsert=1500000;//写数据倍速
        $tbnamepre = 'dq_third_stats_back';
        $tbname = 'third_stats_back';
        Utils::L('jscut', '准备返水分表');
        //拿到返水表最早时间
        $sql = "select min(sb_dt) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? substr($rlt[0]["ym"], 0, 6) : false;
        Utils::L('jscut', ['拿到返水表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的返水数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth($betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的数据------------');
                return;
            }
            $m = $thism['ym'];
            $ctsint = $thism['st'];
            $cteint = $thism['et'];
            $cts = date("Ymd", $thism['st']);
            $cte = date("Ymd", $thism['et']);
            $timestart = time();
            try {
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['判断分表是否存在', $rlt]);
                $betcut = (count($rlt) > 0);

                    //获取原表索引
                    $indexs = self::getTableIndex($tbnamepre);

                        //创建表并删除索引
                        self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");

                    //迁移数据
                    $omaxid = self::transferDataWithPrimKey($tbnamepre, "{$tbnamepre}_{$m}", 'sb_id', 'sb_dt<=' . $cte, $stepnuminsert, " and sb_dt between $cts and $cte");
                    //增加索引
                    //找分表索引
                    $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                    self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, []);
                    //如果迁移完成则更新分表记录状态
                    $timeend = time();
                if (!$betcut) {
                    $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$ctsint,$cteint,$timestart,$timeend)";
                    Db::execute($sql);
                }
                //删除数据
                self::delTableDatas("delete from {$tbnamepre} where sb_id<={$omaxid} and sb_dt between $cts and $cte order by sb_id limit $stepnum");
                $timeend=time();
                Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
            } catch (\Exception $ex) {
                Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
            }
        }
    }

    public static function cut_third_stats_fyfy()
    {
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数
        $stepnuminsert=1500000;//写数据倍速
        $tbnamepre = 'dq_third_stats_fyfy';
        $tbname = 'third_stats_fyfy';
        Utils::L('jscut', '准备代理分佣负盈模式分表');
        //拿到代理分佣负盈模式表最早时间
        $sql = "select min(sff_m) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ?? false;
        Utils::L('jscut', ['拿到代理分佣负盈模式表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的代理分佣负盈模式数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth($betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的数据------------');
                return;
            }
            $m = $thism['ym'];
            $cte = $thism['et'];
            $cts = $thism['st'];
            $timestart = time();
            try{
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['判断分表是否存在', $rlt]);
                $betcut = (count($rlt) > 0);

                    //获取原表索引
                    $indexs = self::getTableIndex($tbnamepre);

                    //创建表并删除索引
                    self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");

                    //迁移数据
                    $omaxid = self::transferDataWithPrimKey($tbnamepre, "{$tbnamepre}_{$m}", 'sff_id', "sff_m=$m", $stepnuminsert, "and sff_m=$m");
                    //增加索引
                    //找分表索引
                    $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                    self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, ['sff_m']);
                    //如果迁移完成则更新分表记录状态
                    $timeend = time();
                if (!$betcut) {
                    $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$cts,$cte,$timestart,$timeend)";
                    Db::execute($sql);
                }
                //删除数据
                self::delTableDatas("delete from {$tbnamepre} where sff_id<={$omaxid} and sff_m=$m order by sff_id limit $stepnum");
                $timeend=time();
                Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
            } catch (\Exception $ex) {
                Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
            }

        }
    }


    public static function cut_third_stats_fyfylog()
    {
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数
        $tbnamepre = 'dq_third_stats_fyfylog';
        $tbname = 'third_stats_fyfylog';
        Utils::L('jscut', '准备代理分佣负盈模式日志分表');
        //拿到代理分佣负盈模式日志表最早时间
        $sql = "select min(sffl_dt) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? substr($rlt[0]["ym"], 0, 6) : false;
        Utils::L('jscut', ['拿到代理分佣负盈模式日志表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的代理分佣负盈模式日志数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth($betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的数据------------');
                return;
            }
            $m = $thism['ym'];
            $ctsint = $thism['st'];
            $cteint = $thism['et'];
            $cts = date("Ymd", $thism['st']);
            $cte = date("Ymd", $thism['et']);
            $timestart = time();
            try{
            //判断分表是否存在
            $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
            $rlt = Db::query($sql);
            Utils::L('jscut', ['判断分表是否存在', $rlt]);
            $betcut = (count($rlt) > 0);

                    //获取原表索引
                    $indexs = self::getTableIndex($tbnamepre);

                    //创建表并删除索引
                    self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");

                    //迁移数据
                    self::transferDataWithoutPrimKey($tbnamepre,"{$tbnamepre}_{$m}","sffl_dt between $cts and $cte");

                    //增加索引
                    //找分表索引
                    $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                    self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, []);
                    //如果迁移完成则更新分表记录状态
                    $timeend = time();
                if (!$betcut) {
                    $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$ctsint,$cteint,$timestart,$timeend)";
                    Db::execute($sql);
                }
                //删除数据
                self::delTableDatas("delete from {$tbnamepre} where sffl_dt between $cts and $cte limit $stepnum");
                $timeend=time();
                Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
            } catch (\Exception $ex) {
                Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
            }
        }
    }


    public static function cut_third_stats_users($zoneEx='')
    {
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数
        $tbnamepre = 'dq_third_stats_users'.$zoneEx;
        $sql = "show tables like '{$tbnamepre}'";
        $rlt = Db::query($sql);
        if (count($rlt) == 0) {
            Utils::L('jscut', ['原表不存在，不需要切表', "{$tbnamepre}", $rlt, $sql]);
            return;
        }
        $tbname = 'third_stats_users'.$zoneEx;
        Utils::L('jscut', '准备用户日数据分表');
        //拿到代理分佣负盈模式日志表最早时间
        $sql = "select min(su_dt) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? substr($rlt[0]["ym"], 0, 6) : false;
        Utils::L('jscut', ['拿到用户日数据表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的用户日数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth($betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的数据------------');
                return;
            }
            $m = $thism['ym'];
            $ctsint = $thism['st'];
            $cteint = $thism['et'];
            $cts = date("Ymd", $thism['st']);
            $cte = date("Ymd", $thism['et']);
            $timestart = time();
            try{
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['判断分表是否存在', $rlt]);
                $betcut = (count($rlt) > 0);

                    //获取原表索引
                    $indexs = self::getTableIndex($tbnamepre);

                    //创建表并删除索引
                    self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");

                    //迁移数据
                    self::transferDataWithoutPrimKey($tbnamepre,"{$tbnamepre}_{$m}","su_dt between $cts and $cte");

                    //增加索引
                    //找分表索引
                    $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                    self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, []);
                    //如果迁移完成则更新分表记录状态
                    $timeend = time();
                if (!$betcut) {
                    $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$ctsint,$cteint,$timestart,$timeend)";
                    Db::execute($sql);
                }
                //删除数据
                self::delTableDatas("delete from {$tbnamepre} where su_dt between $cts and $cte limit $stepnum");
                $timeend=time();
                Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
            } catch (\Exception $ex) {
                Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
            }

        }
    }


    //TODO 完成统计表后再来进行分表完善和测试
    public static function cut_third_stats_global($zoneEx='')
    {
        $stepnum = env('CUT_TABLES_DEL_BATCH_NUM',50000); //每次转移注表条数
        $tbnamepre = 'dq_third_stats_global'.$zoneEx;
        $sql = "show tables like '{$tbnamepre}'";
        $rlt = Db::query($sql);
        if (count($rlt) == 0) {
            Utils::L('jscut', ['原表不存在，不需要切表', "{$tbnamepre}", $rlt, $sql]);
            return;
        }
        $tbname = 'third_stats_global'.$zoneEx;
        Utils::L('jscut', '准备代理分佣负盈模式日志分表');
        //拿到代理分佣负盈模式日志表最早时间
        $sql = "select min(su_dt) as ym from $tbnamepre";
        $rlt = Db::query($sql);
        $betmin = $rlt[0]["ym"] ? substr($rlt[0]["ym"], 0, 6) : false;
        Utils::L('jscut', ['拿到代理分佣负盈模式日志表最早时间', $betmin]);
        if (!$betmin) {
            Utils::L('jscut', '当前没有需要分表的代理分佣负盈模式日志数据');
        } else {
            //是否超过两个月
            $stopm = self::nextmonth(time(), -2);
            Utils::L('jscut', ['截止月份', $stopm]);
            $thism = self::nextmonth($betmin, 0);
            Utils::L('jscut', ['当前月份', $thism]);
            if ($thism['ym'] > $stopm['ym']) {
                Utils::L('jscut', '------------当前没有需要分表的数据------------');
                return;
            }
            $m = $thism['ym'];
            $ctsint = $thism['st'];
            $cteint = $thism['et'];
            $cts = date("Ymd", $thism['st']);
            $cte = date("Ymd", $thism['et']);
            $timestart = time();
            try{
                //判断分表是否存在
                $sql = "select ct_m from dq_cut_tables where ct_table='$tbname' and ct_m='" . $betmin . "'";
                $rlt = Db::query($sql);
                Utils::L('jscut', ['判断分表是否存在', $rlt]);
                $betcut = (count($rlt) > 0);

                    //获取原表索引
                    $indexs = self::getTableIndex($tbnamepre);

                    //创建表并删除索引
                    self::createCutTable($tbnamepre, $indexs, "{$tbnamepre}_{$m}");

                    //迁移数据
                    self::transferDataWithoutPrimKey($tbnamepre,"{$tbnamepre}_{$m}","su_dt between $cts and $cte");

                    //增加索引
                    //找分表索引
                    $cindexs = self::getTableIndex("{$tbnamepre}_{$m}");
                    self::addTableIndex("{$tbnamepre}_{$m}", $indexs, $cindexs, ['tree']);
                    //如果迁移完成则更新分表记录状态
                    $timeend = time();
                if (!$betcut) {
                    $sql = "insert into dq_cut_tables (ct_table,ct_m,ct_s,ct_e,ct_intime,ct_endtime) values ('$tbname',$m,$ctsint,$cteint,$timestart,$timeend)";
                    Db::execute($sql);
                }
                //删除数据
                self::delTableDatas("delete from {$tbnamepre} where su_dt between $cts and $cte limit $stepnum");
                $timeend=time();
                Utils::L('jscut', ['分表完成', '总执行时间：', $timeend - $timestart]);
            } catch (\Exception $ex) {
                Utils::L('jscut', ['处理数据错误', $ex->getMessage(),$ex->getTraceAsString()]);
            }
        }
    }


}
