<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver184 extends VersionBase
{

    public function run()
    {
        if (!$this->existTableFullName("dq_operating_agent")) {
            $sql = "CREATE TABLE `dq_operating_agent`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `total_recharge` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '充值总额',
  `first_recharge_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '首冲金额',
  `total_withdrawal` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '提现总额',
  `actual_payment` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '实际出款',
  `access_balance` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '存取差额',
  `new_registrants` int(11) NOT NULL DEFAULT 0 COMMENT '新注册人数',
  `recharge_number` int(11) NOT NULL DEFAULT 0 COMMENT '充值人数',
  `number_charge` int(11) NOT NULL DEFAULT 0 COMMENT '首充人数',
  `number_withdrawals` int(11) NOT NULL DEFAULT 0 COMMENT '提现人数',
  `number_people` int(11) NOT NULL DEFAULT 0 COMMENT '投注人数',
  `number_bets` int(11) NOT NULL DEFAULT 0 COMMENT '投注笔数',
  `total_bet` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注总额',
  `bet_draw` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注和局金额',
  `bet_valid_amount` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '有效投注金额',
  `betting_loss` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注盈亏',
  `member_total_lose` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '会员总输',
  `artificial_increase` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '人工增加',
  `artificial_reduction` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '人工减少',
  `agency_commission` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '代理佣金',
  `promotional_rebates` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '推广返佣',
  `activity_offer` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '活动优惠',
  `give_bonuses` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '赠送彩金',
  `turntable_times` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '转盘次数',
  `add_time` int(11) NOT NULL DEFAULT 0 COMMENT '时间日期',
  `day_time` date NOT NULL COMMENT '日期',
  `active_number` int(10) NOT NULL DEFAULT 0 COMMENT '活跃人数',
  `win_price` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '派彩总额',
  `lottery` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '免费转盘奖励',
  `task_reward` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '任务中心奖励',
  `level_reward` decimal(20, 2) NULL DEFAULT 0.00 COMMENT 'VIP等级周月奖励',
  `game_back` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '游戏反水',
  `sign_reward` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '签到奖励',
  `agent_fyfy` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '代理返佣',
  `record_reward` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '领取码',
  `no_settle_order` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '未结算注单',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '代理运营总表' ROW_FORMAT = FIXED;";
            $this->execute($sql);
        }
        //代理留存率
        if (!$this->existTableField("dq_remain_report_agent", "remain_rate6")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report_agent` 
ADD COLUMN `remain_rate6` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_rate5`,
ADD COLUMN `remain_uids6` varchar(1000) NULL DEFAULT 0 AFTER `remain_uids5`,
ADD COLUMN `remain_amount6` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_amount5`;
SQL;
            $this->execute($sql);
        }
        //代理运营总表和留存率报表
        $reportNodeId = $this->getNodeIdByNodeUrl('/report');
        if ($reportNodeId) {
            $reportAgentNodeId = $this->getNodeIdByNodeUrl('/report/reportAgent');
            if (!$reportAgentNodeId) {
                $arr = [
                    'node_name' => '代理运营报表',
                    'node_url' => '/report/reportAgent',
                    'path' => '/report/reportAgent',
                    'name' => 'reportAgent',
                    'pid' => $reportNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 0,
                ];
                $_id = Db::name('admin_node')->insertGetId($arr);
                $arr = [
                    'node_name' => '导出',
                    'node_url' => 'export',
                    'path' => 'export',
                    'name' => 'export',
                    'pid' => $_id,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                $_id = Db::name('admin_node')->insertGetId($arr);
            }
            $remainAgentNodeId = $this->getNodeIdByNodeUrl('/report/remainAgent');
            if (!$remainAgentNodeId) {
                $in_all = [
                    'node_name' => '代理留存率',
                    'node_url' => '/report/remainAgent',
                    'path' => '/report/remainAgent',
                    'name' => 'remainAgent',
                    'pid' => $reportNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 0,
                ];
                $_id = Db::name('admin_node')->insertGetId($in_all);
                $arr = [
                    'node_name' => '导出',
                    'node_url' => 'export',
                    'path' => 'export',
                    'name' => 'export',
                    'pid' => $_id,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                $_id = Db::name('admin_node')->insertGetId($arr);
            }
        }
        //代理留存率充值人数
        if (!$this->existTableField("dq_remain_report_agent", "remain_people1")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report_agent` 
ADD COLUMN `remain_people1` int(11) NULL DEFAULT 0 AFTER `remain_rate1`,
ADD COLUMN `remain_people2` int(11) NULL DEFAULT 0 AFTER `remain_rate2`,
ADD COLUMN `remain_people3` int(11) NULL DEFAULT 0 AFTER `remain_rate3`,
ADD COLUMN `remain_people4` int(11) NULL DEFAULT 0 AFTER `remain_rate4`,
ADD COLUMN `remain_people5` int(11) NULL DEFAULT 0 AFTER `remain_rate5`,
ADD COLUMN `remain_people6` int(11) NULL DEFAULT 0 AFTER `remain_rate6`,
ADD COLUMN `remain_people7` int(11) NULL DEFAULT 0 AFTER `remain_rate7`,
ADD COLUMN `remain_people15` int(11) NULL DEFAULT 0 AFTER `remain_rate15`,
ADD COLUMN `remain_people30` int(11) NULL DEFAULT 0 AFTER `remain_rate30`,
ADD COLUMN `remain_people60` int(11) NULL DEFAULT 0 AFTER `remain_rate60`;
SQL;
            $this->execute($sql);
        }
        //留存率充值人数
        if (!$this->existTableField("dq_remain_report", "remain_people1")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report` 
ADD COLUMN `remain_people1` int(11) NULL DEFAULT 0 AFTER `remain_rate1`,
ADD COLUMN `remain_people2` int(11) NULL DEFAULT 0 AFTER `remain_rate2`,
ADD COLUMN `remain_people3` int(11) NULL DEFAULT 0 AFTER `remain_rate3`,
ADD COLUMN `remain_people4` int(11) NULL DEFAULT 0 AFTER `remain_rate4`,
ADD COLUMN `remain_people5` int(11) NULL DEFAULT 0 AFTER `remain_rate5`,
ADD COLUMN `remain_people6` int(11) NULL DEFAULT 0 AFTER `remain_rate6`,
ADD COLUMN `remain_people7` int(11) NULL DEFAULT 0 AFTER `remain_rate7`,
ADD COLUMN `remain_people15` int(11) NULL DEFAULT 0 AFTER `remain_rate15`,
ADD COLUMN `remain_people30` int(11) NULL DEFAULT 0 AFTER `remain_rate30`,
ADD COLUMN `remain_people60` int(11) NULL DEFAULT 0 AFTER `remain_rate60`;
SQL;
            $this->execute($sql);
        }

    }
}