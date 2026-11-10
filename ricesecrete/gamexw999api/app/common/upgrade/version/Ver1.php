<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver1 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        
        // 判断表是否已存在
        // $this->existTable("dq_xxx");
        
        // 判断x表是否存在name字段
        // $this->existTableField("dq_xxx", "name");
        
        $full_table_name = "dq_agent_rake_back";
        $field = "savings";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '储蓄金额（用于提现判断）'";
            $this->execute($sql);
        }

        $full_table_name = "dq_system";
        $field = "commission_water";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` int(5) NOT NULL DEFAULT 0 COMMENT '代理返佣提现流水倍数'";
            $this->execute($sql);
        }

    }

}
