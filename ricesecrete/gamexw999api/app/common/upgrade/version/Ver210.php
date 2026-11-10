<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver210 extends VersionBase
{
    public function run()
    {
        //logo
        if (!$this->existTableField("dq_user",'salt')) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `salt` varchar(255) NUll COMMENT '密码盐';";
            $this->execute($sql);
        }

    }
}