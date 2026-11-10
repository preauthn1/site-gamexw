<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver166 extends VersionBase
{

    public function run()
    {
        //user_award_cfgs是否删除日志
        if (!$this->existTableField("dq_user_award_cfgs", "uac_is_del")) {
            $sql = "ALTER TABLE `dq_user_award_cfgs` ADD COLUMN `uac_is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除';";
            $this->execute($sql);
        }
    }
}