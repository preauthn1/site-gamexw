<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver222 extends VersionBase
{
    public function run()
    {
        //是否新标签页打开
        if (!$this->existTableField("dq_third_configs",'is_new_tab')) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `is_new_tab` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否新标签页打开（0否，1是）';";
            $this->execute($sql);
        }
    }
}