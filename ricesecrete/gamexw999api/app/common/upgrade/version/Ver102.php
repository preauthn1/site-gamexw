<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver102 extends VersionBase {

    public function run() {
        // 增加任务表活跃度字段
        if (!$this->existTableField("dq_task", "activity")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `activity` int(10) COMMENT '任务完成活跃度';";
            $this->execute($sql);
        }

        // 阶段任务领取记录
        if (!$this->existTableField("dq_task_record", "activity")) {
            $sql = "ALTER TABLE `dq_task_record` ADD COLUMN `activity` int(10) COMMENT '任务完成活跃度';";
            $this->execute($sql);
        }

        // 增加阶段任务表
        if (!$this->existTable("dq_task_stage")) {
            $sql = <<<SQL
CREATE TABLE `dq_task_stage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(100) COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '语言',
  `type` tinyint(1) NOT NULL COMMENT '类型（0每日任务，1每周任务）',
  `receive_level_id` int NOT NULL DEFAULT '0' COMMENT 'VIP等级限制',
  `receive_tag_id` int NOT NULL DEFAULT '0' COMMENT '领取标签限制（0不限制）',
  `reg_json` text  COMMENT '奖励规则',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否开启奖励：0否，1是',
  `create_time` int NOT NULL COMMENT '创建时间',
  `update_time` int NOT NULL COMMENT '修改时间',
  `name` varchar(32) COMMENT '阶段任务名称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='阶段任务';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTable("dq_task_stage_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_task_stage_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL COMMENT '用户id',
  `stage_id` int NOT NULL COMMENT '阶段任务id',
  `stage` tinyint NOT NULL COMMENT '阶段',
  `activity` int NOT NULL COMMENT '领取奖励活跃度',
  `reward_amount` decimal(20,2) NOT NULL COMMENT '奖励金额',
  `reward_integral` decimal(20,2) NOT NULL COMMENT '奖励积分',
  `savings` decimal(10,2) NOT NULL DEFAULT '1.00' COMMENT '流水倍数',
  `create_time` int NOT NULL COMMENT '创建时间',
  `update_time` int NOT NULL COMMENT ' 修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='阶段任务奖励领取记录';
SQL;
            $this->execute($sql);
        }

        // 任务分类增加阶段任务关联字段
        if (!$this->existTableField("dq_task_type", "stage_id")){
            $sql = "ALTER TABLE `dq_task_type` ADD COLUMN `stage_id` int(11) DEFAULT 0 COMMENT '阶段任务id';";
            $this->execute($sql);
        }

        // 任务表增加任务模式字段
        if (!$this->existTableField("dq_task", "task_mode")){
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `task_mode` tinyint(1) DEFAULT 0 COMMENT '任务模式：0普通任务，1阶段任务';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "stage_id")) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `stage_id` int(11) NOT NULL DEFAULT 0 COMMENT '阶段任务id';";
            $this->execute($sql);
        }

        $taskListNodeId = $this->getNodeIdByNodeUrl('/task');
        if($taskListNodeId){
            if( !$this->getNodeIdByNodeUrl('/task/taskStage') ){
                //阶段任务列表
                $this->addPublicParentNode(
                    '阶段任务',
                    '/task/taskStage',
                    'taskStage',
                    '/task/taskStage',
                    $taskListNodeId,
                    3,
                    true
                );
            }
            if( !$this->getNodeIdByNodeUrl('/task/stageRecord') ){
                //阶段任务领取记录
                $this->addPublicParentNode(
                    '阶段任务领取记录',
                    '/task/stageRecord',
                    'stageRecord',
                    '/task/stageRecord',
                    $taskListNodeId,
                    4
                );
            }
        }
    }
}