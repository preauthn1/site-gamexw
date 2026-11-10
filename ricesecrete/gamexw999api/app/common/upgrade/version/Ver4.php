<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver4 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        $full_table_name = "dq_user_msg";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid`(`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_balance_mx";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid`(`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_report";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid`(`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_rec_id";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_rec_id`(`rec_id`);";
            $this->execute($sql);
        }
        
        $index = "idx_tree";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_tree`(`tree`);";
            $this->execute($sql);
            $sql = "ALTER TABLE `{$full_table_name}` MODIFY COLUMN `tree` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '关系链'";
            $this->execute($sql);
        }
    }

}
