<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver221 extends VersionBase
{
    public function run()
    {
        //任务指定流水
        if (!$this->existTableField("dq_wufu",'register_give_count')) {
            $sql = "ALTER TABLE `dq_wufu` ADD COLUMN `register_give_count` tinyint(5) NOT NULL DEFAULT 1 COMMENT '注册赠送的刮卡次数';";
            $this->execute($sql);
        }
    }
}