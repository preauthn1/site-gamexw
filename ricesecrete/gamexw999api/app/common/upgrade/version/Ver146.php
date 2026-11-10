<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver146 extends VersionBase
{

    public function run()
    {
        //首页规则管理表
        if (!$this->existTable("dq_rule_index")) {
            $sql = <<<SQL
CREATE TABLE `dq_rule_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(30) NOT NULL DEFAULT '',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '规则类型',
  `content` longtext,
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首页规则管理';
SQL;
            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($zgNodeId) {
            $ruleindexNodeId = $this->getNodeIdByNodeUrl('/ruleindex/list');
            if (empty($ruleindexNodeId)) {
                //首页规则管理
                $ruleindexCNodeId = $this->addPublicParentNode(
                    '首页规则管理',
                    '/ruleindex/list',
                    'ruleIndexList',
                    '/ruleindex/list',
                    $zgNodeId,
                    4
                );
                $this->addPublicChildrenNode($ruleindexCNodeId);
            }
        }
    }
}