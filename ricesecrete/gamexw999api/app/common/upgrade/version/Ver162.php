<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver162 extends VersionBase {

    public function run() {

        //游戏锁定类型 1锁定 0解锁 锁定后批量状态不生效
        if (!$this->existTableField("dq_games", "g_lock")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_lock` tinyint(2) NOT NULL DEFAULT 0 COMMENT '游戏锁定状态 1锁定 0解锁 锁定后批量状态不生效';";
            $this->execute($sql);
        }
    }
}