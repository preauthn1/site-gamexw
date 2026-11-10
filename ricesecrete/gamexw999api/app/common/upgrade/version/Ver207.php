<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver207 extends VersionBase
{
    public function run()
    {
        //logo
        if (!$this->existTableField("dq_games",'g_show')) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示（1显示）';";
            $this->execute($sql);
        }

    }
}