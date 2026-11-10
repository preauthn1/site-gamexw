<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver144 extends VersionBase
{

    public function run()
    {
        //三方平台服务费分值变更记录表
        if (!$this->existTable("dq_third_cost_logs")) {
            $sql = <<<SQL
CREATE TABLE `dq_third_cost_logs` (
    `tcl_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `tcl_intime` int(11) DEFAULT NULL,
    `tcl_old` decimal(20,2) DEFAULT NULL,
    `tcl_change` decimal(20,2) DEFAULT NULL,
    `tcl_new` decimal(20,2) DEFAULT NULL,
    `tcl_type` int(255) DEFAULT '0' COMMENT '0 节点 1上分 2月清',
    `tcl_opstaff` varchar(64) DEFAULT NULL COMMENT '操作人[上分比例]',
    PRIMARY KEY (`tcl_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三方平台服务费分值变更记录表';
SQL;
            $this->execute($sql);
        }

        //三方平台服务费注单增分统计临时表
        if (!$this->existTable("dq_third_cost_bettemp")) {
            $sql = <<<SQL
CREATE TABLE `dq_third_cost_bettemp` (
    `tct_gbid` int(11) NOT NULL,
    `tct_bettime` int(11) DEFAULT '0',
    `tct_betprice` decimal(20,2) DEFAULT '0.00',
    `tct_winprice` decimal(20,2) DEFAULT '0.00',
    `tct_overtime` int(11) DEFAULT NULL,
    PRIMARY KEY (`tct_gbid`),
    KEY `tct_overtime` (`tct_overtime`),
    KEY `tct_bettime` (`tct_bettime`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三方平台服务费注单增分统计临时表';
SQL;
            $this->execute($sql);
        }


        $thirdGameNodeId = $this->getNodeIdByNodeUrl('/threeGames');
        if ($thirdGameNodeId) {
            $affiliateNodeId = $this->getNodeIdByNodeUrl('/thirdcost/thirdCostList ');
            if (!$affiliateNodeId) {
                //服务费预储
                $affiliateNodeId = $this->addPublicParentNode(
                    '服务费预储',
                    '/thirdcost/thirdCostList',
                    'thirdCostList',
                    '/thirdcost/thirdCostList',
                    $thirdGameNodeId,
                    93
                );
            }
            $upscoreNodeId = $this->getNodeIdByNodeUrl('upscore');
            if (!$upscoreNodeId) {
                //服务费预储上分
                $this->addPublicParentNode(
                    '上分',
                    'upscore',
                    'upscore',
                    'upscore',
                    $affiliateNodeId
                );
            }
        }
    }
}