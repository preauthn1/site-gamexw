<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver202 extends VersionBase
{
    public function run()
    {
        if (!$this->existTable("dq_pdd_news")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(30) NOT NULL DEFAULT '' COMMENT '语言种类',
  `pdd_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联pdd转盘Id',
  `news_title` varchar(255) NOT NULL DEFAULT '',
  `news_images` varchar(255) NOT NULL DEFAULT '',
  `news_intro` varchar(255) NOT NULL DEFAULT '',
  `news_content` mediumtext,
  `add_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='pdd转盘内容规则';
SQL;
            $this->execute($sql);
        }
        $actionNodeId = $this->getNodeIdByNodeUrl('/pdd/pddList');
        if ($actionNodeId) {
            $pddTurntableNodeId = $this->getNodeIdByNodeUrl('/pddNews/lists');
            if (!$pddTurntableNodeId) {
                //pdd转盘
                $pid = $this->addPublicParentNode(
                    'pdd转盘内容',
                    '/pddNews/lists',
                    'pddNewsLists',
                    '/pddNews/lists',
                    $actionNodeId,
                    7,
                    true
                );
            }
        }
        $pddNodeId = $this->getNodeIdByNodeUrl('/pdd/pddList');
        if ($pddNodeId) {
            $ret = Db::name('admin_node')->where('pid', $pddNodeId)->where('node_url', 'content')->find();
            if( !$ret ) {
                //设置内容
                $pid = $this->addPublicParentNode(
                    '设置内容',
                    'content',
                    'content',
                    'content',
                    $actionNodeId,
                    0,
                    true
                );
            }
        }
    }
}