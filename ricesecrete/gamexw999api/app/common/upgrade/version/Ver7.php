<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver7 extends VersionBase {

    public function run() {
        $full_table_name = "dq_lang";
        $field = "sort";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` tinyint(3) DEFAULT 0 NOT NULL COMMENT '排序 (从大到小)';";
            $this->execute($sql);
        }

    }

}
