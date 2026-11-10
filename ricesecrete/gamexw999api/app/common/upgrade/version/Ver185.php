<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver185 extends VersionBase
{

    public function run()
    {
        //导出密码
        if (!$this->existTableField("dq_export_reports", "er_password")) {
            $sql = "ALTER TABLE `dq_export_reports` ADD COLUMN `er_password` varchar(255) COMMENT '解压密码';";
            $this->execute($sql);
        }
    }
}