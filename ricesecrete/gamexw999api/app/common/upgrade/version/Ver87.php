<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver87 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user_msg", "recharge_first")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `recharge_first` tinyint(1) NULL COMMENT '充值是否首充';";
            $this->execute($sql);
        }
    }
}