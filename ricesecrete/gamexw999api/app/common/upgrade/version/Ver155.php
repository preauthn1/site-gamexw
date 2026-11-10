<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver155 extends VersionBase
{

    public function run()
    {
        //默认头像管理表
        if (!$this->existTable("dq_default_head")) {
            $sql = <<<SQL
CREATE TABLE `dq_default_head` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '头像名称',
  `url` varchar(255) DEFAULT '' COMMENT '头像地址',
  `sort` int(5) DEFAULT '1' COMMENT '排序',
  `status` int(11) DEFAULT '1' COMMENT '1开启 0关闭',
  `add_time` int(11) DEFAULT '0' COMMENT '添加时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 

SQL;
            $this->execute($sql);
        }

        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $pNodeId = $this->getNodeIdByNodeUrl('/admin/defaultHeadImg');
            if (empty($pNodeId)) {
                //默认头像管理
                $nCNodeId = $this->addPublicParentNode(
                    '默认头像管理',
                    '/admin/defaultHeadImg',
                    'defaultHeadImg',
                    '/admin/defaultHeadImg',
                    $systemNodeId,
                    10
                );
                $this->addPublicChildrenNode($nCNodeId);
            }

        }
    }
}