<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver241 extends VersionBase {

    public function run() {
        // 简洁
        if ( !$this->existTableField("dq_user_verify_config", "introduce") ) {
            $sql = "ALTER TABLE `dq_user_verify_config` ADD COLUMN `introduce` varchar(255) NULL COMMENT '简介';";
            $this->execute($sql);
        }
    }

}
