<?php

namespace app\common\upgrade\version;

class Ver141 extends VersionBase
{

    public function run()
    {
        //分享平台管理表
        if (!$this->existTable("dq_affiliate_platform")) {
            $sql = <<<SQL
CREATE TABLE `dq_affiliate_platform` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '平台名称',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '平台图标',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '平台链接',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分享平台';
SQL;
            $this->execute($sql);
        }
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $affiliateNodeId = $this->getNodeIdByNodeUrl('/admin/affiliatePlatform');
            if (empty($affiliateNodeId)) {
                //分享平台管理
                $affiliateCNodeId = $this->addPublicParentNode(
                    '分享平台管理',
                    '/admin/affiliatePlatform',
                    'affiliatePlatform',
                    '/admin/affiliatePlatform',
                    $systemNodeId,
                    4
                );
                $this->addPublicChildrenNode($affiliateCNodeId);
            }
        }
    }
}