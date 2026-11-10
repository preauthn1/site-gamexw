<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver145 extends VersionBase
{

    public function run()
    {
        //语种类型
        if (!$this->existTableField("dq_index_menu", "lang")) {
            $sql = "ALTER TABLE `dq_index_menu` ADD COLUMN `lang` varchar(30) DEFAULT '' COMMENT '语言种类';";
            $this->execute($sql);
        }
    }
}