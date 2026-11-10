<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver104 extends VersionBase {

    public function run() {
        // 任务表增加任务模式字段
        if ($this->existTableField("dq_user_balance_mx", "price")){
            $sql = "ALTER TABLE dq_user_balance_mx ADD INDEX(`price`);";
            $this->execute($sql);
        }
    }
}