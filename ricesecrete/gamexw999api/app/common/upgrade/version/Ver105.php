<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver105 extends VersionBase {

    public function run() {
        // 签到表
        if (!$this->existTable("dq_sign")) {
            $sql = <<<SQL
CREATE TABLE `dq_sign`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `sign_day` int(11) NOT NULL DEFAULT 7 COMMENT '签到天数',
  `sign_condition` tinyint(1) NOT NULL DEFAULT 1 COMMENT '参与条件（1首充金额，2单笔金额，3累计金额，4完成指定签到任务）',
  `recharge_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '条件金额',
  `sign_id` int(11) NOT NULL DEFAULT 0 COMMENT '签到id',
  `condition_date_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '条件对应时间（0所有，1上月，2本月，3昨天，4今天）',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理线id限制（可参与）',
  `tag_id` int(11) NOT NULL DEFAULT 0 COMMENT '标签限制（可参与）',
  `vip_id` int(11) NOT NULL DEFAULT 0 COMMENT 'VIP限制（可参与）',
  `week_sign_count` int(10) NOT NULL DEFAULT 0 COMMENT '每周补签次数',
  `join_count` int(10) NOT NULL DEFAULT 0 COMMENT '可参与次数',
  `reward_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励模式（0每日领取，1累计领取）',
  `reward_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励类型（0固定金额，1奖励占比%）',
  `reward_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `reward_integral` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励积分',
  `savings` int(5) NOT NULL DEFAULT 1 COMMENT '流水倍数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1启用，0禁用）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '签到任务';
SQL;
            $this->execute($sql);
        }
        // 签到天表
        if (!$this->existTable("dq_sign_day")) {
            $sql = <<<SQL
CREATE TABLE `dq_sign_day`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `sign_id` int(11) NOT NULL COMMENT '签到id',
  `day` int(2) NOT NULL COMMENT '天数',
  `reward_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励模式（0每日领取，1累计领取）',
  `reward_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励类型（0固定金额，1奖励占比%）',
  `reward_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `reward_integral` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励积分',
  `savings` int(10) NOT NULL DEFAULT 0 COMMENT '流水倍数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  INDEX `sign_id`(`sign_id`) USING BTREE,
  INDEX `day`(`day`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '签到天数';
SQL;
            $this->execute($sql);
        }
        // 签到记录
        if (!$this->existTable("dq_sign_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_sign_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `sign_id` int(11) NOT NULL COMMENT '签到id',
  `uid` int(11) NOT NULL COMMENT 'UID',
  `day` int(5) NOT NULL COMMENT '天数',
  `amount` decimal(10, 2) NOT NULL COMMENT '计算金额',
  `reward_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `reward_integral` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励积分',
  `savings` int(10) NOT NULL COMMENT '流水倍数',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态（0累计发放，1发放当天，2发放所有）',
  `sign_status` tinyint(1) NOT NULL COMMENT '补签状态（1正常，0补签）',
  `is_expired` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励是否过期（0正常，1过期。发放所有奖励时候用到）',
  `date` varchar(10)  NOT NULL COMMENT '日期',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '签到记录';
SQL;
            $this->execute($sql);
        }

        $activityListNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if($activityListNodeId){
            $signNodeId = $this->getNodeIdByNodeUrl('/sign/list');
            if( !$signNodeId ){
                //签到任务
                $signNodeId = $this->addPublicParentNode(
                    '签到任务',
                    '/sign/list',
                    'signList',
                    '/sign/list',
                    $activityListNodeId,
                    5,
                    true
                );
            }
            //天数列表
            $this->addPublicParentNode(
                '天数列表',
                'dayList',
                'dayList',
                'dayList',
                $signNodeId
            );
            //签到记录
            $this->addPublicParentNode(
                '签到记录',
                '/sign/record',
                'signRecord',
                '/sign/record',
                $activityListNodeId,
                5
            );
        }
        // 签到介绍
        if (!$this->existTableField("dq_sign", "introduce")){
            $sql = "ALTER TABLE `dq_sign` ADD COLUMN `introduce` text COMMENT '介绍';";
            $this->execute($sql);
        }
        // 语言
        if (!$this->existTableField("dq_sign", "lang")){
            $sql = "ALTER TABLE `dq_sign` ADD COLUMN `lang` varchar(20) COMMENT '语言';";
            $this->execute($sql);
        }
        // 签到记录是否奖励
        if (!$this->existTableField("dq_sign_record", "is_receive_reward")){
            $sql = "ALTER TABLE `dq_sign_record` ADD COLUMN `is_receive_reward` tinyint(1) COMMENT '是否领取了奖励';";
            $this->execute($sql);
        }
        // 当日最低充值
        if (!$this->existTableField("dq_sign", "min_recharge")){
            $sql = "ALTER TABLE `dq_sign` ADD COLUMN `min_recharge` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '当日最低充值';";
            $this->execute($sql);
        }
        // 当日最低下注
        if (!$this->existTableField("dq_sign", "min_bet")){
            $sql = "ALTER TABLE `dq_sign` ADD COLUMN `min_bet` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '当日最低下注';";
            $this->execute($sql);
        }
    }
}