<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver108 extends VersionBase {

    public function run() {

        // 任务代理线限制
        if (!$this->existTableField("dq_task", "receive_agent_id")){
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `receive_agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '领取代理线限制（0不限制）';";
            $this->execute($sql);
        }

        // 阶段任务代理线限制
        if (!$this->existTableField("dq_task_stage", "receive_agent_id")){
            $sql = "ALTER TABLE `dq_task_stage` ADD COLUMN `receive_agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '领取代理线限制（0不限制）';";
            $this->execute($sql);
        }

    }
}