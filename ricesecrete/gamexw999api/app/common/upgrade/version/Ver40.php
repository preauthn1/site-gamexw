<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver40 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_liansai_sc_bd", "indemnity")) {
            $sql = "ALTER TABLE `dq_liansai_sc_bd` ADD COLUMN `indemnity` TINYINT(1) DEFAULT 0 NULL COMMENT '包赔 0 否 1 是';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_sc_bd", "water")) {
            $sql = "ALTER TABLE `dq_liansai_sc_bd` ADD COLUMN `water` INT(10) DEFAULT 0 NULL COMMENT '流水倍数';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_sc", "indemnity")) {
            $sql = "ALTER TABLE `dq_liansai_sc` ADD COLUMN `indemnity` TINYINT(1) DEFAULT 0 NULL COMMENT '包赔 0 未执行 1 已执行';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_zhudan", "indemnity")) {
            $sql = "ALTER TABLE `dq_liansai_zhudan` ADD COLUMN `indemnity` TINYINT(1) DEFAULT 0 NULL COMMENT '包赔 0 未执行 1 已执行';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_action_apply", "idx_uid")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_action_apply", "idx_action_id")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD INDEX `idx_action_id` (`action_id`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "phone_rule")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `phone_rule` VARCHAR(255) DEFAULT '' NULL COMMENT '手机首位规则，格式：1|2';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "phone_num_rule")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `phone_num_rule` VARCHAR(255) DEFAULT '' NULL COMMENT '手机位数规则，格式：9|10';";
            $this->execute($sql);
        }
    }

}
