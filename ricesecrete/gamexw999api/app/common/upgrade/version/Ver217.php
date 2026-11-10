<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver217 extends VersionBase
{
    public function run()
    {
        if (!$this->existTableField("dq_ad_channel",'ac_token')) {
            $sql = "ALTER TABLE `dq_ad_channel` ADD COLUMN `ac_token` text  NULL COMMENT '像素TOKEN';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_ad_channel",'ac_test_event_code')) {
            $sql = "ALTER TABLE `dq_ad_channel` ADD COLUMN `ac_test_event_code` varchar(255) NULL DEFAULT '' COMMENT '测试代码';";
            $this->execute($sql);
        }
    }
}