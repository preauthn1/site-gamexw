<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver203 extends VersionBase
{
    public function run()
    {
        if (!$this->existTable("dq_sign_news")) {
            $sql = <<<SQL
CREATE TABLE `dq_sign_news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(30) NOT NULL DEFAULT '' COMMENT '语言种类',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '关联Id',
  `news_title` varchar(255) NOT NULL DEFAULT '',
  `news_images` varchar(255) NOT NULL DEFAULT '',
  `news_intro` varchar(255) NOT NULL DEFAULT '',
  `news_content` mediumtext,
  `add_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='签到内容规则';
SQL;
            $this->execute($sql);
        }
        $actionNodeId = $this->getNodeIdByNodeUrl('/sign/list');
        if ($actionNodeId) {
            $pddTurntableNodeId = $this->getNodeIdByNodeUrl('/signNews/lists');
            if (!$pddTurntableNodeId) {
                //签到规则
                $pid = $this->addPublicParentNode(
                    '签到规则管理',
                    '/signNews/lists',
                    'signNewsLists',
                    '/signNews/lists',
                    $actionNodeId,
                    7,
                    true
                );
            }
        }
        $pddNodeId = $this->getNodeIdByNodeUrl('/sign/list');
        if ($pddNodeId) {
            $ret = Db::name('admin_node')->where('pid', $pddNodeId)->where('node_url', 'content')->find();
            if( !$ret ){
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