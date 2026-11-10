<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver156 extends VersionBase {

    public function run() {

        //增加留存报表 6日统计数据字段
        if (!$this->existTableField("dq_remain_report", "remain_rate6")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report` 
ADD COLUMN `remain_rate6` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_rate5`,
ADD COLUMN `remain_uids6` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids5`,
ADD COLUMN `remain_amount6` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_amount5`;
SQL;
            $this->execute($sql);
        }
    }
}