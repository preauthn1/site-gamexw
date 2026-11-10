<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver225 extends VersionBase
{

    public function run()
    {
        //支付平台类型理表
        if (!$this->existTable("dq_gonggao_type")) {
            $sql = <<<SQL
CREATE TABLE `dq_gonggao_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '名称',
  `icon` varchar(255) DEFAULT '' COMMENT '图标',
  `sort` int(10) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1启用 2禁用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告类型';
;
SQL;
            $this->execute($sql);
        }

        $nodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('/gonggaotype/list');
            if (!$_node) {
                //公告类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '公告类型',
                    '/gonggaotype/list',
                    'typeList',
                    '/gonggaotype/list',
                    $nodeId,
                    2
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }

        //公告类型
        if (!$this->existTableField("dq_gonggao", "type_id")){
            $sql = "ALTER TABLE `dq_gonggao` ADD COLUMN `type_id` int(11) NOT NULL DEFAULT 0 COMMENT '类型id';";
            $this->execute($sql);
        }
    }
}