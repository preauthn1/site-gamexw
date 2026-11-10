<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver69 extends VersionBase {

    public function run() {
        // 任务
        if (!$this->existTable("dq_task")) {
            $sql = <<<SQL
CREATE TABLE `dq_task`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `background_img` varchar(255) NOT NULL COMMENT '背景图',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0每日任务，1每周任务）',
  `task_condition` varchar(100) NOT NULL COMMENT '任务条件多选（0最低下注额，1最低充值，2最低倍数，3累计充值，4在线时长，5累计下注，6游戏时长）',
  `min_bet` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '最低下注（奖励类型0时用到）',
  `min_recharge` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '最低充值（奖励类型1时用到）',
  `multiple` decimal(10, 1) NOT NULL DEFAULT 0.0 COMMENT '倍数（奖励类型为2和3时用到）',
  `task_num` int(10) NOT NULL COMMENT '任务数量',
  `reward_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励类型（0当前货币余额，1积分）',
  `amount_or_integral` decimal(20, 2) NOT NULL COMMENT '奖励金额或积分',
  `savings` decimal(10, 2) NOT NULL DEFAULT 1.00 COMMENT '流水倍数',
  `receive_level_id` int(11) NOT NULL DEFAULT 0 COMMENT '领取vip等级限制（0不限制）',
  `receive_tag_id` int(11) NOT NULL DEFAULT 0 COMMENT '领取标签限制（0不限制）',
  `sort` tinyint(3) NOT NULL DEFAULT 1 COMMENT '排序',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '任务';
SQL;
            $this->execute($sql);
        }
        //任务内容
        if (!$this->existTable("dq_task_content")) {
            $sql = <<<SQL
CREATE TABLE `dq_task_content`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(10) NOT NULL COMMENT '语言',
  `task_id` int(11) NOT NULL COMMENT '任务id',
  `title` varchar(100) NOT NULL COMMENT '标题',
  `introduce` varchar(255) NOT NULL COMMENT '简介',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '任务内容';
SQL;
            $this->execute($sql);
        }
        //任务领取记录
        if (!$this->existTable("dq_task_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_task_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `task_id` int(11) NOT NULL COMMENT '任务id',
  `type` tinyint(1) NOT NULL COMMENT '类型（0每日任务，1每周任务）',
  `task_condition` varchar(100) NOT NULL COMMENT '任务条件多选（0最低下注额，1最低充值，2最低倍数，3累计充值，4在线时长，5累计下注，6游戏时长）',
  `task_data` text NOT NULL COMMENT '任务数据快照',
  `task_num` int(10) NOT NULL COMMENT '任务数量',
  `finish_num` int(10) NOT NULL COMMENT '完成数量',
  `reward_type` tinyint(1) NOT NULL COMMENT '奖励类型（0余额:当前货币，1积分）',
  `reward_amount_or_integral` decimal(20, 2) NOT NULL COMMENT '奖励金额或积分',
  `savings` decimal(10, 2) NOT NULL DEFAULT 1.00 COMMENT '流水倍数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '任务记录';
SQL;
            $this->execute($sql);
        }
    }
}