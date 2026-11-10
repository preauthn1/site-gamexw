<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver76 extends VersionBase {

    public function run() {
        //奖励类型
        if (!$this->existTableField("dq_action", "everyday_person_num")) {
            $sql = "ALTER TABLE `dq_action` ADD `everyday_person_num` int(5) NOT NULL DEFAULT 0 COMMENT '每日没人限领次数（0不限制）';";
            $this->execute($sql);
        }
    }
}