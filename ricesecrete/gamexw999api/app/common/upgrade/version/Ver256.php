<?php

namespace app\common\upgrade\version;

use think\facade\Db;
use function Symfony\Component\Translation\t;

class Ver256 extends VersionBase
{

    public function run()
    {
        if (!$this->existTable("dq_game_water_rates")) {
            $sql = <<<SQL
CREATE TABLE `dq_game_water_rates` (
`gwr_gid` int(11) NOT NULL,
`gwr_gtid` int(11) NOT NULL,
`gwr_water_rate` decimal(20,4) DEFAULT '1.0000',
`gwr_cjwater_rate` decimal(20,4) DEFAULT '1.0000',
PRIMARY KEY (`gwr_gid`),
KEY `gwr_gtid` (`gwr_gtid`),
KEY `gwr_water_rate` (`gwr_water_rate`),
KEY `gwr_cjwater_rate` (`gwr_cjwater_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏游戏水折扣比率表，扣减流水时将按照此比率实际扣减流水';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_award_cfgs", "uac_balancetype")) {
            //增加彩金模式
            $sql = "ALTER TABLE `dq_user_award_cfgs` ADD COLUMN `uac_balancetype` int(255) NULL DEFAULT 1 COMMENT '流水要求（/赠送金额）真金0还是彩金1' AFTER `uac_game_type_ids`;";
            $this->execute($sql);
            $sql = "ALTER TABLE `dq_user_award_temp`
MODIFY COLUMN `uat_vaildwater` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '阶段有效流水（剩余）' AFTER `uat_gid`,
MODIFY COLUMN `uat_subwater` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '单次统计扣减额中间结果（避免多表更新时前字段值计算结果会影响后值）' AFTER `uat_vaildwater`,
ADD COLUMN `uat_validcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '阶段有彩金流水（剩余），真金彩金流水分开计算时使用' AFTER `uat_subwater`,
ADD COLUMN `uat_subcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '单次统计扣减额中间结果（彩金）' AFTER `uat_validcjwater`;";
            $this->execute($sql);
            $sql = "ALTER TABLE `dq_user_award_logs`
MODIFY COLUMN `ual_subwater` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '此游戏扣减（贡献）流水' AFTER `ual_gid`,
ADD COLUMN `ual_subcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '此游戏扣减（贡献）彩金流水' AFTER `ual_subwater`;";
            $this->execute($sql);
        }

        $nodeId = $this->getNodeIdByNodeUrl('/threeGames/gameCategory');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrlPid('savings', $nodeId);
            if (!$_node) {
                //流水扣减设置
                $this->addPublicParentNode(
                    '流水扣减设置',
                    'savings',
                    'savings',
                    'savings',
                    $nodeId
                );
            }
        }
        $nodeId = $this->getNodeIdByNodeUrl('/games');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrlPid('/threeGames/gameDecSavings', $nodeId);
            if (!$_node) {
                //流水扣减设置
                $this->addPublicParentNode(
                    '游戏扣减流水',
                    '/threeGames/gameDecSavings',
                    'gameDecSavingsList',
                    '/threeGames/gameDecSavings',
                    $nodeId, 3, true
                );
            }
        }
        if (!$this->existTableField("dq_action", "wallet_water_type")) {
            //钱包指定流水类型
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `wallet_water_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '指定流水钱包类型（0真金1彩金）' AFTER `water_type_ids`;";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "wallet_water_type")) {
            //钱包指定流水类型
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `wallet_water_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '指定流水钱包类型（0真金1彩金）' AFTER `water_type_ids`;";
            $this->execute($sql);
        }
    }
}