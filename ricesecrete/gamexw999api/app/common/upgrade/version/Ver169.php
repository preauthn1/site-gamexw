<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver169 extends VersionBase
{

    public function run()
    {
        //活动再存优惠是否第二天后才能参与
        if (!$this->existTableField("dq_action", "is_first_tomorrow_join")) {
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `is_first_tomorrow_join` tinyint(0) NOT NULL DEFAULT 0 COMMENT '是否首充第二天后才能参与';";
            $this->execute($sql);
        }
    }
}