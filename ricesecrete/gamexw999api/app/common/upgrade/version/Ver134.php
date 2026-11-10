<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver134 extends VersionBase
{

    public function run()
    {
        //奖励五福id
        if (!$this->existTableField("dq_task", "w_id")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `w_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖励五福id';";
            $this->execute($sql);
        }
        //奖励五福次数
        if (!$this->existTableField("dq_task", "w_count")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `w_count` int(11) NOT NULL DEFAULT 0 COMMENT '奖励五福次数';";
            $this->execute($sql);
        }
    }
}