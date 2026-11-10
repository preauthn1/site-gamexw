<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver68 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_gonggao", "jump_url")) {
            $sql = "ALTER TABLE `dq_gonggao` ADD `jump_url` varchar(100) NULL COMMENT '关联功能';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_remain_report", "type")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report` 
ADD COLUMN `type` tinyint(1) NULL DEFAULT 1 COMMENT '0 登陆，1游戏，2充值' AFTER `id`,
ADD COLUMN `remain_rate1` decimal(10, 4) NULL DEFAULT 0 AFTER `remain_stat`,
ADD COLUMN `remain_uids1` text NULL AFTER `remain_rate1`,
ADD COLUMN `remain_rate2` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids1`,
ADD COLUMN `remain_uids2` text NULL AFTER `remain_rate2`,
MODIFY COLUMN `remain_rate3` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_stat`,
ADD COLUMN `remain_rate4` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids3`,
ADD COLUMN `remain_uids4` text NULL AFTER `remain_rate4`,
MODIFY COLUMN `remain_rate5` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids3`,
MODIFY COLUMN `remain_rate7` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids5`,
MODIFY COLUMN `remain_rate15` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids7`,
MODIFY COLUMN `remain_rate30` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids15`,
MODIFY COLUMN `remain_rate60` decimal(10, 4) NULL DEFAULT 0.00 AFTER `remain_uids30`;
SQL;
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action", "user_type")) {
            $sql = "ALTER TABLE `dq_action` ADD `user_type` TINYINT(1)  NULL  DEFAULT '0'  COMMENT '0 所有用户，1新用户，2老用户';";
            $this->execute($sql);
        }
    }
}