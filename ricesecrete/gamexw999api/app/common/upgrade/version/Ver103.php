<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver103 extends VersionBase {

    public function run() {
        // 任务表增加任务模式字段
        if (!$this->existTableField("dq_user_withdrawal", "user_remarks")){
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `user_remarks` varchar(255) COMMENT '用户备注';";
            $this->execute($sql);
        }
    }
}