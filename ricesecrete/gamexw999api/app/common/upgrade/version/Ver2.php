<?php
namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver2 extends VersionBase {    
    public function run() {
        // 版本升级自动执行代码        
        $full_table_name = "dq_bank_sk";
        $field = "agent_id";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` INT(10) DEFAULT 0 NOT NULL COMMENT '绑定代理ID';";
            $this->execute($sql);
        }

        $full_table_name = "dq_system";
        $field = "is_lowercs";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` tinyint(1) NOT NULL DEFAULT 0 COMMENT '推广下级是否测试账号 0否 1是'";
            $this->execute($sql);
        }
    }
}
