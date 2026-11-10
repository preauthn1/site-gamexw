<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver260 extends VersionBase {

    public function run() {
        //今日总注册和且首充用户数
        if (!$this->existTableField("dq_operating", "day_register_first_recharge")) {
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `day_register_first_recharge` int(11) NULL NULL DEFAULT 0 COMMENT '今日总注册和且首充用户数';";
            $this->execute($sql);
        }
    }

}
