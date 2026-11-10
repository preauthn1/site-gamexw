<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver81 extends VersionBase {

    public function run() {
        if (!$this->existTableFullName("dq_remain_report_agent")) {
            $sql = <<<SQL
CREATE TABLE `dq_remain_report_agent`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL COMMENT '代理用户id',
  `type` tinyint(1) NULL DEFAULT 1 COMMENT '0 登陆，1游戏，2充值',
  `remain_stat` varchar(10) NULL DEFAULT '',
  `recharge_user` int(10) NULL DEFAULT 0 COMMENT '当天首充人数',
  `recharge_amount` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '当天首充金额',
  `remain_rate3` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_rate1` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount1` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate2` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount2` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_uids3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount3` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate5` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_rate4` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids4` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount4` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_uids5` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount5` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate7` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids7` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount7` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate15` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids15` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount15` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate30` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids30` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount30` decimal(20, 4) NULL DEFAULT 0.0000,
  `remain_rate60` decimal(10, 4) NULL DEFAULT 0.0000,
  `remain_uids60` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
  `remain_amount60` decimal(20, 4) NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '代理留存率报表';
SQL;
            $this->execute($sql);
        }
    }
}