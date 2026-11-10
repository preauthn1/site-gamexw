<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver66 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_system", "start_page_status")) {
            $sql = "ALTER TABLE `dq_system` ADD `start_page_status` tinyint(1) NOT NULL DEFAULT 0  COMMENT '启动页（1开启，0关闭）';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_is_del")) {
            $sql = "ALTER TABLE `dq_games` ADD `g_is_del` TINYINT(1)  NULL  DEFAULT '0'  COMMENT '是否删除';";
            $this->execute($sql);
        }
    }
}