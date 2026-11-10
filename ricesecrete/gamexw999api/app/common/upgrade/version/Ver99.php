<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver99 extends VersionBase {

    public function run() {
        // 提现三方代付失败原因
        if (!$this->existTableField("dq_user_withdrawal", "third_err_remarks")) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `third_err_remarks` varchar(255) NULL COMMENT '三方代付失败原因';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user", "update_time")) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `update_time` INT(10) DEFAULT 0 NULL COMMENT '更新时间';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_user_distribution")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_distribution`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
  `price` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '派发金额',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '派发方式 0手动 1自动',
  `water` int(11) NOT NULL DEFAULT 0 COMMENT '流水倍数',
  `give_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '赠金领取状态(0未领取 1已领取 2已失效)',
  `give_time` int(11) NOT NULL DEFAULT 0 COMMENT '赠金领取时间',
  `add_time` int(11) NOT NULL DEFAULT 0 COMMENT '派发时间',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核人id',
  `follow_id` int(11) NOT NULL DEFAULT 0 COMMENT '回访id',
  `salesman_id` int(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态 0待审核 1通过 2自动通过 3拒绝',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_uid`(`uid`) USING BTREE
) ENGINE = MyISAM CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '用户派发赠金';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTable("dq_user_follow")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_follow`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
  `salesman_id` int(11) NOT NULL DEFAULT 0 COMMENT '电访员id',
  `role_id` int(11) NOT NULL DEFAULT 0 COMMENT '角色id',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核人员id',
  `add_time` int(11) NOT NULL DEFAULT 0 COMMENT '派发时间',
  `televised` int(11) NOT NULL DEFAULT 0 COMMENT '电访时间',
  `televised_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '电访进度(0 未安排 1待电访 2已电访 3 电访2次以上)',
  `sms_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '短信发送状态(0 未发送 1已发送)',
  `intention_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '客户意向状态 (0未评估 1低 2中 3高)',
  `sms_time` int(11) NOT NULL DEFAULT 0 COMMENT '短信发送时间',
  `remark` varchar(255) NULL DEFAULT NULL COMMENT '备注',
  `feedback` varchar(255) NULL DEFAULT NULL COMMENT '反馈内容',
  `is_connected` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否接通(0未安排 1否 2是)',
  `is_distribution` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否要领取赠金(0未处理 1否 2是)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_uid`(`uid`) USING BTREE
) ENGINE = MyISAM CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '用户回访派发记录';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTable("dq_distribution_config")) {
            $sql = <<<SQL
CREATE TABLE `dq_distribution_config`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '赠金审核类型0手动 1自动',
  `max_price` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '派发最大金额',
  `water` int(5) NOT NULL DEFAULT 0 COMMENT '默认流水倍数',
  `timeout` int(5) NOT NULL DEFAULT 0 COMMENT '待电访超时时间，单位小时',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '赠金派发配置';
SQL;
            $this->execute($sql);
            $inssetSql = "INSERT INTO `dq_distribution_config` (`id`,`type`,`max_price`,`water`,`timeout`) VALUES (1, 0, 0, 0, 0);";
            $this->execute($inssetSql);
        }
    }
}