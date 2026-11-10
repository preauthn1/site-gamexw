<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver244 extends VersionBase {

    public function run() {
        //增加备注
        if ( !$this->existTableField("dq_games", "g_remarks") ) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_remarks` varchar(255) NULL COMMENT '备注';";
            $this->execute($sql);
        }
    }

}
