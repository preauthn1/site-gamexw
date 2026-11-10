<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver6 extends VersionBase {
    
    public function run() {
        $full_table_name = "dq_user_report";
        $field = "order_cancel";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` DECIMAL(20,2) DEFAULT 0.00 NOT NULL COMMENT '注单取消金额';";
            $this->execute($sql);
        }
        
         $field = "order_cancel_num";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` INT(10) DEFAULT 0 NOT NULL COMMENT '注单取消量';";
            $this->execute($sql);
        }
    }

}
