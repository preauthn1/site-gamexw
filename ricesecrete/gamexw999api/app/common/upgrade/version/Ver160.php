<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver160 extends VersionBase {

    public function run() {

        //登录日志增加设备类型
        if (!$this->existTableField("dq_task", "start_time")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '自定义开始时间';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "end_time")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '自定义结束时间';";
            $this->execute($sql);
        }
    }
}