<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver98 extends VersionBase {

    public function run() {
        // 字段image
        if (!$this->existTableField("dq_user_level", "image")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `image` varchar(255) NULL COMMENT '等级图片';";
            $this->execute($sql);
        }
    }
}