<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver77 extends VersionBase {

    public function run() {
        if (!$this->existTableFullName("dq_third_stats_global")) {
            $sql = <<<SQL
CREATE TABLE `dq_third_stats_global` (
  `su_dt` int(11) NOT NULL COMMENT 'YYYYMMDD',
  `su_tcid` int(11) DEFAULT '0' COMMENT '平台ID',
  `su_gid` int(11) DEFAULT '0' COMMENT '游戏ID',
  `su_gtype` int(11) DEFAULT '0' COMMENT '游戏类型',
  `rec_id3` int(11) DEFAULT '0' COMMENT '用户id',
  `rec_id2` int(11) DEFAULT '0',
  `rec_id` int(11) DEFAULT '0',
  `tree` varchar(1000) DEFAULT '',
  `su_uid` int(11) DEFAULT '0',
  `su_label` int(11) DEFAULT '0' COMMENT '用户标签',
  `su_betnum` int(11) DEFAULT '0' COMMENT '投注数量',
  `su_bet` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额',
  `su_losenum` int(11) DEFAULT '0' COMMENT '输次数',
  `su_lose` decimal(20,2) DEFAULT '0.00' COMMENT '输金额',
  `su_losereal` decimal(20,2) DEFAULT '0.00' COMMENT '实际输金额（已包含注单赢，但实际金额输的部分，比如半赢）',
  `su_winnum` int(11) DEFAULT '0' COMMENT '赢次数',
  `su_win` decimal(20,2) DEFAULT '0.00' COMMENT '赢金额',
  `su_winreal` decimal(20,2) DEFAULT '0.00' COMMENT '实际赢金额（已去掉本金，仅包含结算金额大于本金的部分）',
  `su_drawnum` int(11) DEFAULT '0' COMMENT '平次数',
  `su_draw` decimal(20,2) DEFAULT '0.00' COMMENT '平金额',
  `su_syswin` decimal(20,2) DEFAULT '0.00' COMMENT '平台盈亏，正数盈，负数亏',
  UNIQUE KEY `spl_dt` (`su_dt`,`su_uid`,`su_gid`) USING BTREE,
  KEY `su_tcid` (`su_tcid`) USING BTREE,
  KEY `su_gid` (`su_gid`) USING BTREE,
  KEY `su_gtype` (`su_gtype`) USING BTREE,
  KEY `su_uid` (`su_uid`),
  KEY `tree` (`tree`),
  KEY `rec_id3` (`rec_id3`),
  KEY `rec_id2` (`rec_id2`),
  KEY `rec_id` (`rec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户日数据报表（全局）';
SQL;
            $this->execute($sql);

            $sql = <<<SQL
CREATE TABLE `dq_third_stats_global_temp` (
  `su_dt` int(11) NOT NULL COMMENT 'YYYYMMDD',
  `su_tcid` int(11) DEFAULT '0' COMMENT '平台ID',
  `su_gid` int(11) DEFAULT '0' COMMENT '游戏ID',
  `su_gtype` int(11) DEFAULT '0' COMMENT '游戏类型',
  `rec_id3` int(11) DEFAULT '0' COMMENT '用户id',
  `rec_id2` int(11) DEFAULT '0',
  `rec_id` int(11) DEFAULT '0',
  `tree` varchar(1000) DEFAULT '0',
  `su_uid` int(11) DEFAULT '0',
  `su_label` int(11) DEFAULT '0' COMMENT '用户标签',
  `su_betnum` int(11) DEFAULT '0' COMMENT '投注数量',
  `su_bet` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额',
  `su_losenum` int(11) DEFAULT '0' COMMENT '输次数',
  `su_lose` decimal(20,2) DEFAULT '0.00' COMMENT '输金额',
  `su_losereal` decimal(20,2) DEFAULT '0.00' COMMENT '实际输金额（已包含注单赢，但实际金额输的部分，比如半赢）',
  `su_winnum` int(11) DEFAULT '0' COMMENT '赢次数',
  `su_win` decimal(20,2) DEFAULT '0.00' COMMENT '赢金额',
  `su_winreal` decimal(20,2) DEFAULT '0.00' COMMENT '实际赢金额（已去掉本金，仅包含结算金额大于本金的部分）',
  `su_drawnum` int(11) DEFAULT '0' COMMENT '平次数',
  `su_draw` decimal(20,2) DEFAULT '0.00' COMMENT '平金额',
  `su_syswin` decimal(20,2) DEFAULT '0.00' COMMENT '平台盈亏，正数盈，负数亏',
  UNIQUE KEY `spl_dt` (`su_dt`,`su_uid`,`su_gid`) USING BTREE,
  KEY `su_tcid` (`su_tcid`) USING BTREE,
  KEY `su_gid` (`su_gid`) USING BTREE,
  KEY `su_gtype` (`su_gtype`) USING BTREE,
  KEY `su_uid` (`su_uid`),
  KEY `tree` (`tree`),
  KEY `rec_id3` (`rec_id3`),
  KEY `rec_id2` (`rec_id2`),
  KEY `rec_id` (`rec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户日数据报表（全局）';
SQL;
            $this->execute($sql);

            $sql = <<<SQL
CREATE TABLE `dq_third_stats_global_betusernum` (
  `sus_dt` int(11) NOT NULL COMMENT '时间，yyyymmdd',
  `sus_key` varchar(100) NOT NULL COMMENT '查询组合条件 g游戏IDp代理UIDl用户标签t平台IDgt游戏分类',
  `sus_betusernum` int(11) NOT NULL DEFAULT '0' COMMENT '当日指定查询条件的投注人数',
  `sus_done` int(11) NOT NULL DEFAULT '0' COMMENT '是否已完成统计，用于区分当天即时统计数据是否已完成，未完成0，已完成则是时间戳',
  UNIQUE KEY `sus_dt` (`sus_dt`,`sus_key`),
  KEY `sus_dt_2` (`sus_dt`),
  KEY `sus_key` (`sus_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
SQL;
            $this->execute($sql);
        }
    }
}