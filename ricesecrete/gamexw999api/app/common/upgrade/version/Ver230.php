<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver230 extends VersionBase
{

    public function run()
    {
        //流水是否包含本金
        if (!$this->existTableField("dq_action", "savings_is_include_fee")){
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `savings_is_include_fee` tinyint(1) NOT NULL DEFAULT 0 COMMENT '流水是否包含本金（1包含，0不包含）';";
            $this->execute($sql);
        }
    }
}