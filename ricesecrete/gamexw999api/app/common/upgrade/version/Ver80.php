<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver80 extends VersionBase {

    public function run() {
        $pid = $this->getNodeIdByName("活动管理");
        //领取码列表
        $this->addPublicParentNode(
            '领取码管理',
            '/receiveCode/receiveCodeList',
            'receiveCodeList',
            '/receiveCode/receiveCodeList',
            $pid,
            10,
            true
        );
        //领取记录
        $this->addPublicParentNode(
            '领取记录',
            '/receiveCode/receiveCodeRecord',
            'receiveCodeRecord',
            '/receiveCode/receiveCodeRecord',
            $pid,
            15
        );
        //生成领取码表和领取记录表
        if (!$this->existTableFullName("dq_receive_code")) {
            $sql = <<<SQL
CREATE TABLE `dq_receive_code`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `code` varchar(255) NOT NULL COMMENT '领取码',
  `lang` varchar(50) NULL DEFAULT NULL COMMENT '语言',
  `title` varchar(100) NULL DEFAULT NULL COMMENT '标题',
  `image` text NULL COMMENT '封面',
  `introduce` varchar(255)  NULL DEFAULT NULL COMMENT '简介',
  `content` text NULL COMMENT '内容',
  `receive_num` int(10) NOT NULL DEFAULT 1 COMMENT '可领取次数',
  `everyone_receive_num` int(10) NOT NULL DEFAULT 1 COMMENT '每人限领取数量',
  `level_id` int(11) NOT NULL DEFAULT 0 COMMENT '可领取等级',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '可领取代理线',
  `tag_id` int(11) NOT NULL DEFAULT 0 COMMENT '可领取标签',
  `receive_savings` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '流水限制',
  `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '开始时间',
  `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '结束时间',
  `reward_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `savings` decimal(10, 2) NOT NULL COMMENT '流水倍数',
  `sort` int(5) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL COMMENT '状态（1启用，0禁用）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `code`(`code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '领取码';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableFullName("dq_receive_code_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_receive_code_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `code` varchar(100) NOT NULL COMMENT '领取码',
  `receive_code_id` int(11) NOT NULL COMMENT '领取码id',
  `receive_code_data` text NOT NULL COMMENT '领取码快照',
  `reward_amount` decimal(10, 2) NOT NULL COMMENT '领取金额',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '领取码记录';
SQL;
            $this->execute($sql);
        }

    }
}