<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver25 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        $full_table_name = "dq_liansai_sc";
        $index = "idx_sctime";
        if (!$this->existTableIndex($full_table_name, $index)) {
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_sctime` (`sc_time`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_artificial_balance";
        $index = "idx_mxid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_mxid`(`mx_id`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_phone";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_phone` (`phone`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user";
        $index = "idx_tokenkey";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_tokenkey` (`usertokenkey`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_bank";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_quick";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_report";
        $index = "idx_time";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_time` (`start_time`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_api_bd";
        $index = "idx_scid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_scid` (`sc_id`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_agent_sell_log";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_liansai_zhudan";
        $index = "idx_addtime";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_addtime` (`add_time`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_withdrawal";
        $index = "idx_updatetime";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_updatetime` (`update_time`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_user_withdrawal";
        $index = "idx_addtime";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_addtime` (`add_time`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_lang_item_detail";
        $index = "idx_lang";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_lang` (`lang_id`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_lang_item_detail";
        $index = "idx_item";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD INDEX `idx_item` (`item_id`);";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_gonggao";
        $field = "agent_id";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` INT(10) DEFAULT 0 NULL COMMENT '代理ID';";
            $this->execute($sql);
        }
    }

}
