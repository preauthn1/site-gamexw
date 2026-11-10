<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver211 extends VersionBase
{
    public function run()
    {
        //任务状态
        if (!$this->existTableField("dq_task",'status')) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `status` tinyint(1) NOT NUll DEFAULT 1 COMMENT '状态（1开启，0关闭）';";
            $this->execute($sql);
        }

    }
}