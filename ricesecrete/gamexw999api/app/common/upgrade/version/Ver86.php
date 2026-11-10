<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver86 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user", "login_ip_count")) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `login_ip_count` int(5) DEFAULT 1 COMMENT '登录IP数量';";
            $this->execute($sql);
        }

        // 优化综合报表，增加有效投注字段
        if (!$this->existTableField("dq_third_stats_global", "su_valid_bet")) {
            $sql = "ALTER TABLE `dq_third_stats_global` ADD COLUMN `su_valid_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注' AFTER `su_bet`;";
            $this->execute($sql);
            $sql = "UPDATE `dq_third_stats_global` SET su_valid_bet=su_bet WHERE su_valid_bet=0 AND su_bet > 0;";
            $this->execute($sql);
            $sql = "UPDATE `dq_third_stats_global` set su_syswin=su_valid_bet-su_win;";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_global_temp", "su_valid_bet")) {
            $sql = "ALTER TABLE `dq_third_stats_global_temp` ADD COLUMN `su_valid_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注' AFTER `su_bet`;";
            $this->execute($sql);
        }

        // 添加系统设置参数
        $this->addSysSets('FySavingRateOut', "外部代理返佣要求流水倍数", 0, 'uint');
        $this->addSysSets('BackSavingRate', "游戏返水要求流水倍数", 0, 'uint');


        // 记录用户流水明细
        if (!$this->existTable("dq_user_water")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_water`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL DEFAULT 0 COMMENT '用户ID',
  `amount` decimal(20, 2) NULL COMMENT '原始金额',
  `multiple` int(11) NULL DEFAULT 1 COMMENT '流水倍数',
  `water` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '增加流水金额，负数为减少流水',
  `origin_id` int NULL DEFAULT 0 COMMENT '来源记录ID',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型：0充值，1返水，2活动奖励，3返佣，4推广奖励，5任务，其他自定',
  `remark` varchar(255) NULL DEFAULT '' COMMENT '备注',
  `create_time` bigint NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  INDEX `idx_uid`(`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '流水要求明细表';
SQL;
            $this->execute($sql);
        }
    }
}