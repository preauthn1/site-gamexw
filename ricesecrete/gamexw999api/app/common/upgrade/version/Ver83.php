<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver83 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_journalism", "start_time")) {
            $sql = "ALTER TABLE `dq_journalism` ADD COLUMN `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '开始时间';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_journalism", "end_time")) {
            $sql = "ALTER TABLE `dq_journalism` ADD COLUMN `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '结束时间';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_journalism", "amount")) {
            $sql = "ALTER TABLE `dq_journalism` ADD COLUMN `amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '赛事奖金';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_cut_tables")) {
            $sql = <<<SQL
CREATE TABLE `dq_cut_tables` (
  `ct_table` varchar(32) NOT NULL COMMENT '切分表',
  `ct_m` int(11) NOT NULL COMMENT '表年月yyyymm',
  `ct_s` int(11) NOT NULL COMMENT '数据开始时间戳',
  `ct_e` int(11) NOT NULL COMMENT '数据结束时间戳',
  `ct_intime` int(11) NOT NULL COMMENT '建表时间',
  `ct_endtime` int(11) DEFAULT NULL COMMENT '数据更新完成时间',
  `ct_flag` bigint(11) DEFAULT NULL COMMENT '冗余，可用于存储时间标志或ID标志',
  KEY `ct_table` (`ct_table`),
  KEY `ct_m` (`ct_m`),
  KEY `ct_s` (`ct_s`),
  KEY `ct_e` (`ct_e`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分表记录表';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_stats_fyfy", "sff_exinf")) {
            $sql = "ALTER TABLE `dq_third_stats_fyfy` ADD COLUMN `sff_exinf` varchar(255) NULL COMMENT '扩展信息，比如保存外部代理流水分佣每次的分佣设置';";
            $this->execute($sql);
        }
    }
}