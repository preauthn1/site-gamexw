<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver182 extends VersionBase
{

    public function run()
    {
        if ($this->existTable("dq_red_packet")) {
            if (!$this->existTableField("dq_red_packet", 'validity_time')) {
                //如果表结构有问题，删除表
                $sql = <<<SQL
            DROP TABLE IF EXISTS `dq_red_packet`;
SQL;
                $this->execute($sql);
            }
        }
        //红包雨活动管理表
        if (!$this->existTable("dq_red_packet")) {
            $sql = <<<SQL
CREATE TABLE `dq_red_packet` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(50) DEFAULT '' COMMENT '语种',
  `name` varchar(50) DEFAULT '' COMMENT '活动名称',
  `model` tinyint(1) DEFAULT '1' COMMENT '1每日  2每周',
  `validity_time` varchar(30) DEFAULT '' COMMENT '活动有效参与时间',
  `condition` tinyint(4) DEFAULT '1' COMMENT '参与条件 0不限制 1. 完成首冲用户 2. 当日有存款用户(设定参与金额门槛,>=可以参加) 3. 当天/累计完成流水要求(不实现)   4.手机/银行卡号/个人信息(KYC验证),(后续添加TC的限制条件)',
  `condition_value` decimal(15,2) DEFAULT '0.00' COMMENT '金额门槛',
  `award_type` tinyint(1) DEFAULT '1' COMMENT '奖励模式 1范围  2固定金额  3输赢阶梯随机',
  `award` text CHARACTER SET utf8mb4  COMMENT '奖励模式 ',
  `award_limit` decimal(20,2) DEFAULT '0.00' COMMENT '总金额限制',
  `people_limit` int(10) DEFAULT '0' COMMENT '人数限制',
  `user_limit` int(10) DEFAULT '0' COMMENT '用户每日次/每周数限制',
  `receive_savings` decimal(20,2) DEFAULT '0.00' COMMENT '流水限制',
  `savings` int(11) DEFAULT '0' COMMENT '流水倍数',
  `agent_id` varchar(255) DEFAULT '0' COMMENT '限制代理',
  `tag_id` int(11) NOT NULL DEFAULT '0' COMMENT '标签限制（可参与）',
  `vip_id` int(11) NOT NULL DEFAULT '0' COMMENT 'VIP限制（可参与）',
  `introduce` text COMMENT '活动说明',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（1启用，0禁用）',
  `sort` int(10) DEFAULT '0' COMMENT '排序',
  `start_time` int(11) DEFAULT '0' COMMENT '活动开始时间',
  `end_time` int(11) DEFAULT '0' COMMENT '活动结束时间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='红包雨活动';
SQL;
            $this->execute($sql);
        }
        if ($this->existTable("dq_red_packet_log")) {
            if (!$this->existTableField("dq_red_packet_log", 'day')) {
                //如果表结构有问题，删除表
                $sql = <<<SQL
            DROP TABLE IF EXISTS `dq_red_packet_log`;
SQL;
                $this->execute($sql);
            }
        }
        if (!$this->existTable("dq_red_packet_log")) {
            $sql = <<<SQL
CREATE TABLE `dq_red_packet_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `rid` int(10) DEFAULT '0' COMMENT '活动id',
  `model` tinyint(1) DEFAULT '1' COMMENT '模式  1每日  2每周',
  `award_value` decimal(10,2) DEFAULT '0.00' COMMENT '获得的奖励',
  `award_type` tinyint(1) DEFAULT '1' COMMENT '1金额 2积分 ',
  `day` varchar(10) CHARACTER SET utf8mb4  DEFAULT '' COMMENT '年月日 2024-04-29',
  `week` varchar(10) CHARACTER SET utf8mb4 DEFAULT '' COMMENT '年周 2024-12',
  `month` varchar(10) DEFAULT '' COMMENT '年月 2024-04',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '领取时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `rid` (`rid`),
  KEY `award_value` (`award_value`),
  KEY `uid` (`uid`),
  KEY `ind_r_a` (`rid`,`uid`,`day`) USING BTREE,
  KEY `inde_r_aw` (`uid`,`rid`,`week`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='红包雨领取记录';
SQL;
            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/huodongguanli/redPacket');
            if (empty($NodeId)) {
                //红包雨活动管理
                $CNodeId = $this->addPublicParentNode(
                    '红包雨活动',
                    '/huodongguanli/redPacket',
                    'redPacket',
                    '/huodongguanli/redPacket',
                    $zgNodeId,
                    11
                );
                $this->addPublicChildrenMenuNode($CNodeId);
            }
            $NodeId = $this->getNodeIdByNodeUrl('/huodongguanli/redPacketAward');
            if (empty($NodeId)) {
                //红包雨活动管理
                $CNodeId = $this->addPublicParentNode(
                    '红包雨领取记录',
                    '/huodongguanli/redPacketAward',
                    'redPacketAward',
                    '/huodongguanli/redPacketAward',
                    $zgNodeId,
                    12
                );
                $ret = Db::name('admin_node')->where(['path' => 'export', 'pid' => $CNodeId])->find();
                if (empty($ret)) {
                    $in_all = [
                        'node_name' => '导出',
                        'node_url' => 'export',
                        'path' => 'export',
                        'name' => 'export',
                        'pid' => $CNodeId,
                        'sort' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                        'is_menu' => 1,
                    ];
                    Db::name('admin_node')->insert($in_all);
                }
            }
        }
    }
}