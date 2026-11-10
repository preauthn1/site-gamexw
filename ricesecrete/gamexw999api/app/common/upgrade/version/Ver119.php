<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver119 extends VersionBase {

    public function run() {
        // 预测页
        if (!$this->existTable("dq_forecast")) {
            $sql = <<<SQL
CREATE TABLE `dq_forecast` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '语言',
  `banner_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT NULL COMMENT 'banner',
  `introduce` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '介绍',
  `invite_code` int(10) NULL DEFAULT NULL COMMENT '邀请码',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `lang`(`lang`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='预测页';
SQL;
            $this->execute($sql);
        }
        // 预测游戏
        if (!$this->existTable("dq_forecast_game")) {
            $sql = <<<SQL
CREATE TABLE `dq_forecast_game` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `forecast_id` int(11) NOT NULL DEFAULT 0 COMMENT '预测id',
  `tcid` int(11) NOT NULL DEFAULT 0 COMMENT '平台id',
  `gid` int(11) NOT NULL DEFAULT 0 COMMENT '游戏id',
  `time` int(10) NOT NULL DEFAULT 0 COMMENT '时间周期（单位：分钟）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',                            
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `forecast_id`(`forecast_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='预测对应游戏';
SQL;
            $this->execute($sql);
        }
        //菜单添加
        $thirdGameNodeId = $this->getNodeIdByNodeUrl('/threeGames');
        if($thirdGameNodeId){

            $forecastNodeId = $this->getNodeIdByNodeUrl('/threeGames/forecast');
            if( !$forecastNodeId ){
                //预测页
                $forecastNodeId = $this->addPublicParentNode(
                    '预测页',
                    '/threeGames/forecast',
                    'forecast',
                    '/threeGames/forecast',
                    $thirdGameNodeId               ,
                    92,
                    true
                );
            }
            $forecastGameNodeId = $this->getNodeIdByNodeUrl('/threeGames/forecastGame');
            if( !$forecastGameNodeId ){
                //预测游戏
                $this->addPublicParentNode(
                    '预测游戏',
                    '/threeGames/forecastGame',
                    'forecastGame',
                    '/threeGames/forecastGame',
                    $forecastNodeId,
                    0,
                    true
                );
            }
        }
        // 排序
        if (!$this->existTableField("dq_forecast_game", "sort")){
            $sql = "ALTER TABLE `dq_forecast_game` ADD COLUMN `sort` tinyint(10) NOT NULL DEFAULT 0 COMMENT '排序';";
            $this->execute($sql);
        }
    }
}