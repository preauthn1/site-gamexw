<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver139 extends VersionBase
{

    public function run()
    {
        //任务邀请有效用户数量
        if (!$this->existTableField("dq_task", "invite_people")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `invite_people` int(10) NOT NULL DEFAULT 0 COMMENT '邀请有效用户数量';";
            $this->execute($sql);
        }
    }
}