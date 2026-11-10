<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver35 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_system", "mail_phone_ip_num")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `mail_phone_ip_num`  varchar(255) NOT NULL DEFAULT '' COMMENT '短信和邮件次数限制';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user", "is_usdt")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `is_usdt` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '是否USDT用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_operating", "active_number")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `active_number` INT(10) DEFAULT 0 NOT NULL COMMENT '活跃人数'; ";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_agent_rake_back", "idx_status_time")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_agent_rake_back` ADD INDEX `idx_status_time` (`status`, `create_time`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_login", "idx_time")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_login` ADD INDEX `idx_time` (`login_time`);";
            $this->execute($sql);
        }
    }

}
