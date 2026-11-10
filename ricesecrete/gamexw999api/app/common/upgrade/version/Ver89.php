<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver89 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_user_games", "ug_play_count")) {
            $sql = "ALTER TABLE `dq_user_games` ADD COLUMN `ug_play_count` int(10) NOT NULL DEFAULT 1 COMMENT '玩游戏次数';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_configs", "tc_timezone")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_timezone` tinyint(4) NULL DEFAULT 0 COMMENT '三方平台对账时区';";
            $this->execute($sql);
        }

        if (!$this->existTableFullName("dq_user_reports")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_reports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '用户名',
  `phone` varchar(50) DEFAULT '' COMMENT '手机',
  `mail` varchar(50) DEFAULT '' COMMENT '邮箱',
  `lebal` tinyint(1) DEFAULT '0' COMMENT '用户标签1一般用户2测试用户',
  `reg_time` int(11) NOT NULL DEFAULT '0' COMMENT '注册时间',
  `first_deposit_time` int(11) NOT NULL DEFAULT '0' COMMENT '首存时间',
  `recent_withdraw_money_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近出金时间',
  `recent_deposit_money_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近入金时间',
  `login_time` int(11) NOT NULL DEFAULT '0' COMMENT '最近登陆时间',
  `deposit_nums` int(11) NOT NULL DEFAULT '0' COMMENT '入金次数',
  `deposit_total` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '入金总额',
  `withdrawal_nums` int(11) NOT NULL DEFAULT '0' COMMENT '出金次数',
  `withdrawal_total` decimal(20,2) DEFAULT '0.00' COMMENT '出金总额',
  `total_gift` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '赠送金额',
  `action_discount` decimal(20,2) DEFAULT '0.00' COMMENT '活动优惠',
  `lottery_total` decimal(20,2) DEFAULT '0.00' COMMENT '抽奖',
  `rebate_total` decimal(20,2) DEFAULT '0.00' COMMENT '返佣',
  `rebate1_total` decimal(20,2) DEFAULT '0.00' COMMENT '返水',
  `promotion_bonus` decimal(20,2) DEFAULT '0.00' COMMENT '推广奖金',
  `mission_rewards` decimal(20,2) DEFAULT '0.00' COMMENT '任务奖励',
  `vip_bonus` decimal(20,2) DEFAULT '0.00' COMMENT 'VIP周月礼金',
  `phone_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手机验证状态，1已完成0未完成',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户状态 1启用 0禁用',
  `level` varchar(50) NOT NULL DEFAULT '' COMMENT '会员等级名称',
  `rec_id` int(11) NOT NULL DEFAULT 0 COMMENT '上级代理用户id',
  `reg_ip` varchar(100) NOT NULL DEFAULT '' COMMENT '注册ip',
  `balance` decimal(20,2) NOT NULL DEFAULT 0 COMMENT '余额',
  `televised` int(11) NOT NULL DEFAULT 0 COMMENT '电访时间',
  `televised_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '电访进度(0 未安排 1待电访 2已电访 3 电访2次以上)',
  `sms_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '短信发送状态(0 未发送 1已发送)',
  `intention_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '客户意向状态 (0未评估 1低 2中 3高)',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `admin_group` varchar(100) DEFAULT '' COMMENT '电访组别',
  `admin_name` varchar(100) DEFAULT '' COMMENT '电访人员',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '管理员id',
  `role_id` int(11) NOT NULL DEFAULT 0 COMMENT '角色id',
  `assign_time` int(11) NOT NULL DEFAULT 0 COMMENT '管理员派发时间',
  `sms_time` int(11) NOT NULL DEFAULT 0 COMMENT '短信发送时间',
  `feedback` varchar(255) DEFAULT '' COMMENT '反馈内容', 
  `is_connected` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否接通(0未安排 1否 2是)',  
  `is_distribution` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否要领取赠金(0未处理 1否 2是)',  
  `follow_id` int(11) NOT NULL DEFAULT 0 COMMENT '最新回访id',  
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_time` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '会员报表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "televised_status")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `televised_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '电访进度(0 未安排 1待电访 2已电访 3 电访2次以上)';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "sms_status")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `sms_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '短信发送状态(0 未发送 1已发送)';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "intention_status")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `intention_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '客户意向状态 (0未评估 1低 2中 3高)';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "remark")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `remark` varchar(255) DEFAULT '' COMMENT '备注';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "admin_group")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `admin_group` varchar(100) DEFAULT '' COMMENT '电访组别';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "admin_name")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `admin_name` varchar(100) DEFAULT '' COMMENT '电访人员';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "admin_id")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '管理员id';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "role_id")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `role_id` int(11) NOT NULL DEFAULT 0 COMMENT '角色id';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "assign_time")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `assign_time` int(11) NOT NULL DEFAULT 0 COMMENT '管理员派发时间';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "sms_time")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `sms_time` int(11) NOT NULL DEFAULT 0 COMMENT '短信发送时间';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "feedback")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `feedback` varchar(255) DEFAULT '' COMMENT '反馈内容';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "is_connected")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `is_connected` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否接通(0未安排 1否 2是)';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "is_distribution")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `is_distribution` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否要领取赠金(0未处理 1否 2是)';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "follow_id")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `follow_id` int(11) NOT NULL DEFAULT 0 COMMENT '最新回访id';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "level")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `level` varchar(50) NOT NULL DEFAULT '' COMMENT '会员等级名称';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "rec_id")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `rec_id` int(11) NOT NULL DEFAULT 0 COMMENT '上级代理用户id';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "status")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户状态 1启用 0禁用';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "phone_status")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `phone_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手机验证状态，1已完成0未完成';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "reg_ip")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `reg_ip` varchar(100) NOT NULL DEFAULT '' COMMENT '注册ip';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_reports", "balance")) {
            $sql = "ALTER TABLE `dq_user_reports` ADD COLUMN `balance` decimal(20,2) NOT NULL DEFAULT 0 COMMENT '余额';";
            $this->execute($sql);
        }

        if (!$this->existTableFullName("dq_third_report")) {
            $sql = <<<SQL
CREATE TABLE `dq_third_report` (
  `tr_dt` int(11) NOT NULL COMMENT '统计日期',
  `tr_timezone` int(11) DEFAULT '0' COMMENT '统计时区',
  `tr_tcid` int(11) DEFAULT '0' COMMENT '三方平台ID',
  `tr_gid` int(11) DEFAULT '0' COMMENT '游戏ID',
  `tr_bet` decimal(20,4) DEFAULT '0.0000' COMMENT '总投注',
  `tr_valid_bet` decimal(20,4) DEFAULT '0.0000' COMMENT '总有效投注',
  `tr_betnum` int(11) DEFAULT '0' COMMENT '投注笔数',
  `tr_betuser` int(11) DEFAULT '0' COMMENT '投注人数',
  `tr_win` decimal(20,4) DEFAULT '0.0000' COMMENT '派彩金额',
  `tr_winnum` int(11) DEFAULT '0' COMMENT '派彩笔数',
  `tr_drawnum` int(11) DEFAULT '0' COMMENT '和局笔数',
  `tr_draw` decimal(20,4) DEFAULT '0.0000' COMMENT '和局金额',
  `tr_syswin` decimal(20,4) DEFAULT '0.0000' COMMENT '平台盈亏',
  UNIQUE KEY `unq_idx` (`tr_dt`,`tr_timezone`,`tr_tcid`,`tr_gid`),
  KEY `idx_tcid` (`tr_tcid`),
  KEY `idx_gid` (`tr_gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $this->execute($sql);
        }
    }
}