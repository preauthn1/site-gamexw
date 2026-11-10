<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver242 extends VersionBase {

    public function run() {

        if ( !$this->existTableField("dq_user_bank", "branches") ) {
            $sql = "ALTER TABLE `dq_user_bank` 
ADD COLUMN `branches` varchar(100) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '分行名称',
ADD COLUMN `branches_code` varchar(100) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '分行代码';";
            $this->execute($sql);
        }

        if ( !$this->existTableField("dq_pay_set", "pay_off_field") ) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `pay_off_field` mediumtext CHARACTER SET utf8mb4 NULL COMMENT '充值额外字段' AFTER `max_withdrawal_amount`;";
            $this->execute($sql);
        }

        if ( $this->existTableField("dq_user_offline", "apply_price") ) {
            $sql = "ALTER TABLE `dq_user_offline` 
MODIFY COLUMN `apply_price` decimal(20, 6) NOT NULL DEFAULT 0.0000,
MODIFY COLUMN `arrival_price` decimal(20, 6) NOT NULL DEFAULT 0.0000";
            $this->execute($sql);
        }
    }

}
