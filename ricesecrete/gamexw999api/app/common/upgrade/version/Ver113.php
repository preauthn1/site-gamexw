<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver113 extends VersionBase {

    public function run() {
        $time = time();
        // 用户彩金明细
        if (!$this->existTable("dq_user_cjbalance_mx")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_cjbalance_mx` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `add_time` bigint(20) DEFAULT '0' COMMENT '添加时间',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `old_price` decimal(20,2) DEFAULT '0.00' COMMENT '旧的金额记录',
  `new_price` decimal(20,2) DEFAULT '0.00' COMMENT '新的金额记录',
  `price` decimal(20,2) DEFAULT '0.00' COMMENT '交易金额',
  `type` int(11) DEFAULT '1' COMMENT '1是增加2减少',
  `msg` varchar(100) DEFAULT NULL COMMENT '备注',
  `source_id` int(11) DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_uid` (`uid`) USING BTREE,
  KEY `price` (`price`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户彩金明细';
SQL;
            $this->execute($sql);
        }
        // 钱包配置
        if (!$this->existTable("dq_wallet_system")) {
            $sql = <<<SQL
CREATE TABLE `dq_wallet_system`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `wallet_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '字段',
  `wallet_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT NULL COMMENT '字段对应值',
  `wallet_introduce` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT NULL COMMENT '字段描述',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `wallet_field`(`wallet_field`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci  COMMENT = '钱包配置';
SQL;
            $this->execute($sql);

            Db::name('wallet_system')->insertAll([
                [
                    'wallet_field' => 'wallet_type',
                    'wallet_value' => '0',
                    'wallet_introduce' => '钱包类型（0单钱包，1双钱包）',
                    'create_time' => $time,
                    'update_time' => $time,
                ],
                [
                    'wallet_field' => 'game_type',
                    'wallet_value' => '0',
                    'wallet_introduce' => '游戏类型（0双钱包支持，1仅支持现金钱包，2仅支持彩金钱包）',
                    'create_time' => $time,
                    'update_time' => $time,
                ],
                [
                    'wallet_field' => 'game_type_platform_ids',
                    'wallet_value' => '',
                    'wallet_introduce' => '游戏类型对应平台id',
                    'create_time' => $time,
                    'update_time' => $time,
                ],
                [
                    'wallet_field' => 'game_type_game_ids',
                    'wallet_value' => '',
                    'wallet_introduce' => '游戏类型对应游戏id',
                    'create_time' => $time,
                    'update_time' => $time,
                ],
                [
                    'wallet_field' => 'withdrawal_is_clear_cjbalance',
                    'wallet_value' => '0',
                    'wallet_introduce' => '提现是否清除彩金和彩金流水',
                    'create_time' => $time,
                    'update_time' => $time,
                ],
            ]);
        }

        // 彩金流水要求
        if (!$this->existTableField("dq_user", "cjsavings")){
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `cjsavings` decimal(20, 2) NULL DEFAULT 0 COMMENT '彩金流水要求';";
            $this->execute($sql);
        }
        // 彩金钱包余额
        if (!$this->existTableField("dq_user", "cjbalance")){
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `cjbalance` decimal(20, 2) NULL DEFAULT 0 COMMENT '彩金钱包余额';";
            $this->execute($sql);
        }
        // 是否支持彩金投注 0 双钱包支持 1仅支持现金钱包 2仅支持彩金钱包
        if (!$this->existTableField("dq_games", "g_cj")){
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_cj` tinyint(255) NULL DEFAULT 0 COMMENT '是否支持彩金投注 0 双钱包支持 1仅支持现金钱包 2仅支持彩金钱包';";
            $this->execute($sql);
        }
        // 投注金额（彩金部分）
        if (!$this->existTableField("dq_game_bets", "gb_cjprice")){
            $sql = "ALTER TABLE `dq_game_bets` ADD COLUMN `gb_cjprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）';";
            $this->execute($sql);
        }
        // 赢的金额（彩金部分）
        if (!$this->existTableField("dq_game_bets", "gb_wincjprice")){
            $sql = "ALTER TABLE `dq_game_bets` ADD COLUMN `gb_wincjprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）';";
            $this->execute($sql);
        }
        // 投注金额（彩金部分）
        if (!$this->existTableField("dq_game_bets_son", "gb_cjprice")){
            $sql = "ALTER TABLE `dq_game_bets_son` ADD COLUMN `gb_cjprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）';";
            $this->execute($sql);
        }
        // 赢的金额（彩金部分）
        if (!$this->existTableField("dq_game_bets_son", "gb_wincjprice")){
            $sql = "ALTER TABLE `dq_game_bets_son` ADD COLUMN `gb_wincjprice` decimal(10, 2) NULL DEFAULT 0 COMMENT '赢的金额（彩金部分）';";
            $this->execute($sql);
        }
        // 投注金额（包含彩金）
        if ($this->existTableField("dq_game_bets", "gb_price")){
            $sql = "ALTER TABLE `dq_game_bets` MODIFY COLUMN `gb_price` decimal(20, 2) NOT NULL COMMENT '投注金额（包含彩金）';";
            $this->execute($sql);
        }
        // 赢的金额（包含彩金）
        if ($this->existTableField("dq_game_bets", "gb_winprice")){
            $sql = "ALTER TABLE `dq_game_bets` MODIFY COLUMN `gb_winprice` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '赢的金额（包含彩金）';";
            $this->execute($sql);
        }
        // 赢的金额（包含彩金）
        if ($this->existTableField("dq_user_water", "type")){
            $sql = "ALTER TABLE `dq_user_water` MODIFY COLUMN `type` tinyint(3) NULL DEFAULT 0 COMMENT '类型：0充值，1返水，2活动奖励，3返佣，4推广奖励，5任务，7人工增加 11,12 赠送彩金,13 领取码奖励 14 VIP升级彩金 99彩金注单 100彩金注单赢 101彩金变更（奖励或扣减）';";
            $this->execute($sql);
        }
        //菜单
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if($systemNodeId) {
            $pddTurntableNodeId = $this->getNodeIdByNodeUrl('/wallet/walletSystem');
            if (!$pddTurntableNodeId) {
                //钱包配置
                $this->addPublicParentNode(
                    '钱包配置',
                    '/wallet/walletSystem',
                    'walletSystem',
                    '/wallet/walletSystem',
                    $systemNodeId,
                    5
                );
            }
        }

    }
}