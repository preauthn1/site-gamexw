<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver37 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user", "flyer_money")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `flyer_money`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '飞行员上分余额';";
            $this->execute($sql);
        }
        
        if (!$this->existTable("dq_flyer_set")) {
            // 执行增加表字段SQL
            $sql = "CREATE TABLE `dq_flyer_set` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道名称',
                      `status` int(1) NOT NULL DEFAULT '0' COMMENT '0不启用1启用',
                      `channel` varchar(32) NOT NULL DEFAULT '' COMMENT '渠道',
                      `key` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道key',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }
        
        if (!$this->existTable("dq_flyer_order")) {
            // 执行增加表字段SQL
            $sql = "CREATE TABLE `dq_flyer_order` (
                    `id`  int(11) NOT NULL AUTO_INCREMENT ,
                    `type`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '1上分，2下分' ,
                    `uid`  int(11) NOT NULL DEFAULT 0 COMMENT '用户id' ,
                    `username`  varchar(30) NOT NULL DEFAULT 0 COMMENT '用户名' ,
                    `lebal`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户标签1一般用户2测试用户' ,
                    `channel`  varchar(30) NOT NULL DEFAULT 0 COMMENT '渠道' ,
                    `order_id`  varchar(64) NOT NULL DEFAULT 0 COMMENT '订单id' ,
                    `money`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '所上分数' ,
                    `is_full`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否全额转出，1：是，0：否' ,
                    `add_time`  bigint(20) NOT NULL DEFAULT 0 COMMENT '添加时间' ,
                    PRIMARY KEY (`id`),
                    INDEX `idx_type` (`type`) USING BTREE ,
                    INDEX `idx_uid` (`uid`) USING BTREE,
                    INDEX `idx_username` (`username`) USING BTREE ,
                    INDEX `idx_order_id` (`order_id`) USING BTREE ,
                    INDEX `idx_lebal` (`lebal`) USING BTREE,
                    INDEX `idx_add_time` (`add_time`) USING BTREE
                    );";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_flyer_zhudan")) {
            // 执行增加表字段SQL
            $sql = "CREATE TABLE `dq_flyer_zhudan` (
                    `id`  bigint(20) NOT NULL AUTO_INCREMENT ,
                    `zd_id`  bigint(20) NOT NULL DEFAULT 0 COMMENT '注单id' ,
                    `username`  varchar(30) NOT NULL DEFAULT '0' COMMENT '用户名' ,
                    `game_id`  int(11) NOT NULL DEFAULT 0 COMMENT '游戏id' ,
                    `bets_coin`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '投注金额' ,
                    `reward_coin`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '收益金额' ,
                    `before_coin`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '投注后余额' ,
                    `after_coin`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '结算后余额' ,
                    `add_time`  bigint(20) NOT NULL DEFAULT 0 COMMENT '添加时间' ,
                    `begin_time`  bigint(20) NOT NULL ,
                    `end_time`  bigint(20) NOT NULL ,
                    PRIMARY KEY (`id`),
                    INDEX `idx_username` (`username`) USING BTREE ,
                    INDEX `idx_begin_time` (`begin_time`) USING BTREE ,
                    INDEX `idx_end_time` (`end_time`) USING BTREE,
                    INDEX `idx_add_time` (`add_time`) USING BTREE
                    );";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_flyer_set", "exchange_rate")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_set` ADD COLUMN `exchange_rate`  decimal(10,2) NOT NULL DEFAULT 1 COMMENT '汇率';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_flyer_set", "currency")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_set` ADD COLUMN `currency`  varchar(10) NOT NULL DEFAULT 'USDT' COMMENT '币种';";
            $this->execute($sql);
        }
    }

}
