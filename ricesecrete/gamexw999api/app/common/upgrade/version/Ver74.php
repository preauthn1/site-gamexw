<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver74 extends VersionBase {

    public function run() {
        //线下USDT收款图标
        if (!$this->existTableField("dq_bank_sk", "icon")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD `icon` varchar(255) NULL COMMENT '图标';";
            $this->execute($sql);
        }

    }
}