<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver133 extends VersionBase
{

    public function run()
    {
        //随机赠送流水倍数
        if (!$this->existTableField("dq_pdd_turntable", "give_savings")) {
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `give_savings` tinyint(5) DEFAULT 1 COMMENT '随机赠送流水倍数';";
            $this->execute($sql);
        }
    }
}