<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver19 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_user_offline", "set_content")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_offline` ADD COLUMN `set_content` TEXT NULL;";
            $this->execute($sql);
        }
    }

}
