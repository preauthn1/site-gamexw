<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver245 extends VersionBase {

    public function run() {
        //扣除的彩金余额
        if ( !$this->existTableField("dq_user_withdrawal", "dec_cjbalance") ) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `dec_cjbalance` decimal(20, 4) NOT NULL DEFAULT '0.0000' COMMENT '扣除的彩金余额';";
            $this->execute($sql);
        }
        //扣除的彩金流水
        if ( !$this->existTableField("dq_user_withdrawal", "dec_cjsavings") ) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `dec_cjsavings` decimal(20, 4) NOT NULL DEFAULT '0.0000' COMMENT '扣除的彩金流水';";
            $this->execute($sql);
        }
    }

}
