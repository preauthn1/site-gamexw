<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver42 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_lottery_prize", "sort")) {
            $sql = "ALTER TABLE `dq_lottery_prize` ADD COLUMN `sort` int(3) NOT NULL DEFAULT 0 COMMENT '排序 倒序';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "force_pwd")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `force_pwd` TINYINT(1) DEFAULT 0 NULL COMMENT '强制提现密码设置';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "vip_upgrade")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `vip_upgrade` TINYINT(1) DEFAULT 0 NULL COMMENT 'vip升级条件 0 充值 1 推广用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_bank_sk", "new_user")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `new_user` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '0 不限制 1 新用户 2 老用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_pay_set", "new_user")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `new_user` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '0 不限制 1 新用户 2 老用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_level", "first_user")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `first_user` INT(10) DEFAULT 0 NULL COMMENT '直推用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_level", "team_user")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `team_user` INT(10) DEFAULT 0 NULL COMMENT '团队用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_level", "is_valid")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `is_valid` TINYINT(1) DEFAULT 0 NULL COMMENT '是否有效 0 不限制 1 有效会员';";
            $this->execute($sql);
        }
        
    }

}
