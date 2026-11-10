<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver65 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_banner", "jump_url")) {
            $sql = "ALTER TABLE `dq_banner` ADD `jump_url` varchar(255)  NULL  COMMENT '关联功能';";
            $this->execute($sql);
        }
    }
}