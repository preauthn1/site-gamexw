<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver36 extends VersionBase {

    public function run() {
        
        if (!$this->existTableIndex("dq_lottery_record", "idx_uid")) {
            $sql = "ALTER TABLE `dq_lottery_record` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
    }

}
