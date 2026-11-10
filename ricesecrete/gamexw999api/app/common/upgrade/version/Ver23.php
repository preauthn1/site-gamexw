<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver23 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        $full_table_name = "dq_user_login";
        $index = "idx_ip";
        if (!$this->existTableIndex($full_table_name, $index)) {
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_ip` (`login_ip`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_username";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_username`(`username`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_level";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_level` (`rec_level`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_code";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_code` (`rec_code`);";
            $this->execute($sql);
        }
    }

}
