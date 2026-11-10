<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver115 extends VersionBase {

    public function run() {
        // 余额统计
        if (!$this->existTable("dq_balance_statistics")) {
            $sql = <<<SQL
CREATE TABLE `dq_balance_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `balance` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='余额统计';
SQL;
            $this->execute($sql);
        }

        //余额统计
        $reportNodeId = $this->getNodeIdByNodeUrl('/report');
        if($reportNodeId) {
            $balanceStatisticsNodeId = $this->getNodeIdByNodeUrl('/report/balance');
            if (!$balanceStatisticsNodeId) {
                //余额统计
                $this->addPublicParentNode(
                    '余额统计',
                    '/report/balance',
                    'balanceStatistics',
                    '/report/balance',
                    $reportNodeId,
                    4
                );
            }
        }
    }
}