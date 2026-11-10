<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;
use think\exception\PDOException;

class Ver48 extends VersionBase {

    public function run() {        
        if (!$this->existTableField("dq_system", "auto_commission")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `auto_commission` TINYINT(1) DEFAULT 0 NULL COMMENT '自动返佣 0 否 1 是';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "auto_water")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `auto_water` TINYINT(1) DEFAULT 0 NULL COMMENT '自动返水 0 否 1 是';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_lang_items", "is_lock")) {
            $sql = "ALTER TABLE `dq_lang_items` ADD COLUMN `is_lock` TINYINT(1) DEFAULT 0 NULL;";
            $this->execute($sql);
        }
    }
}
