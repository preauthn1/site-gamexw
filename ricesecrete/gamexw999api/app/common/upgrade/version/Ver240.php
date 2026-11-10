<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver240 extends VersionBase {

    public function run() {
        // 榜单增加用户名
        if ( !$this->existTableField("dq_fake_game_set", "username") ) {
            $sql = "ALTER TABLE `dq_fake_game_set` ADD COLUMN `username` varchar(255) NULL COMMENT '用户名';";
            $this->execute($sql);
        }
    }

}
