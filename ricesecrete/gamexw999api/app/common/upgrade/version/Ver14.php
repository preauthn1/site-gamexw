<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver14 extends VersionBase {
    
    public function run() {

        if (!$this->existTableField("dq_system", "withdrawal_check_type")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `withdrawal_check_type`  tinyint(1) NOT NULL DEFAULT 4 COMMENT '提现认证类型，1手机，2邮箱，3，都开启，任意验证。4关闭';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "withdrawal_status")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `withdrawal_status`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启提现认证，1开启，0关闭';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "bind_card_status")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `bind_card_status`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑卡认证，1开启，0关闭';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "card_number")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `card_number`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑卡数量';";
            $this->execute($sql);
        }
    }

}
