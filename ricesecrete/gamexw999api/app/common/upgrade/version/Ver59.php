<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver59 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_system", "hot_quick_recharge")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `hot_quick_recharge` varchar(255) NULL COMMENT '热门银行充值金额设置';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_pay_set", "is_hot")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `is_hot` tinyint(1) NULL COMMENT '是否热门（1是，0否）';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_bank_sk", "is_hot")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `is_hot` tinyint(1) NULL COMMENT '是否热门（1是，0否）';";
            $this->execute($sql);
        }

    }
}