<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver30 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if ($this->existTableField("dq_system", "code_check")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE dq_system modify column code_check int COMMENT '图形码验证 全关 0 全开 1 登陆 2 注册 3';";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_liansai_zhudan";
        $index = "uid";
        if (!$this->existTableIndex("dq_liansai_zhudan", "uid")) {
            if (!$this->existTableIndex("dq_liansai_zhudan", "idx_uid")) {
                // 执行增加表字段SQL
                $sql = "ALTER TABLE `dq_liansai_zhudan` ADD INDEX `idx_uid` (`uid`);";
                $this->execute($sql);
            }
        }
    }
}
