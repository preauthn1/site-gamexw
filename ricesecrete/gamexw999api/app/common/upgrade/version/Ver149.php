<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver149 extends VersionBase
{

    public function run()
    {
        //支付方式图标管理表
        if (!$this->existTable("dq_payment_type")) {
            $sql = <<<SQL
CREATE TABLE `dq_payment_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '支付方式名称',
  `pay_type` tinyint(1) DEFAULT '0' COMMENT '类型 0 银行卡 1USDT 2电子钱包 3PIX',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '图标',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付方式类型图标管理';
SQL;
            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/zhifuguanli');

        if ($zgNodeId) {
            $paymenttypeNodeId = $this->getNodeIdByNodeUrl('/paymenttype/list');
            if (empty($paymenttypeNodeId)) {
                //首页规则管理
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '支付方式图标管理',
                    '/paymenttype/list',
                    'paymenttypeList',
                    '/paymenttype/list',
                    $zgNodeId,
                    10
                );
                $this->addPublicChildrenNode($paymenttypeCNodeId);
            }
        }
    }
}