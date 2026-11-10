<?php
namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver3 extends VersionBase {
    public function run() {
        // 版本升级自动执行代码
        $full_table_name = "dq_action";
        $field = "auto_review";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` tinyint(1) DEFAULT 0 NOT NULL COMMENT '是否自动审核 0否 1是';";
            $this->execute($sql);
        }

    }
}
