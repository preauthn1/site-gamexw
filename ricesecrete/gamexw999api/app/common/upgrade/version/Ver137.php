<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver137 extends VersionBase
{

    public function run()
    {
        //集合
        if (!$this->existTableField("dq_third_configs", "tc_set_code")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_set_code` varchar(255) NULL COMMENT '三方集合名';";
            $this->execute($sql);
        }
    }
}