<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver153 extends VersionBase
{

    public function run()
    {
        //首页规则管理表
        if (!$this->existTable("dq_advertising")) {
            $sql = <<<SQL
CREATE TABLE `dq_advertising` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lang` varchar(30) DEFAULT '' COMMENT '语言种类',
  `title` varchar(255) DEFAULT '' COMMENT '标题',
  `images` varchar(255) DEFAULT '' COMMENT '图片',
  `agent_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '领取代理id',
  `jump_url` varchar(255) DEFAULT '' COMMENT '关联功能',
  `condition` tinyint(2) DEFAULT '1' COMMENT '条件 1首充  2复充',
  `btn_text` varchar(50) DEFAULT '' COMMENT '按钮文字',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态 1启用 0关闭',
  `start_time` int(11) DEFAULT '0' COMMENT '开始时间',
  `end_time` int(11) DEFAULT '0' COMMENT '结束时间',
  `add_time` int(11) DEFAULT '0' COMMENT '添加时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告管理表';

SQL;
            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if ($zgNodeId) {
            $ruleindexNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/advertisingManage');
            if (empty($ruleindexNodeId)) {
                //首页规则管理
                $ruleindexCNodeId = $this->addPublicParentNode(
                    '广告列表',
                    '/huodongguanli/advertisingManage',
                    'advertisingList',
                    '/huodongguanli/advertisingManage',
                    $zgNodeId,
                    10
                );
                $this->addPublicChildrenNode($ruleindexCNodeId);
            }
        }
    }
}