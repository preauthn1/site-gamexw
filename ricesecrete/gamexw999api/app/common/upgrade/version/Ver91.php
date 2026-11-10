<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver91 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_agent_sell_log", "received_people_num")) {
            $sql = "ALTER TABLE `dq_agent_sell_log` ADD COLUMN `received_people_num` int(10) NOT NULL DEFAULT 0 COMMENT '已领取奖励的人数';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_agent_setting", "reward_type")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `reward_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '任务完成活跃度';";
            $this->execute($sql);
        }

    }
}