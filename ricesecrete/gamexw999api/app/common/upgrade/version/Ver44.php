<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver44 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user_login", "device")) {
            $sql = "ALTER TABLE `dq_user_login` ADD COLUMN `device` TEXT NULL;";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_set", "tc_id")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `tc_id` INT(10) DEFAULT 0 NOT NULL COMMENT '平台ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_artificial_balance", "uid")) {
            $sql = "ALTER TABLE `dq_artificial_balance` ADD COLUMN `uid` INT(10) DEFAULT 0 NOT NULL COMMENT '用户ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_uid_status")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD INDEX `idx_uid_status` (`uid`, `status`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_liansai_zhudan", "idx_scid_status")) {
            $sql = "ALTER TABLE `dq_liansai_zhudan` ADD INDEX `idx_scid_status` (`sc_id`, `status`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_uid")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_third_configs", "sort")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `sort` INT(10) DEFAULT 0 NOT NULL COMMENT '排序';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_third_configs", "icon")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `icon` VARCHAR(255) DEFAULT '' NOT NULL COMMENT '平台logo';";
            $this->execute($sql);
        }

        
        if (!$this->existTableField("dq_artificial_balance", "msg")) {
            $sql = "ALTER TABLE `dq_artificial_balance` ADD COLUMN `msg` VARCHAR(255) DEFAULT '' NULL COMMENT '备注';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_set", "is_hot")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `is_hot` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '是否热门';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_bank_sk", "start_level_id")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `start_level_id` INT(10) DEFAULT 0 NULL COMMENT 'vip等级开始ID 0 不限制';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_bank_sk", "end_level_id")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `end_level_id` INT(10) DEFAULT 0 NULL COMMENT 'vip等级结束ID 0 不限制';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_pay_set", "start_level_id")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `start_level_id` INT(10) DEFAULT 0 NULL COMMENT 'vip等级开始ID 0 不限制';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_pay_set", "end_level_id")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `end_level_id` INT(10) DEFAULT 0 NULL COMMENT 'vip等级结束ID 0 不限制';";
            $this->execute($sql);
        }
        
    }

}
