<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver18 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_bank_sk_set", "pay_type")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_bank_sk_set` ADD COLUMN `pay_type`  varchar(30) NOT NULL DEFAULT '' COMMENT '充值类型';";
            $this->execute($sql);
        }
    }

}
