<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver84 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_system", "saving_zero_withdrawal")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `saving_zero_withdrawal` tinyint(1) DEFAULT 0 COMMENT '流水为零提现(0关闭，1开启，开启后用户流水为零才能提现)';";
            $this->execute($sql);
        }
    }
}