<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver63 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_currency", "currency_code")) {
            $sql = "ALTER TABLE `dq_currency` ADD COLUMN `currency_code` varchar(50) NULL COMMENT '货币字母代码';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action", "auto_apply")) {
            $sql = "ALTER TABLE `dq_action` ADD `auto_apply` TINYINT(1)  NULL  DEFAULT '0'  COMMENT '是否自动申请 0 否 1 是'  AFTER `auto_review`;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action", "mutually_exclusive_id")) {
            $sql = "ALTER TABLE `dq_action` ADD `mutually_exclusive_id` INT(11)  NULL  DEFAULT 0 COMMENT '互斥活动ID';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action_apply", "integral")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD `integral` DECIMAL(10,2)  NULL  DEFAULT '0'  COMMENT '积分奖励';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_action_apply", "integral_rate")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD `integral_rate` DECIMAL(10,2)  NULL  DEFAULT '0'  COMMENT '积分奖励比例';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_ck", "ck_integral")) {
            $sql = "ALTER TABLE `dq_user_ck` ADD `ck_integral` DECIMAL(10,2)  NULL  DEFAULT '0'  COMMENT '积分奖励';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_ck", "ck_integral_rate")) {
            $sql = "ALTER TABLE `dq_user_ck` ADD `ck_integral_rate` DECIMAL(10,2)  NULL  DEFAULT '0'  COMMENT '积分奖励比例';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_offline", "not_auto_action")) {
            $sql = "ALTER TABLE `dq_user_offline` ADD `not_auto_action` TINYINT(1)  NULL  DEFAULT '1'  COMMENT '不自动参与活动 1 不 0 自动';";
            $this->execute($sql);
        }
    }
}