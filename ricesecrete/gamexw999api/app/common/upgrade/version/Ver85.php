<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver85 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_agent_setting", "savings")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `savings` tinyint(5) DEFAULT 0 COMMENT '流水倍数';";
            $this->execute($sql);
        }
    }
}