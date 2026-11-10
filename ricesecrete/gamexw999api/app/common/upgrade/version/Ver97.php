<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver97 extends VersionBase {

    public function run() {
        // 运营总报表
        if (!$this->existTableField("dq_operating", "task_reward")) {
            $sql = <<<SQL
ALTER TABLE `dq_operating` 
ADD COLUMN `win_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '派彩总额',
ADD COLUMN `lottery` decimal(20, 2) NULL DEFAULT 0 COMMENT '免费转盘奖励',
ADD COLUMN `task_reward` decimal(20, 2) NULL DEFAULT 0 COMMENT '任务中心奖励',
ADD COLUMN `level_reward` decimal(20, 2) NULL DEFAULT 0 COMMENT 'VIP等级周月奖励'
SQL;
            $this->execute($sql);
        }
    }
}