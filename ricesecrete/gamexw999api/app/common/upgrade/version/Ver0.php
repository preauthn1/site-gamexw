<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver0 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        
        // 判断表是否已存在
        // $this->existTable("dq_xxx");
        
        // 判断x表是否存在name字段
        // $this->existTableField("dq_xxx", "name");
        
        /*$full_table_name = "dq_system_upd";
        $field = "demo";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE {$full_table_name} ADD COLUMN `demo` VARCHAR(100) DEFAULT '' NULL COMMENT '测试';";
            $this->execute($sql);
        }*/
    }

}
