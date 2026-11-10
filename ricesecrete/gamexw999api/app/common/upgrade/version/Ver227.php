<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver227 extends VersionBase
{

    public function run()
    {
        //最低出款金额
        if (!$this->existTableField("dq_pay_set", "min_withdrawal_amount")){
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `min_withdrawal_amount` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '最低出款金额';";
            $this->execute($sql);
        }
        //最高出款金额
        if (!$this->existTableField("dq_pay_set", "max_withdrawal_amount")){
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `max_withdrawal_amount` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '最高出款金额';";
            $this->execute($sql);
        }
    }
}