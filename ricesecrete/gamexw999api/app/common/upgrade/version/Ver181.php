<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver181 extends VersionBase
{

    public function run()
    {
        //支付平台类型理表
        if (!$this->existTable("dq_pay_type")) {
            $sql = <<<SQL
CREATE TABLE `dq_pay_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '支付类型名称',
  `icon` varchar(255) DEFAULT '' COMMENT '图标',
  `sort` int(10) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1启用 2禁用',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT COMMENT='支付方式类型';
;
SQL;
            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/zhifuguanli');
        if(empty($zgNodeId)){
            $zgNodeId = $this->getNodeIdByNodeUrlName('/banksk/list','zhifuqudaoguanli');
        }
        if ($zgNodeId) {
            $paymenttypeNodeId = $this->getNodeIdByNodeUrl('/paytype/list');
            if (empty($paymenttypeNodeId)) {
                //首页规则管理
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '支付类型',
                    '/paytype/list',
                    'paytypeList',
                    '/paytype/list',
                    $zgNodeId,
                    11
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }

        //支付平台类型字段
        if (!$this->existTableField("dq_pay_set", "pay_types")){
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `pay_types` int(10) DEFAULT 0 COMMENT '平台渠道类型';";
            $this->execute($sql);
        }
        //支付平台类型字段
        if (!$this->existTableField("dq_bank_sk", "pay_types")){
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `pay_types` int(10) DEFAULT 0 COMMENT '平台渠道类型';";
            $this->execute($sql);
        }
        //支付平台是否热门字段
        if (!$this->existTableField("dq_pay_type", "is_hot")){
            $sql = "ALTER TABLE `dq_pay_type` ADD COLUMN `is_hot` int(1) DEFAULT 0 COMMENT '是否热门 0否 1是';";
            $this->execute($sql);
        }
    }
}