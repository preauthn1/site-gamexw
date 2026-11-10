<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver159 extends VersionBase {

    public function run() {

        //登录日志增加设备类型
        if (!$this->existTableField("dq_user_login", "device_type")) {
            $sql = "ALTER TABLE `dq_user_login` ADD COLUMN `device_type` varchar(50) COMMENT '设备类型（APP，IPHONE，WINDOWS，MAC）';";
            $this->execute($sql);
        }
    }
}