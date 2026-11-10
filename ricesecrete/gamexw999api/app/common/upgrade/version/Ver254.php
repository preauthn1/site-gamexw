<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver254 extends VersionBase {

    public function run() {
        //中奖手续费
        if (!$this->existTableField("dq_third_configs", "tc_winning_fee")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_winning_fee` tinyint(3) NULL COMMENT '中奖手续费';";
            $this->execute($sql);
        }

        // 中奖手续费记录
        if (!$this->existTable("dq_game_bet_fee")) {
            $sql = <<<SQL
CREATE TABLE `dq_game_bet_fee`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `tcid` int(11) NOT NULL DEFAULT 0 COMMENT '平台id',
  `gid` int(11) NOT NULL DEFAULT 0 COMMENT '游戏id',
  `gbid` int(11) NOT NULL DEFAULT 0 COMMENT '注单id',
  `gbsn` varchar(500)  NULL COMMENT '注单单号',
  `old_price` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '原派彩金额',
  `fee_ratio` tinyint(3) NOT NULL DEFAULT 0 COMMENT '平台手续费比例',
  `fee` decimal(20, 4) NOT NULL DEFAULT 0 COMMENT '平台手续费',
  `now_price` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '现派彩金额',
  `remarks` varchar(255)  NULL DEFAULT NULL COMMENT '备注',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT='中奖手续费记录';
SQL;
            $this->execute($sql);
        }


        //配置菜单
        $gameNodeId = $this->getNodeIdByNodeUrl('/games');
        if( $gameNodeId ){
            $_nodeId = $this->getNodeIdByNodeUrl('/threeGames/betFeeList');
            if( !$_nodeId ){
                $gameBetFeeNodeId = $this->addPublicParentNode(
                    '中奖手续费', '/threeGames/betFeeList',
                    'betFeeList', '/threeGames/betFeeList',
                    $gameNodeId, 2, false,0
                );
                //导出
                $this->addPublicParentNode(
                    '导出', 'export',
                    'export', 'export',
                    $gameBetFeeNodeId
                );
            }
        }

        //中奖手续费
        if (!$this->existTableField("dq_operating", "winprice_fee")) {
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `winprice_fee` decimal(20, 4) NULL NULL DEFAULT 0.0000 COMMENT '中奖手续费';";
            $this->execute($sql);
        }
    }

}
