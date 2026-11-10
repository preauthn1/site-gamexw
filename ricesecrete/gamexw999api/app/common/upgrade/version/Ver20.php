<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver20 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_action_apply", "water")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_action_apply` ADD COLUMN `water` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '流水倍数 用于提现判断';";
            $this->execute($sql);
        }

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_action", "water")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `water` int(11) DEFAULT 0 COMMENT '流水倍数';";
            $this->execute($sql);
        }
    }

}
