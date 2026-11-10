<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver33 extends VersionBase {

    public function run() {
        // 判断表是否已存在
        if(!$this->existTable("dq_operating")){
            $sql = "CREATE TABLE `dq_operating`  (
                    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `total_recharge` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '充值总额',
                    `first_recharge_amount` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '首冲金额',
                    `total_withdrawal` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '提现总额',
                    `actual_payment` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '实际出款',
                    `access_balance` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '存取差额',
                    `new_registrants` INT(11) NOT NULL DEFAULT 0 COMMENT '新注册人数',
                    `recharge_number` INT(11) NOT NULL DEFAULT 0 COMMENT '充值人数',
                    `number_charge` INT(11) NOT NULL DEFAULT 0 COMMENT '首充人数',
                    `number_withdrawals` INT(11) NOT NULL DEFAULT 0 COMMENT '提现人数',
                    `number_people` INT(11) NOT NULL DEFAULT 0 COMMENT '投注人数',
                    `number_bets` INT(11) NOT NULL DEFAULT 0 COMMENT '投注笔数',
                    `total_bet` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '投注总额',
                    `betting_loss` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '投注盈亏',
                    `member_total_lose` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '会员总输',
                    `artificial_increase` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '人工增加',
                    `artificial_reduction` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '人工减少',
                    `agency_commission` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '代理佣金',
                    `promotional_rebates` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '推广返佣',
                    `activity_offer` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '活动优惠',
                    `give_bonuses` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '赠送彩金',
                    `turntable_times` DECIMAL(20, 2) NOT NULL DEFAULT 0 COMMENT '转盘次数',
                    `add_time` INT(11) NOT NULL DEFAULT 0 COMMENT '时间日期',
                    `day_time` DATE NOT NULL COMMENT '日期',
                    PRIMARY KEY (`id`) USING BTREE
                  ) ENGINE = MYISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci  COMMENT = '运营总表' ROW_FORMAT = FIXED;";
            $this->execute($sql);
        }
    }

}
