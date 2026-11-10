<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver62 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_news", "first_recharge_images")) {
            $sql = "ALTER TABLE `dq_news` ADD COLUMN `first_recharge_images` varchar(255) NULL COMMENT '首充图';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_news", "recharge_images")) {
            $sql = "ALTER TABLE `dq_news` ADD COLUMN `recharge_images` varchar(255) NULL COMMENT '再充图';";
            $this->execute($sql);
        }
    }
}