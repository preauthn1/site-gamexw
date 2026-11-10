<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver11 extends VersionBase {
    
    public function run() {
        // 版本升级自动执行代码
        
        // 判断表是否已存在
        if(!$this->existTable("dq_game_operation")){
         $sql = "CREATE TABLE `dq_game_operation` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `operation_id` varchar(255) DEFAULT '',
                  `game_id` int(11) NOT NULL DEFAULT '0',
                  `action` varchar(255) DEFAULT '',
                  `message` text,
                  `add_time` bigint(20) DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
         $this->execute($sql);
        }

        if(!$this->existTable("dq_game_set")){
            $sql = "CREATE TABLE `dq_game_set` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `game_sys_sign` text COMMENT '系统游戏判断标志',
                      `game_name` text COMMENT '游戏平台名称',
                      `game_status` int(1) DEFAULT '0' COMMENT '0不启用1启用',
                      `game_param` text COMMENT '游戏第三方参数json',
                      `min_transfer` decimal(10,2) DEFAULT '0.00' COMMENT '转账最低金额',
                      `max_transfer` decimal(10,2) DEFAULT '0.00' COMMENT '转账最高金额',
                      `sort` int(11) DEFAULT '0' COMMENT '权重展示',
                      `rate` decimal(10,2) DEFAULT '1.00',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        if(!$this->existTable("dq_game_tx")){
            $sql = "CREATE TABLE `dq_game_tx` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` int(11) DEFAULT '0' COMMENT '用户id',
                      `ref_id` varchar(255) DEFAULT '',
                      `game_id` int(11) DEFAULT '0',
                      `bet_amount` decimal(20,2) DEFAULT '0.00',
                      `actual_amount` decimal(20,2) DEFAULT '0.00',
                      `odds` decimal(20,2) DEFAULT '0.00',
                      `add_time` bigint(20) DEFAULT NULL,
                      `tx_number` varchar(255) DEFAULT '' COMMENT '自己交易id',
                      `txid` bigint(20) DEFAULT '0' COMMENT '三方交易ID',
                      `bet_status` int(11) DEFAULT '0' COMMENT '0待确认1确认2取消3已结算',
                      `tx_type` int(11) DEFAULT '0' COMMENT '0單張注單 1串关注单2三方注单3CASHOUT',
                      `status` varchar(255) DEFAULT '',
                      `payout` decimal(20,2) DEFAULT '0.00' COMMENT '赢的奖金',
                      `update_time` bigint(20) DEFAULT '0',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        if(!$this->existTable("dq_game_user")){
            $sql = "CREATE TABLE `dq_game_user` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `game_id` int(11) DEFAULT NULL,
                      `uid` int(11) NOT NULL,
                      `member_id` varchar(255) NOT NULL DEFAULT '',
                      `username` varchar(255) DEFAULT '',
                      `amount` decimal(20,2) DEFAULT '0.00' COMMENT '游戏平台钱',
                      `add_time` bigint(20) DEFAULT NULL,
                      `online` tinyint(1) DEFAULT '0' COMMENT '游戏在线状态',
                      `min_transfer` decimal(20,2) DEFAULT '0.00',
                      `max_transfer` decimal(20,2) DEFAULT '0.00',
                      `currency` int(11) DEFAULT '0',
                      `odds_type` int(11) DEFAULT '0',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

    }

}
