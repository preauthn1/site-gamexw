<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver38 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_flyer_set", "api_url")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_set` ADD COLUMN `api_url`  VARCHAR(255) NOT NULL DEFAULT 0 COMMENT '接口地址';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_flyer_order", "game_name")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_order` ADD COLUMN `game_name`  VARCHAR(30) NOT NULL DEFAULT 'flyer' COMMENT '游戏名称';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_flyer_zhudan", "game_name")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_zhudan` ADD COLUMN `game_name`  VARCHAR(30) NOT NULL DEFAULT 'flyer' COMMENT '游戏名称';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_flyer_zhudan", "lebal")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_flyer_zhudan` ADD COLUMN `lebal`  tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户标签，1一般用户，2测试用户';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_lottery_config", "free_time")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `free_time` INT(10) DEFAULT 0 NULL COMMENT '免费时间间隔 单位/秒';";

            $this->execute($sql);
        }

        if (!$this->existTable("dq_game_report_operating")) {
              $sql = "CREATE TABLE `dq_game_report_operating` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `bets_number` int(11) NOT NULL DEFAULT '0' COMMENT '投注人数',
              `bets_total` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '投注总额',
              `pay_total` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '支出总额',
              `user_total_loss` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '玩家总盈亏',
              `platform_total_lose` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '平台总盈亏',
              `channel` varchar(30) NOT NULL DEFAULT '0' COMMENT '渠道',
              `game_name` varchar(30) NOT NULL DEFAULT '0' COMMENT '游戏名称',
              `add_time` int(11) NOT NULL DEFAULT '0' COMMENT '时间日期',
              `day_time` date NOT NULL COMMENT '创建日期',
              PRIMARY KEY (`id`) USING BTREE
            ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=FIXED COMMENT='小游戏报表';";

            $this->execute($sql);
        }

        if (!$this->existTable("dq_game_zhudan")) {
            $sql = "CREATE TABLE `dq_game_zhudan` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `zd_id` varchar(225) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '注单id',
              `username` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '用户名',
              `game_id` int(11) NOT NULL DEFAULT '0' COMMENT '游戏id',
              `bets_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '投注金额',
              `reward_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '收益金额',
              `before_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '投注后余额',
              `after_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '结算后余额',
              `add_time` bigint(20) NOT NULL DEFAULT '0' COMMENT '添加时间',
              `begin_time` bigint(20) NOT NULL,
              `end_time` bigint(20) NOT NULL,
              `game_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'flyer' COMMENT '游戏名称',
              `lebal` tinyint(1) NOT NULL DEFAULT '1' COMMENT '用户标签，1一般用户，2测试用户',
              PRIMARY KEY (`id`,`zd_id`),
              KEY `idx_username` (`username`) USING BTREE,
              KEY `idx_begin_time` (`begin_time`) USING BTREE,
              KEY `idx_end_time` (`end_time`) USING BTREE,
              KEY `idx_add_time` (`add_time`) USING BTREE
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8_unicode_ci;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_set", "icon")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `icon`  varchar(255) NULL COMMENT '游戏图标';";

            $this->execute($sql);
        }

    }

}
