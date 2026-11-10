<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver12 extends VersionBase {

    public function run() {
        $full_table_name = "dq_system";
        $field = "saba_check";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '沙巴娱乐，0是关闭，1是开启';";
            $this->execute($sql);
        }
    }

}
