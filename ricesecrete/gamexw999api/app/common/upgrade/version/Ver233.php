<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver233 extends VersionBase
{

    public function run()
    {
        if (!$this->existTable("dq_jackpot")) {
            $sql = <<<SQL
CREATE TABLE `dq_jackpot`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `j_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '榜单类型 1日榜 2周榜 3月榜',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
  `username` varchar(100) NULL DEFAULT NULL COMMENT '用户名',
  `head_img` varchar(255) NULL DEFAULT NULL COMMENT '头像',
  `price` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '下注金额',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否真实用户 1是 2否',
  `day` int(11) NULL DEFAULT NULL COMMENT '日期',
  `min_rand` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '假数据随机最小值',
  `max_rand` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '假数据随机最大值',
  `upper_limit` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '假数据金额上限',
  `ranking` int(6) NOT NULL DEFAULT 0 COMMENT '排名',
  `create_time` bigint(20) NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` bigint(20) NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_uid`(`uid`) USING BTREE,
  INDEX `idx_day`(`day`) USING BTREE,
  INDEX `idx_j_type`(`j_type`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_price`(`price`) USING BTREE,
  INDEX `idx_ranking`(`ranking`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '用户排行榜';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTable("dq_jackpot_config")) {
            $time = time();
            $sql = <<<SQL
CREATE TABLE `dq_jackpot_config`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型 1日榜 2周榜 3月榜',
  `and_time` bigint(20) NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` bigint(20) NULL DEFAULT NULL COMMENT '结束时间',
  `bet_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '统计类型 1下注金额 2有效流水',
  `portional_config` text NULL COMMENT '排名奖金比例配置',
  `level_id` int(11) NOT NULL DEFAULT 0 COMMENT '指定等级ID',
  `tag_id` int(11) NOT NULL DEFAULT 0 COMMENT '可参与用户标签ID',
  `agent_id` varchar(200) NOT NULL DEFAULT '0' COMMENT '可参与代理id',
  `fcharge_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '首冲并且有下注记录状态 0关 1开',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 0关闭',
  `water` int(5) NULL DEFAULT 0 COMMENT '流水倍数',
  `starting_amount` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '起始金额',
  `jackpot_time` varchar(30) NULL DEFAULT NULL COMMENT '结算点时间格式：00:00',
  `next_time` bigint(20) NULL DEFAULT NULL COMMENT '奖金下次结算时间',
  `create_time` bigint(20) NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` bigint(20) NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '排行榜配置';
SQL;
            $this->execute($sql);
            $inssetSql1 = "INSERT INTO `dq_jackpot_config` (`id`,`type`,`and_time`,`end_time`,`bet_type`,`portional_config`,`level_id`,`tag_id`,`agent_id`,`fcharge_status`,`status`,`water`,`starting_amount`,`jackpot_time`,`next_time`,`create_time`) VALUES (1, 1, null, null, 1,'[]',0,0,0,0,0,0,0,'23:30',null,{$time});";
            $this->execute($inssetSql1);
            $inssetSql2 = "INSERT INTO `dq_jackpot_config` (`id`,`type`,`and_time`,`end_time`,`bet_type`,`portional_config`,`level_id`,`tag_id`,`agent_id`,`fcharge_status`,`status`,`water`,`starting_amount`,`jackpot_time`,`next_time`,`create_time`) VALUES (2, 2, null, null, 1,'[]',0,0,0,0,0,0,0,'23:30',null,{$time});";
            $this->execute($inssetSql2);
            $inssetSql3 = "INSERT INTO `dq_jackpot_config` (`id`,`type`,`and_time`,`end_time`,`bet_type`,`portional_config`,`level_id`,`tag_id`,`agent_id`,`fcharge_status`,`status`,`water`,`starting_amount`,`jackpot_time`,`next_time`,`create_time`) VALUES (3, 3, null, null, 1,'[]',0,0,0,0,0,0,0,'23:30',null,{$time});";
            $this->execute($inssetSql3);
        }

        if (!$this->existTable("dq_jackpot_content")) {
            $sql = <<<SQL
CREATE TABLE `dq_jackpot_content`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `j_id` int(11) NOT NULL DEFAULT 0 COMMENT '配置id',
  `lang` varchar(50)  NOT NULL COMMENT '语言',
  `introduce` longtext  NULL COMMENT '规则',
  `create_time` bigint(20) NOT NULL COMMENT '创建时间',
  `update_time` bigint(20) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `lang`(`lang`) USING BTREE,
  INDEX `j_id`(`j_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '排行榜规则内容';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTable("dq_jackpot_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_jackpot_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day` int(11) NULL DEFAULT NULL COMMENT '日期',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
  `price` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '发放金额',
  `proportion` decimal(10, 4) NULL DEFAULT NULL COMMENT '换算后比例',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '榜单类型 1日榜 2周榜 3月榜',
  `ranking` int(6) NOT NULL DEFAULT 0 COMMENT '排名',
  `create_time` bigint(20) NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_uid`(`uid`) USING BTREE,
  INDEX `idx_day`(`day`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_price`(`price`) USING BTREE,
  INDEX `idx_ranking`(`ranking`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '排行榜奖金发放记录';
SQL;
            $this->execute($sql);
        }

        //配置菜单
        $jackpotNodeId = $this->getNodeIdByNodeUrl('/jackpot');
        if( !$jackpotNodeId ){
            $jackpotNodeId = $this->addPublicParentNode(
                '排行榜管理', '/jackpot',
                'jackpot', '/jackpot',
                0, 15, false,0
            );
        }

        //配置菜单
        //配置管理
        $configNodeId = $this->getNodeIdByNodeUrlPid('/jackpot/config', $jackpotNodeId);
        if( !$configNodeId ){
            $configNodeId = $this->addPublicParentNode(
                '配置管理', '/jackpot/config',
                'jackpotConfig', '/jackpot/config',
                $jackpotNodeId, 1, false
            );
        }
        //编辑
        $configEditNodeId = $this->getNodeIdByNodeUrlPid('edit', $configNodeId);
        if( !$configEditNodeId ){
            $this->addPublicParentNode(
                '编辑', 'edit',
                'edit', 'edit',
                $configNodeId, 1, false
            );
        }

        //活动内容
        $contentNodeId = $this->getNodeIdByNodeUrlPid('active', $configNodeId);
        if( !$contentNodeId ){
            $this->addPublicParentNode(
                '设置活动内容', 'active',
                'active', 'active',
                $configNodeId, 2, false
            );
        }

        //排行榜
        $rankingsNodeId = $this->getNodeIdByNodeUrlPid('/jackpot/list', $jackpotNodeId);
        if( !$rankingsNodeId ){
            $rankingsNodeId = $this->addPublicParentNode(
                '用户排行榜', '/jackpot/list',
                'jackpotList', '/jackpot/list',
                $jackpotNodeId, 2, true
            );
        }
        //排行榜导出
        $rankingsExportNodeId = $this->getNodeIdByNodeUrlPid('export', $rankingsNodeId);
        if( !$rankingsExportNodeId ) {
            $this->addPublicParentNode(
                '导出', 'export',
                'export', 'export',
                $rankingsNodeId, 1
            );
        };

        //记录
        $recordNodeId = $this->getNodeIdByNodeUrlPid('/jackpot/record', $jackpotNodeId);
        if( !$recordNodeId ){
            $recordNodeId = $this->addPublicParentNode(
                '排行榜发放记录', '/jackpot/record',
                'jackpotRecord', '/jackpot/record',
                $jackpotNodeId, 3, false
            );
        }
        //记录导出
        $recordExportNodeId = $this->getNodeIdByNodeUrlPid('export', $recordNodeId);
        if( !$recordExportNodeId ) {
            $this->addPublicParentNode(
                '导出', 'export',
                'export', 'export',
                $recordNodeId, 1
            );
        };
    }
}