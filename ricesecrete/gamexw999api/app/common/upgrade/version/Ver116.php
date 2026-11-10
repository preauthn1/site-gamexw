<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver116 extends VersionBase {

    public function run() {
        // 转盘在线任务
        if (!$this->existTable("dq_lottery_online_task")) {
            $sql = <<<SQL
CREATE TABLE `dq_lottery_online_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0每天，1每周，2每月）',
  `time` int(11) NOT NULL COMMENT '时长（单位：分钟）',
  `lottery_num` int(3) NOT NULL DEFAULT 1 COMMENT '赠送抽奖次数',
  `level_id` int(11) NOT NULL DEFAULT 0 COMMENT '参与vip等级限制',
  `tag_id` int(11) NOT NULL DEFAULT 0 COMMENT '参与标签限制',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '参与代理线限制',
  `join_num` tinyint(3) NOT NULL COMMENT '可参与数量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `lottery_num`(`lottery_num`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='转盘在线任务';
SQL;
            $this->execute($sql);
        }
        // 转盘在线任务记录
        if (!$this->existTable("dq_lottery_online_task_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_lottery_online_task_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `task_id` int(11) NOT NULL COMMENT 'id',
  `type` tinyint(1) NOT NULL COMMENT '类型（0每天，1每周，2每月）',
  `receive_num` tinyint(3) NOT NULL COMMENT '领取次数',
  `receive_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '获取金额',
  `receive_integral` int(10) NOT NULL DEFAULT 0 COMMENT '获取积分',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `task_id`(`task_id`, `type`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转盘在线任务领取记录';
SQL;
            $this->execute($sql);
        }

        //转盘在线任务
        $huodongNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if($huodongNodeId) {
            $onlineTaskNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/onlineTask');
            if (!$onlineTaskNodeId) {
                //转盘在线任务
                $this->addPublicParentNode(
                    '转盘在线任务',
                    '/huodongguanli/onlineTask',
                    'onlineTask',
                    '/huodongguanli/onlineTask',
                    $huodongNodeId,
                    4,
                    true
                );
            }
            //转盘在线任务记录
            $onlineTaskRecordNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/onlineTaskRecord');
            if (!$onlineTaskRecordNodeId) {
                //余额统计
                $this->addPublicParentNode(
                    '转盘在线任务记录',
                    '/huodongguanli/onlineTaskRecord',
                    'onlineTaskRecord',
                    '/huodongguanli/onlineTaskRecord',
                    $huodongNodeId,
                    4
                );
            }
        }
        // 添加语言字段
        if (!$this->existTableField("dq_lottery_online_task", "lang")){
            $sql = "ALTER TABLE `dq_lottery_online_task` ADD COLUMN `lang` varchar(50) NOT NULL COMMENT '语言';";
            $this->execute($sql);
        }

    }
}