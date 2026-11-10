<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver143 extends VersionBase
{

    public function run()
    {
        //集合
        if (!$this->existTableField("dq_affiliate", "lang")) {
            $sql = "ALTER TABLE `dq_affiliate` ADD COLUMN `lang` varchar(30) DEFAULT '' COMMENT '语言种类';";
            $this->execute($sql);
        }
    }
}