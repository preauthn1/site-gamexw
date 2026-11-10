<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver82 extends VersionBase {

    public function run() {
        if (!$this->existTableFullName("dq_third_stats_fyfyinslog")) {
            $sql = <<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内部代理分佣扣减下级外部代理分佣记录表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableFullName("dq_third_stats_fyfyinslog_outproxy")) {
            $sql = <<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内部代理分佣扣减下级外部代理分佣记录表之代理贡献记录表';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_stats_fyfy", "sff_subout")) {
            $sql = "ALTER TABLE `dq_third_stats_fyfy` ADD COLUMN `sff_subout` decimal(20, 2) NULL DEFAULT 0 COMMENT '扣减内部代理下级外部代理分佣金额';";
            $this->execute($sql);
        }
    }
}