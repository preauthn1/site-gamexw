<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver8 extends VersionBase {

    public function run() {
        $full_table_name = "dq_liansai_zhudan";
        $field = "cancel_time";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` bigint(20) DEFAULT 0 NULL COMMENT '取消时间';";
            $this->execute($sql);
        }

        $full_table_name = "dq_liansai_zhudan";
        $field = "admin_name";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` varchar(50) DEFAULT '' NULL COMMENT '操作人';";
            $this->execute($sql);
        }
    }

}
