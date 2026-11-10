<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver118 extends VersionBase {

    public function run() {
        // 三方集合文件名
        if (!$this->existTableField("dq_third_configs", "tc_set_code")){
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_set_code` varchar(255) NULL COMMENT '三方集合文件名' after `tc_code`;";
            $this->execute($sql);
        }
    }
}