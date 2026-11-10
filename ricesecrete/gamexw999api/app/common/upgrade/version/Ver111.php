<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver111 extends VersionBase {

    public function run() {
        // 指定游戏流水获得奖励用户记录表
        if (!$this->existTable("dq_user_award_cfgs")) {
            $sql = <<<SQL
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
SQL;
            $this->execute($sql);
        }

        // 指定奖励活动流水扣减中间表
        if (!$this->existTable("dq_user_award_logs")) {
            $sql = <<<SQL
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
SQL;
            $this->execute($sql);
        }
        // 指定活动流水统计临时表
        if (!$this->existTable("dq_user_award_temp")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_award_temp` (
  `uat_uid` int(11) NOT NULL,
  `uat_gid` int(11) NOT NULL,
  `uat_vaildwater` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '阶段有效流水（剩余）',
  `uat_subwater` decimal(10, 4) NULL DEFAULT 0 COMMENT '单次统计扣减额中间结果（避免多表更新时前字段值计算结果会影响后值）',
  UNIQUE KEY `uat_uid` (`uat_uid`,`uat_gid`),
  KEY `uat_gid` (`uat_gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='指定活动流水统计临时表';
SQL;
            $this->execute($sql);
        }
        // 流水类型
        if (!$this->existTableField("dq_action", "water_type")){
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `water_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '流水类型（0所有，1指定平台，2指定游戏）';";
            $this->execute($sql);
        }

        // 流水类型对应id字符串
        if (!$this->existTableField("dq_action", "water_type_ids")){
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `water_type_ids` varchar(255) null COMMENT '流水类型对应id字符串';";
            $this->execute($sql);
        }

        // 游戏指定类型（1指定平台，2指定游戏）
        if (!$this->existTableField("dq_user_award_cfgs", "uac_game_type")){
            $sql = "ALTER TABLE `dq_user_award_cfgs` ADD COLUMN `uac_game_type` tinyint(1) NOT NULL COMMENT '指定类型（1指定平台，2指定游戏）';";
            $this->execute($sql);
        }

        // 游戏指定类型对应id
        if (!$this->existTableField("dq_user_award_cfgs", "uac_game_type_ids")){
            $sql = "ALTER TABLE `dq_user_award_cfgs` ADD COLUMN `uac_game_type_ids` varchar(255) NULL COMMENT '指定类型对应id';";
            $this->execute($sql);
        }

    }
}