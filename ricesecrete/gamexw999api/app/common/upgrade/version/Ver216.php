<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver216 extends VersionBase
{
    public function run()
    {
        if (!$this->existTableField("dq_user_level",'day_max_amount')) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `day_max_amount` decimal(20,2) NOT NUll DEFAULT 0 COMMENT '用户每日总出款限制';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_prize",'water')) {
            $sql = "ALTER TABLE `dq_lottery_prize` ADD COLUMN `water` tinyint(3) NOT NUll DEFAULT 0 COMMENT '流水倍数';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_receive_code",'tag_list')) {
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `tag_list` varchar(200) NOT NUll DEFAULT '' COMMENT '用户领取完后打多个标签id';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_receive_code_record",'tag_list')) {
            $sql = "ALTER TABLE `dq_receive_code_record` ADD COLUMN `tag_list` varchar(200) NOT NUll DEFAULT '' COMMENT '用户领取完后打多个标签id';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_rebate_set")) {
            $sql = "CREATE TABLE `dq_rebate_set`  (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `ps_id` int(11) NOT NULL DEFAULT 0 COMMENT '支付渠道id',
                    `min` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最低充值',
                    `max` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最高充值',
                    `proportion` decimal(5, 2) NOT NULL DEFAULT 0 COMMENT '返利比例',
                    `create_time` int(11) NOT NULL DEFAULT 0,
                    `update_time` int(11) NOT NULL,
                    PRIMARY KEY (`id`)
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ;";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_bank_sk_rebate_set")) {
            $sql = "CREATE TABLE `dq_bank_sk_rebate_set`  (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `bank_sk_id` int(11) NOT NULL DEFAULT 0 COMMENT '支付渠道id',
                    `min` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最低充值',
                    `max` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最高充值',
                    `proportion` decimal(5, 2) NOT NULL DEFAULT 0 COMMENT '返利比例',
                    `create_time` int(11) NOT NULL DEFAULT 0,
                    `update_time` int(11) NOT NULL,
                    PRIMARY KEY (`id`)
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_offline",'rebate')) {
            $sql = "ALTER TABLE `dq_user_offline` ADD COLUMN `rebate` decimal(20, 2) NOT NUll DEFAULT 0 COMMENT '返利金额';";
            $this->execute($sql);
        }
    }
}