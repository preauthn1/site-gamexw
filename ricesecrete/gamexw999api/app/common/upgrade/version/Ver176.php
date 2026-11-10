<?php

namespace app\common\upgrade\version;

class Ver176 extends VersionBase
{

    public function run()
    {
        //领取状态
        if (!$this->existTableField("dq_task_record", "receive_status")){
            $sql = "ALTER TABLE `dq_task_record` ADD COLUMN `receive_status` tinyint(1) DEFAULT 1 COMMENT '0未领取，1已领取';";
            $this->execute($sql);
        }
        //领取状态
        if (!$this->existTableField("dq_task_stage_record", "receive_status")){
            $sql = "ALTER TABLE `dq_task_stage_record` ADD COLUMN `receive_status` tinyint(1) DEFAULT 1 COMMENT '0未领取，1已领取';";
            $this->execute($sql);
        }
    }
}