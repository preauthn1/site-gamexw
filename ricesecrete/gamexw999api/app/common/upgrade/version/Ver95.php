<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver95 extends VersionBase {

    public function run() {
        if (!$this->existTable("dq_ad_report")) {
            $sql = <<<SQL
CREATE TABLE `dq_ad_report` (
  `sr_dt` int(11) NOT NULL COMMENT '统计日期：Ymd',
  `sr_acid` int(11) DEFAULT '0' COMMENT '统计渠道ID',
  `sr_uid` int(11) DEFAULT '0' COMMENT '渠道用户UID',
  `sr_share_code` varchar(10) DEFAULT '' COMMENT '渠道邀请码',
  `sr_reg` int(11) DEFAULT '0' COMMENT '新注册会员数',
  `sr_first` int(11) DEFAULT '0' COMMENT '首充会员数',
  `sr_user` int(11) DEFAULT '0' COMMENT '累计会员数',
  `sr_recharge_user` varchar(255) DEFAULT '0' COMMENT '充值人数',
  `sr_first_amount` decimal(20,2) DEFAULT '0.00' COMMENT '首充金额',
  `sr_recharge_amount` decimal(20,2) DEFAULT '0.00' COMMENT '充值金额',
  `sr_withdraw_user` int(11) DEFAULT '0' COMMENT '提现人数',
  `sr_withdraw_amount` decimal(20,2) DEFAULT '0.00' COMMENT '提现金额',
  `sr_w_max_amount` decimal(20,2) DEFAULT '0.00' COMMENT '提现单笔金额',
  `sr_validbet` decimal(20,2) DEFAULT '0.00' COMMENT '有效投注',
  `sr_user_lost` decimal(20,2) DEFAULT '0.00' COMMENT '客损',
  `sr_bonus` decimal(20,2) DEFAULT '0.00' COMMENT '所有活动/赠送相关彩金总额',
  UNIQUE KEY `idx_acid` (`sr_dt`,`sr_acid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告渠道统计日报表';
SQL;
            $this->execute($sql);
        }


    }
}