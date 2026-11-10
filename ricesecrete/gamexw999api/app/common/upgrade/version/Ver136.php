<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver136 extends VersionBase
{

    public function run()
    {
        //首页游戏排行管理表
        if (!$this->existTable("dq_game_rank")) {
            $sql = <<<SQL
CREATE TABLE `dq_game_rank` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_val` varchar(50) DEFAULT '' COMMENT '排行数据键值',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '排行名称',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '排行图标',
  `icon_url` varchar(255) DEFAULT '' COMMENT '排行图标地址',
  `type` tinyint(2) DEFAULT '1' COMMENT '排行类型  1 热门 2最佳 3最新 4内部',
  `sort` tinyint(3) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 开启 1 禁用 0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首页游戏排行管理';
SQL;
            $this->execute($sql);
        }
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $affiliateNodeId = $this->getNodeIdByNodeUrl('/admin/gameRank');
            if (empty($affiliateNodeId)) {
                //附属机构管理
                $cNodeId = $this->addPublicParentNode(
                    '首页游戏排行',
                    '/admin/gameRank',
                    'gameRank',
                    '/admin/gameRank',
                    $systemNodeId,
                    5
                );
                $this->addPublicChildrenNode($cNodeId);
            }

        }
        //首页菜单布局管理
        if (!$this->existTable("dq_index_menu")) {
            $sql = <<<SQL
CREATE TABLE `dq_index_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area` varchar(50) DEFAULT '' COMMENT '显示区域',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `href` varchar(255) DEFAULT '' COMMENT '跳转链接',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标',
  `icon_url` varchar(255) DEFAULT '' COMMENT '图标地址',
  `sort` tinyint(3) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 开启 1 禁用 0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首页菜单布局管理';
SQL;
            $this->execute($sql);
        }
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $affiliateNodeId = $this->getNodeIdByNodeUrl('/admin/indexMenu');
            if (empty($affiliateNodeId)) {
                //附属机构管理
                $cNodeId = $this->addPublicParentNode(
                    '首页菜单',
                    '/admin/indexMenu',
                    'indexMenu',
                    '/admin/indexMenu',
                    $systemNodeId,
                    6
                );
                $this->addPublicChildrenNode($cNodeId);
            }

        }

    }
}