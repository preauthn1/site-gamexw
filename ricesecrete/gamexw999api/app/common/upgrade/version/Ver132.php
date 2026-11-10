<?php

namespace app\common\upgrade\version;

class Ver132 extends VersionBase
{

    public function run()
    {
        //lineId
        if (!$this->existTableField("dq_user", "line_id")) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `line_id` varchar(255) NULL COMMENT 'lineId';";
			$this->execute($sql);
        }
    }
}