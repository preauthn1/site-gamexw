<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver79 extends VersionBase {

    public function run() {
        //上个活动
        if (!$this->existTableField("dq_action", "previous_action_id")) {
            $sql = "ALTER TABLE `dq_action` ADD `previous_action_id` int(11) NOT NULL DEFAULT 0 COMMENT '前一个活动id';";
            $this->execute($sql);
        }
    }
}