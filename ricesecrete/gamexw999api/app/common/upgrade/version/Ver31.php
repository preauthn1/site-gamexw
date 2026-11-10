<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver31 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_system", "is_recharge")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `is_recharge` int(1) NOT NULL DEFAULT 1 COMMENT '充值开关 开启 1 关闭 0';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "is_withdraw")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `is_withdraw` int(1) NOT NULL DEFAULT 1 COMMENT '提现开关 开启 1 关闭 0';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "is_bet")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `is_bet` int(1) NOT NULL DEFAULT 1 COMMENT '投注开关 开启 1 关闭 0';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_admin_log", "ip")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_admin_log` ADD COLUMN `ip` VARCHAR(100) DEFAULT '' NULL COMMENT '操作IP';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user", "idx_rec_id2")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD INDEX `idx_rec_id2` (`rec_id2`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user", "idx_rec_id3")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD INDEX `idx_rec_id3` (`rec_id3`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_offline", "idx_addtime")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_offline` ADD INDEX `idx_addtime` (`add_time`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_offline", "idx_updatetime")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_offline` ADD INDEX `idx_updatetime` (`update_time`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_offline", "idx_order_sn")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_offline` ADD INDEX `idx_order_sn` (`order_sn`);";
            $this->execute($sql);
        }
    }

}
