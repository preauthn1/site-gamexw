<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver129 extends VersionBase
{

    public function run()
    {
        //是否开启首次提现认证，1开启，0关闭
        if (!$this->existTableField("dq_system", "withdrawal_status")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `withdrawal_status` tinyint(1) DEFAULT 0 COMMENT '是否开启首次提现认证，1开启，0关闭';";
            $this->execute($sql);
        }
    }
}