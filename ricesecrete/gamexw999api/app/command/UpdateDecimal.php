<?php

namespace app\command;

use app\common\model\UserModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class UpdateDecimal extends Command {

    protected function configure() {
        $this->setName('update:decimal')->setDescription('更新数据库decimal默认值为0');
    }

    protected function execute(Input $input, Output $output) {
        $sql = "show tables";
        $tables = Db::query($sql);
        $sqls = [];
        $sql_tmp = "MODIFY COLUMN `%s` %s NULL DEFAULT 0 COMMENT '%s'";
        foreach ($tables as $tb) {
            $tb = array_values($tb);
            $fields = Db::table($tb[0])->getFields();
            $sql = "ALTER TABLE `{$tb[0]}` ";
            $sql_modify = [];
            foreach ($fields as $field) {
                if (strexists($field['type'], 'decimal') && is_null($field['default'])) {
                    $sql_modify[] = sprintf($sql_tmp, $field['name'], $field['type'], $field['comment']);
                }
            }
            if (empty($sql_modify)) {
                continue;
            }
            $sqls[] = $sql . implode(",", $sql_modify);
        }

        foreach ($sqls as $sql) {
            preg_match('/ALTER TABLE `(.*)` MODIFY/', $sql, $match);
            if (isset($match[1]) && $this->existTableFullName($match[1])) {
                $ret = Db::execute($sql);
                $output->writeln("table:{$match[1]},updated:{$ret}");
            }
        }
    }

    public function existTableFullName($full_table_name, $config = "") {
        $exist = Db::connect($config)->query("show tables like '{$full_table_name}'");
        return empty($exist) ? false : true;
    }

    private function getSql() {
        $sql = <<<SQL
ALTER TABLE `dq_action_apply` MODIFY COLUMN `gd_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '类型为3的，固定奖励金额',MODIFY COLUMN `tyj_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '体验金金额',MODIFY COLUMN `ck_hl_amount` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '存款获利金额',MODIFY COLUMN `water` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '流水倍数 用于提现判断',MODIFY COLUMN `integral` decimal(10, 2) NULL DEFAULT 0 COMMENT '积分奖励',MODIFY COLUMN `integral_rate` decimal(10, 2) NULL DEFAULT 0 COMMENT '积分奖励比例';
ALTER TABLE `dq_agent_report` MODIFY COLUMN `order_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '订单总额',MODIFY COLUMN `win_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '总赢金额';
ALTER TABLE `dq_agent_sell_log` MODIFY COLUMN `money` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '奖励金额';
ALTER TABLE `dq_agent_setting` MODIFY COLUMN `stratum_bonus` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '领取奖金';
ALTER TABLE `dq_analyst_record` MODIFY COLUMN `odds` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '赔率(包赔有效)';
ALTER TABLE `dq_artificial_balance` MODIFY COLUMN `price` decimal(20, 2) NULL DEFAULT 0 COMMENT '操作金额';
ALTER TABLE `dq_currency` MODIFY COLUMN `recharge_rate` decimal(20, 8) NOT NULL DEFAULT 1 COMMENT '充值汇率',MODIFY COLUMN `withdrawal_rate` decimal(20, 8) NOT NULL DEFAULT 1 COMMENT '提现汇率';
ALTER TABLE `dq_false_accout` MODIFY COLUMN `b_price` decimal(10, 2) NULL DEFAULT 0 COMMENT '本金';
ALTER TABLE `dq_flyer_order` MODIFY COLUMN `money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '所上分数';
ALTER TABLE `dq_flyer_zhudan` MODIFY COLUMN `bets_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `reward_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '收益金额',MODIFY COLUMN `before_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注后余额',MODIFY COLUMN `after_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '结算后余额';
ALTER TABLE `dq_game_award` MODIFY COLUMN `gaw_winprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '奖金';
ALTER TABLE `dq_game_back_config` MODIFY COLUMN `gbc_rate` decimal(10, 6) NULL DEFAULT 0 COMMENT '返水比例';
ALTER TABLE `dq_game_bets` MODIFY COLUMN `gb_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `gb_winprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额';
ALTER TABLE `dq_game_bets_son` MODIFY COLUMN `gb_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `gb_winprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额';
ALTER TABLE `dq_game_bets_temp` MODIFY COLUMN `gb_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `gb_winprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的金额';
ALTER TABLE `dq_game_cards_special` MODIFY COLUMN `gs_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '操作金额';
ALTER TABLE `dq_game_km_special` MODIFY COLUMN `gs_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '操作金额';
ALTER TABLE `dq_game_pay_logs` MODIFY COLUMN `gpl_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '交易价格';
ALTER TABLE `dq_game_report_operating` MODIFY COLUMN `bets_total` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注总额',MODIFY COLUMN `pay_total` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '支出总额',MODIFY COLUMN `user_total_loss` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '玩家总盈亏',MODIFY COLUMN `platform_total_lose` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '平台总盈亏';
ALTER TABLE `dq_game_set` MODIFY COLUMN `min_transfer` decimal(10, 2) NULL DEFAULT 0 COMMENT '转账最低金额',MODIFY COLUMN `max_transfer` decimal(10, 2) NULL DEFAULT 0 COMMENT '转账最高金额';
ALTER TABLE `dq_game_third_special` MODIFY COLUMN `gs_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '操作金额';
ALTER TABLE `dq_game_ticket` MODIFY COLUMN `odds` decimal(20, 2) NULL DEFAULT 0 COMMENT '赔率';
ALTER TABLE `dq_game_tx` MODIFY COLUMN `bet_amount` decimal(20, 2) NULL DEFAULT 0,MODIFY COLUMN `actual_amount` decimal(20, 2) NULL DEFAULT 0,MODIFY COLUMN `odds` decimal(20, 2) NULL DEFAULT 0,MODIFY COLUMN `payout` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢的奖金';
ALTER TABLE `dq_game_type` MODIFY COLUMN `min_amount` decimal(20, 2) NOT NULL DEFAULT 0,MODIFY COLUMN `max_amount` decimal(20, 2) NOT NULL DEFAULT 0;
ALTER TABLE `dq_game_user` MODIFY COLUMN `amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '游戏平台钱',MODIFY COLUMN `min_transfer` decimal(20, 2) NULL DEFAULT 0,MODIFY COLUMN `max_transfer` decimal(20, 2) NULL DEFAULT 0;
ALTER TABLE `dq_game_wm_special` MODIFY COLUMN `gs_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '操作金额';
ALTER TABLE `dq_game_zhudan` MODIFY COLUMN `bets_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `reward_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '收益金额',MODIFY COLUMN `before_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注后余额',MODIFY COLUMN `after_coin` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '结算后余额';
ALTER TABLE `dq_liansai_sc_bd` MODIFY COLUMN `trading` decimal(10, 2) NULL DEFAULT 0 COMMENT '虚拟交易量',MODIFY COLUMN `zhudan_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '注单金额',MODIFY COLUMN `test_zhudan_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '测试账号注单金额';
ALTER TABLE `dq_liansai_zhudan` MODIFY COLUMN `tz_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `sy_res` decimal(20, 2) NULL DEFAULT 0 COMMENT '输赢';
ALTER TABLE `dq_lottery_config` MODIFY COLUMN `required_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '消耗金额',MODIFY COLUMN `uprice` decimal(20, 2) NULL DEFAULT 0 COMMENT '充值usdt金额';
ALTER TABLE `dq_lottery_prize` MODIFY COLUMN `amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '金额',MODIFY COLUMN `probability` decimal(10, 2) NULL DEFAULT 0 COMMENT '中奖几率%';
ALTER TABLE `dq_lottery_record` MODIFY COLUMN `price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '消费金额',MODIFY COLUMN `prize_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '奖品金额';
ALTER TABLE `dq_ol_pay` MODIFY COLUMN `pay_price` decimal(10, 2) NULL DEFAULT 0 COMMENT '支付金额';
ALTER TABLE `dq_operating` MODIFY COLUMN `total_recharge` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '充值总额',MODIFY COLUMN `first_recharge_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '首冲金额',MODIFY COLUMN `total_withdrawal` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '提现总额',MODIFY COLUMN `actual_payment` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '实际出款',MODIFY COLUMN `access_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '存取差额',MODIFY COLUMN `total_bet` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注总额',MODIFY COLUMN `bet_draw` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注和局金额',MODIFY COLUMN `bet_valid_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注金额',MODIFY COLUMN `betting_loss` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注盈亏',MODIFY COLUMN `member_total_lose` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '会员总输',MODIFY COLUMN `artificial_increase` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '人工增加',MODIFY COLUMN `artificial_reduction` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '人工减少',MODIFY COLUMN `agency_commission` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '代理佣金',MODIFY COLUMN `promotional_rebates` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '推广返佣',MODIFY COLUMN `activity_offer` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '活动优惠',MODIFY COLUMN `give_bonuses` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '赠送彩金',MODIFY COLUMN `turntable_times` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '转盘次数';
ALTER TABLE `dq_pay_set` MODIFY COLUMN `pay_min_amout` decimal(10, 2) NULL DEFAULT 0 COMMENT '渠道最低金额',MODIFY COLUMN `pay_max_amout` decimal(10, 2) NULL DEFAULT 0 COMMENT '渠道最高金额',MODIFY COLUMN `recharge_rate` decimal(10, 6) NULL DEFAULT 0 COMMENT '针对U 充值税率',MODIFY COLUMN `withdrawal_rate` decimal(10, 6) NULL DEFAULT 0 COMMENT '针对U 提现税率';
ALTER TABLE `dq_remain_report` MODIFY COLUMN `recharge_amount` decimal(20, 4) NULL DEFAULT 0 COMMENT '当天首充金额',MODIFY COLUMN `remain_rate3` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate1` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount1` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate2` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount2` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount3` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate5` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate4` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount4` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount5` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate7` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount7` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate15` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount15` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate30` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount30` decimal(20, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_rate60` decimal(10, 4) NULL DEFAULT 0,MODIFY COLUMN `remain_amount60` decimal(20, 4) NULL DEFAULT 0;
ALTER TABLE `dq_s_lottery_odds` MODIFY COLUMN `bet_odd` decimal(10, 4) NOT NULL DEFAULT 0 COMMENT '赔率';
ALTER TABLE `dq_s_lottery_order` MODIFY COLUMN `odds` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '赔率',MODIFY COLUMN `amount` decimal(20, 4) NOT NULL DEFAULT 0 COMMENT '下注金额',MODIFY COLUMN `win_amount` decimal(20, 4) NULL DEFAULT 0 COMMENT '赢金额';
ALTER TABLE `dq_task` MODIFY COLUMN `min_bet` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最低下注（奖励类型0时用到）',MODIFY COLUMN `min_recharge` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最低充值（奖励类型1时用到）',MODIFY COLUMN `multiple` decimal(10, 1) NOT NULL DEFAULT 0 COMMENT '倍数（奖励类型为2和3时用到）',MODIFY COLUMN `amount_or_integral` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '奖励金额或积分',MODIFY COLUMN `cumulative_recharge` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '累计充值',MODIFY COLUMN `cumulative_bet` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '累计下注';
ALTER TABLE `dq_third_fyfy_lvlconfig` MODIFY COLUMN `tfc_rate` decimal(10, 6) NULL DEFAULT 0 COMMENT '阶梯分佣比例';
ALTER TABLE `dq_third_stats_back` MODIFY COLUMN `sb_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `sb_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `sb_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '和金额',MODIFY COLUMN `sb_backbase` decimal(20, 2) NULL DEFAULT 0 COMMENT '返水基数金额（输+赢）',MODIFY COLUMN `sb_backrate` decimal(20, 2) NULL DEFAULT 0 COMMENT '当前返水比例，根据VIP级别和各游戏设置',MODIFY COLUMN `sb_backmoney` decimal(20, 2) NULL DEFAULT 0 COMMENT '应返金额';
ALTER TABLE `dq_third_stats_fybet` MODIFY COLUMN `ssb_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `ssb_betlose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额（冗余）',MODIFY COLUMN `ssb_betwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额（冗余）',MODIFY COLUMN `ssb_betdraw` decimal(20, 2) NULL DEFAULT 0 COMMENT '和金额（冗余）',MODIFY COLUMN `ssb_baserate` decimal(20, 2) NULL DEFAULT 0 COMMENT '分佣基数比例',MODIFY COLUMN `ssb_basereal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际用于分佣的金额（投注金额*分佣基数百分比）',MODIFY COLUMN `ssb_rate` decimal(20, 2) NULL DEFAULT 0 COMMENT '分佣比例',MODIFY COLUMN `ssb_fy` decimal(20, 2) NULL DEFAULT 0 COMMENT '分佣金额';
ALTER TABLE `dq_third_stats_fyfy` MODIFY COLUMN `sff_prelosetotal` decimal(20, 2) NULL DEFAULT 0 COMMENT '上期负盈，0或负数',MODIFY COLUMN `sff_bet` decimal(20, 8) NULL DEFAULT 0 COMMENT '有效投注额，用于外部用户代理返佣',MODIFY COLUMN `sff_betwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `sff_betloss` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `sff_ks` decimal(20, 2) NULL DEFAULT 0 COMMENT '客损：赢金额-输金额（取反），负数表示客户赢了',MODIFY COLUMN `sff_payin` decimal(20, 2) NULL DEFAULT 0 COMMENT '充值金额',MODIFY COLUMN `sff_payinfee` decimal(20, 2) NULL DEFAULT 0 COMMENT '充值手续费',MODIFY COLUMN `sff_payout` decimal(20, 2) NULL DEFAULT 0 COMMENT '提现金额',MODIFY COLUMN `sff_payoutfee` decimal(20, 2) NULL DEFAULT 0 COMMENT '提现手续费',MODIFY COLUMN `sff_yh` decimal(20, 2) NULL DEFAULT 0 COMMENT '优惠额',MODIFY COLUMN `sff_back` decimal(20, 2) NULL DEFAULT 0 COMMENT '返水额',MODIFY COLUMN `sff_tec` decimal(20, 2) NULL DEFAULT 0 COMMENT '技术维护费',MODIFY COLUMN `sff_base` decimal(20, 2) NULL DEFAULT 0 COMMENT '分佣基数（上期负盈+充值额-提现额-优惠-返水-技术维护费-充值手续费-提现手续费），可为负数',MODIFY COLUMN `sff_baserate` decimal(20, 6) NULL DEFAULT 0 COMMENT '分佣基数比例',MODIFY COLUMN `sff_basereal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际用于分佣的金额（分佣基数*分佣基数百分比）',MODIFY COLUMN `sff_rate` decimal(20, 6) NULL DEFAULT 0 COMMENT '阶梯最高分佣比例',MODIFY COLUMN `sff_fy` decimal(20, 2) NULL DEFAULT 0 COMMENT '分佣金额',MODIFY COLUMN `sff_subout` decimal(20, 2) NULL DEFAULT 0 COMMENT '扣减内部代理下级外部代理分佣金额',MODIFY COLUMN `sff_losetotal` decimal(20, 2) NULL DEFAULT 0 COMMENT '0或负数，本期负盈，去掉保底数的部分';
ALTER TABLE `dq_third_stats_fyfylog` MODIFY COLUMN `sffl_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注额',MODIFY COLUMN `sffl_betwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `sffl_betloss` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `sffl_ks` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额-输金额（取反），负数表示客户赢了',MODIFY COLUMN `sffl_payin` decimal(20, 2) NULL DEFAULT 0 COMMENT '充值金额',MODIFY COLUMN `sffl_payinfee` decimal(20, 2) NULL DEFAULT 0 COMMENT '充值手续费',MODIFY COLUMN `sffl_payout` decimal(20, 2) NULL DEFAULT 0 COMMENT '提现金额',MODIFY COLUMN `sffl_payoutfee` decimal(20, 2) NULL DEFAULT 0 COMMENT '提现手续费',MODIFY COLUMN `sffl_yh` decimal(20, 2) NULL DEFAULT 0 COMMENT '优惠额',MODIFY COLUMN `sffl_back` decimal(20, 2) NULL DEFAULT 0 COMMENT '返水额';
ALTER TABLE `dq_third_stats_global` MODIFY COLUMN `su_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `su_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `su_losereal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际输金额（已包含注单赢，但实际金额输的部分，比如半赢）',MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `su_winreal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际赢金额（已去掉本金，仅包含结算金额大于本金的部分）',MODIFY COLUMN `su_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '平金额',MODIFY COLUMN `su_syswin` decimal(20, 2) NULL DEFAULT 0 COMMENT '平台盈亏，正数盈，负数亏',MODIFY COLUMN `su_valid_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注';
ALTER TABLE `dq_third_stats_global_temp` MODIFY COLUMN `su_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `su_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `su_losereal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际输金额（已包含注单赢，但实际金额输的部分，比如半赢）',MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `su_winreal` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际赢金额（已去掉本金，仅包含结算金额大于本金的部分）',MODIFY COLUMN `su_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '平金额',MODIFY COLUMN `su_syswin` decimal(20, 2) NULL DEFAULT 0 COMMENT '平台盈亏，正数盈，负数亏',MODIFY COLUMN `su_valid_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注';
ALTER TABLE `dq_third_stats_proxyline` MODIFY COLUMN `spl_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `spl_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `spl_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `spl_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '平金额';
ALTER TABLE `dq_third_stats_users` MODIFY COLUMN `su_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `su_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `su_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '平金额';
ALTER TABLE `dq_third_stats_users_temp` MODIFY COLUMN `su_bet` decimal(20, 2) NULL DEFAULT 0 COMMENT '投注金额',MODIFY COLUMN `su_lose` decimal(20, 2) NULL DEFAULT 0 COMMENT '输金额',MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0 COMMENT '赢金额',MODIFY COLUMN `su_draw` decimal(20, 2) NULL DEFAULT 0 COMMENT '平金额';
ALTER TABLE `dq_user` MODIFY COLUMN `balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '余额',MODIFY COLUMN `water_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '流水总金额-不提现',MODIFY COLUMN `sc_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '充值总金额',MODIFY COLUMN `xc_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '续充金额',MODIFY COLUMN `withdrawal_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '已提现金额',MODIFY COLUMN `agent_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '代理返佣',MODIFY COLUMN `tg_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '推广金额',MODIFY COLUMN `win_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '赢的金额',MODIFY COLUMN `lose_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '输的金额',MODIFY COLUMN `zd_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '总下注单总金额',MODIFY COLUMN `ty_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '体验金额',MODIFY COLUMN `betamount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '限制下注金额',MODIFY COLUMN `limit_min` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最低下注',MODIFY COLUMN `limit_max` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '最高下注',MODIFY COLUMN `limit_sum_max` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '单场最高下注',MODIFY COLUMN `savings` decimal(20, 2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '储蓄金额（用于提现判断）',MODIFY COLUMN `set_fund_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '当前用户已购买的定期基金金额',MODIFY COLUMN `live_fund_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '当前用户已购买的活期基金金额',MODIFY COLUMN `frozen_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '基金定期冻结金额',MODIFY COLUMN `flyer_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '飞行员上分余额';
ALTER TABLE `dq_user_agent` MODIFY COLUMN `rate` decimal(10, 2) NULL DEFAULT 0 COMMENT '收益率，需要/100';
ALTER TABLE `dq_user_balance_gamelog` MODIFY COLUMN `ubgl_oldban` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户游戏账户旧余额（预计）',MODIFY COLUMN `ubgl_newban` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户游戏账户新余额（预计）',MODIFY COLUMN `ubgl_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '变更金额（正负）';
ALTER TABLE `dq_user_balance_mx` MODIFY COLUMN `old_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '旧的金额记录',MODIFY COLUMN `new_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '新的金额记录',MODIFY COLUMN `price` decimal(20, 2) NULL DEFAULT 0 COMMENT '交易金额';
ALTER TABLE `dq_user_balance_translog` MODIFY COLUMN `ubtl_oldban` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户平台账户旧余额',MODIFY COLUMN `ubtl_newban` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户平台账户新余额',MODIFY COLUMN `ubtl_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '转账金额（正数）';
ALTER TABLE `dq_user_ck` MODIFY COLUMN `ck_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '存款金额',MODIFY COLUMN `ck_hl_amount` decimal(10, 2) NULL DEFAULT 0 COMMENT '存款获利金额',MODIFY COLUMN `ck_integral` decimal(10, 2) NULL DEFAULT 0 COMMENT '积分奖励',MODIFY COLUMN `ck_integral_rate` decimal(10, 2) NULL DEFAULT 0 COMMENT '积分奖励比例';
ALTER TABLE `dq_user_first_recharge` MODIFY COLUMN `amount` decimal(20, 2) UNSIGNED NULL DEFAULT 0 COMMENT '充值总额';
ALTER TABLE `dq_user_level` MODIFY COLUMN `recharge_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '累积充值金额',MODIFY COLUMN `transaction_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '累积交易金额',MODIFY COLUMN `min_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '最低出款金额',MODIFY COLUMN `max_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '最高出款金额',MODIFY COLUMN `level_gold` decimal(20, 2) NULL DEFAULT 0 COMMENT '等级彩金',MODIFY COLUMN `week_reward_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '周奖金金额',MODIFY COLUMN `month_reward_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '月奖金金额',MODIFY COLUMN `water` decimal(20, 2) NULL DEFAULT 0 COMMENT '游戏流水';
ALTER TABLE `dq_user_level_reward` MODIFY COLUMN `reward_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '奖励金额';
ALTER TABLE `dq_user_offline` MODIFY COLUMN `apply_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '申请金额',MODIFY COLUMN `arrival_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '到账金额（转换之后的）';
ALTER TABLE `dq_user_thirds` MODIFY COLUMN `ut_balancein` decimal(20, 4) NULL DEFAULT 0 COMMENT '三方平台进入转入金额',MODIFY COLUMN `ut_balance` decimal(20, 4) UNSIGNED NULL DEFAULT 0 COMMENT '三方平台当前余额',MODIFY COLUMN `ut_balanceout` decimal(20, 4) NULL DEFAULT 0 COMMENT '三方平台退出转出金额';
ALTER TABLE `dq_user_withdrawal` MODIFY COLUMN `w_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '提现金额',MODIFY COLUMN `arrival_price` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际到账金额（换算之后）',MODIFY COLUMN `arrival_prices` decimal(20, 2) NULL DEFAULT 0 COMMENT '实际到账金额（没换算）',MODIFY COLUMN `exchange_rate` decimal(10, 6) NULL DEFAULT 0 COMMENT '汇率',MODIFY COLUMN `w_service` decimal(20, 2) NULL DEFAULT 0 COMMENT '手续费';
ALTER TABLE `dq_user_report` MODIFY COLUMN `zd_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '注单总额', MODIFY COLUMN `sy_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '会员输赢金额', MODIFY COLUMN `trans_fee` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '交易手续费', MODIFY COLUMN `adjust_add` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '人工调整加', MODIFY COLUMN `adjust_reduce` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '人工调整减', MODIFY COLUMN `yh_amount` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '活动优惠', MODIFY COLUMN `agent_commission` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '代理返佣', MODIFY COLUMN `share_commission` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '推广返佣', MODIFY COLUMN `recharge` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '充值金额', MODIFY COLUMN `withdrawal` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '提现金额', MODIFY COLUMN `lottery_money` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '升级彩金/赠送彩金', MODIFY COLUMN `order_cancel` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '注单取消金额';
SQL;

        return $sql;
    }
}