<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver126 extends VersionBase {

    public function run() {
        //代理线id
        if (!$this->existTableField("dq_banner", "agent_id")){
            $sql = "ALTER TABLE `dq_banner` ADD COLUMN `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理线id';";
            $this->execute($sql);
        }
    }
}