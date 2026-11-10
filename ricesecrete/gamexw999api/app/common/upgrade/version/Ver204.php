<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver204 extends VersionBase
{
    public function run()
    {
        //logo
        if (!$this->existTableField("dq_index_menu",'logo')) {
            $sql = "ALTER TABLE `dq_index_menu` ADD COLUMN `logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'logo';";
            $this->execute($sql);
        }
    }
}