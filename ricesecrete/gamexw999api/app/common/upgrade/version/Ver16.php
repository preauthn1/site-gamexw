<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver16 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user", "frozen_amount")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `frozen_amount`  decimal(20,2) NOT NULL DEFAULT 0 COMMENT '基金定期冻结金额';";
            $this->execute($sql);
        }

        // 判断表是否已存在
        if(!$this->existTable("dq_game_ticket")){
            $sql = "CREATE TABLE `dq_game_ticket` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `ref_id` varchar(255) DEFAULT '' COMMENT '唯一ID',
                      `away_id` bigint(20) DEFAULT '0' COMMENT '客队ID',
                      `away_name` varchar(255) DEFAULT '' COMMENT '客队名称',
                      `away_name_en` varchar(255) DEFAULT '' COMMENT '客队名称英文',
                      `away_score` int(11) DEFAULT '0' COMMENT '客队分数',
                      `ba_status` tinyint(4) DEFAULT '0' COMMENT 'baStatus',
                      `bet_choice` varchar(255) DEFAULT '' COMMENT '投注选项',
                      `bet_choice_en` varchar(255) DEFAULT '' COMMENT '投注选项英文',
                      `bet_team` varchar(255) DEFAULT '' COMMENT '投注队',
                      `bet_type` int(5) DEFAULT '0' COMMENT '投注类型',
                      `bet_type_name` varchar(255) DEFAULT '' COMMENT '投注类型名称',
                      `bet_type_name_en` varchar(255) DEFAULT '' COMMENT '投注类型名称英文',
                      `excluding` varchar(255) DEFAULT '' COMMENT 'excluding',
                      `home_id` bigint(20) DEFAULT '0' COMMENT '主队ID',
                      `home_name` varchar(255) DEFAULT '' COMMENT '主队名称',
                      `home_name_en` varchar(255) DEFAULT '' COMMENT '主队名称英文',
                      `home_score` int(11) DEFAULT NULL COMMENT '主队分数',
                      `is_live` tinyint(1) DEFAULT NULL COMMENT '是否直播',
                      `kick_off_time` bigint(20) DEFAULT NULL COMMENT '比赛开始时间',
                      `league_id` bigint(20) DEFAULT NULL COMMENT '联赛Id',
                      `league_name` varchar(255) DEFAULT '' COMMENT '联赛名称',
                      `league_name_en` varchar(255) DEFAULT '' COMMENT '联赛名称英文',
                      `match_date_time` bigint(20) DEFAULT NULL COMMENT '比赛结束时间',
                      `match_id` bigint(20) DEFAULT '0' COMMENT '比赛ID',
                      `odds` decimal(20,2) DEFAULT NULL COMMENT '赔率',
                      `odds_id` bigint(20) DEFAULT NULL COMMENT '赔率ID',
                      `odds_type` int(11) DEFAULT NULL COMMENT '赔率类型',
                      `point` varchar(255) DEFAULT '' COMMENT '头球',
                      `point2` varchar(255) DEFAULT '' COMMENT '头球2',
                      `sport_type` int(11) DEFAULT NULL COMMENT '赛事类型',
                      `sport_type_name` varchar(255) DEFAULT '' COMMENT '赛事类型名称',
                      `sport_type_name_en` varchar(255) DEFAULT '' COMMENT '赛事类型名称英文',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_tx", "bet_time")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_game_tx`
ADD COLUMN `bet_time`  bigint(20) NULL DEFAULT 0 COMMENT '下注时间',
ADD COLUMN `parlay_type`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '串关订单类型',
ADD COLUMN `detail`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '串关订单详情',
ADD COLUMN `game_name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '游戏名称',
ADD COLUMN `product_name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '产品名称',
ADD COLUMN `extra`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '额外参数',
ADD COLUMN `bet_choice`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '投注选项';";
            $this->execute($sql);
        }

    }

}
