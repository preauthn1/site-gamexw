<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver5 extends VersionBase {
    
    public function run() {
        $full_table_name = "dq_user";
        $field = "remark";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` varchar(255) NULL DEFAULT '' COMMENT '备注';";
            $this->execute($sql);
        }
    }

}
