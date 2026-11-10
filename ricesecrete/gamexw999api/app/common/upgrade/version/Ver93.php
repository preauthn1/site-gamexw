<?php

namespace app\common\upgrade\version;

class Ver93 extends VersionBase
{
    public function run() {
        if (!$this->existTableField("dq_agent_setting", "start_time")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '开始时间';";
            $this->execute($sql);
        }
    }
}