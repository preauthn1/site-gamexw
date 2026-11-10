<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver190 extends VersionBase
{

    public function run()
    {
        $bbNodeId = $this->getNodeIdByNodeUrl('/report');
        if ($bbNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/report/userActiveReport');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '用户活跃图表',
                    '/report/userActiveReport',
                    'userActiveReport',
                    '/report/userActiveReport',
                    $bbNodeId,
                    9,
                    false,
                    0
                );
            }
            $NodeId = $this->getNodeIdByNodeUrl('/report/finActiveReport');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '游戏财务图表',
                    '/report/finActiveReport',
                    'finActiveReport',
                    '/report/finActiveReport',
                    $bbNodeId,
                    9,
                    false,
                    0
                );
            }
            $NodeId = $this->getNodeIdByNodeUrl('/report/userValueReport');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '用户价值图表',
                    '/report/userValueReport',
                    'userValueReport',
                    '/report/userValueReport',
                    $bbNodeId,
                    9,
                    false,
                    0
                );
            }
            $NodeId = $this->getNodeIdByNodeUrl('/report/indexActiveReport');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '运营指标图表',
                    '/report/indexActiveReport',
                    'indexActiveReport',
                    '/report/indexActiveReport',
                    $bbNodeId,
                    9,
                    false,
                    0
                );
            }
        }
        //用户基础分析报表
        if (!$this->existTable("dq_stat_user_base")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_base` (
    `suv_dt` int(11) NOT NULL COMMENT '日或月',
    `suv_dw` int(11) DEFAULT '0' COMMENT '周，日月时为0',
    `suv_count` int(255) DEFAULT '0' COMMENT '总人数（当前）',
    `suv_reg` int(255) DEFAULT '0' COMMENT '注册人数（当前）',
    `suv_activity` int(255) DEFAULT '0' COMMENT '活跃人数（当前）',
    `suv_game` int(255) DEFAULT '0' COMMENT '游戏人数（当前）',
    `suv_gamenum` int(11) DEFAULT '0' COMMENT '游戏次数',
    `suv_win` int(255) DEFAULT '0' COMMENT '赢人数（当前）',
    `suv_winnum` int(11) DEFAULT '0' COMMENT '赢次数',
    `suv_winprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢金额（当前）',
    `suv_lose` int(255) DEFAULT '0' COMMENT '输人数（当前）',
    `suv_losenum` int(11) DEFAULT NULL COMMENT '输次数',
    `suv_loseprice` decimal(20,2) DEFAULT '0.00' COMMENT '输金额（当前）',
    `suv_pwinprice` decimal(20,2) DEFAULT '0.00' COMMENT '平台赢金额（当前）',
    `suv_payin` int(255) DEFAULT '0' COMMENT '充值人数（当前）',
    `suv_payinnum` int(11) DEFAULT '0' COMMENT '充值次数',
    `suv_payinprice` decimal(10,2) DEFAULT '0.00' COMMENT '充值金额（当前）',
    `suv_withraw` int(255) DEFAULT '0' COMMENT '提现人数（当前）',
    `suv_withrawnum` int(11) DEFAULT '0' COMMENT '提现次数',
    `suv_withrawprice` decimal(20,2) DEFAULT NULL COMMENT '提现金额（当前）',
    `suv_playprice` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额（当前）',
    `suv_balance` decimal(20,2) DEFAULT '0.00' COMMENT '用户余额（当前）',
    `suv_ppwc` decimal(20,2) DEFAULT '0.00' COMMENT '平台充值提现差额，充值多为正，否则为负（当前）',
    `suv_keep` int(255) DEFAULT '0' COMMENT '留存数量（环比，上期活跃，本期留存）',
    `suv_rekeep` int(255) DEFAULT '0' COMMENT '激活数量(环比，上期流失，本期激活）',
    `suv_out` int(255) DEFAULT '0' COMMENT '流失数量(环比，上期活跃，本期流失）',
    `suv_end` int(255) DEFAULT '0' COMMENT '统计是否结束 0未结束 1已结束',
    KEY `suv_dt` (`suv_dt`),
    KEY `suv_dw` (`suv_dw`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户基础分析报表';
SQL;
            $this->execute($sql);
        }
        //用户基础分析报表（代理版）
        if (!$this->existTable("dq_stat_user_base_proxys")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_base_proxys` (
    `suv_dt` int(11) NOT NULL COMMENT '日或月',
    `suv_dw` int(11) DEFAULT '0' COMMENT '周，日月时为0',
    `suv_proxyid` int(11) DEFAULT '0' COMMENT '代理用户ID',
    `suv_count` int(255) DEFAULT '0' COMMENT '总人数（当前）',
    `suv_reg` int(255) DEFAULT '0' COMMENT '注册人数（当前）',
    `suv_activity` int(255) DEFAULT '0' COMMENT '活跃人数（当前）',
    `suv_game` int(255) DEFAULT '0' COMMENT '游戏人数（当前）',
    `suv_gamenum` int(11) DEFAULT '0' COMMENT '游戏次数',
    `suv_win` int(255) DEFAULT '0' COMMENT '赢人数（当前）',
    `suv_winnum` int(11) DEFAULT '0' COMMENT '赢次数',
    `suv_winprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢金额（当前）',
    `suv_lose` int(255) DEFAULT '0' COMMENT '输人数（当前）',
    `suv_losenum` int(11) DEFAULT NULL COMMENT '输次数',
    `suv_loseprice` decimal(20,2) DEFAULT '0.00' COMMENT '输金额（当前）',
    `suv_pwinprice` decimal(20,2) DEFAULT '0.00' COMMENT '平台赢金额（当前）',
    `suv_payin` int(255) DEFAULT '0' COMMENT '充值人数（当前）',
    `suv_payinnum` int(11) DEFAULT '0' COMMENT '充值次数',
    `suv_payinprice` decimal(10,2) DEFAULT '0.00' COMMENT '充值金额（当前）',
    `suv_withraw` int(255) DEFAULT '0' COMMENT '提现人数（当前）',
    `suv_withrawnum` int(11) DEFAULT '0' COMMENT '提现次数',
    `suv_withrawprice` decimal(20,2) DEFAULT NULL COMMENT '提现金额（当前）',
    `suv_playprice` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额（当前）',
    `suv_balance` decimal(20,2) DEFAULT '0.00' COMMENT '用户余额（当前）',
    `suv_ppwc` decimal(20,2) DEFAULT '0.00' COMMENT '平台充值提现差额，充值多为正，否则为负（当前）',
    `suv_keep` int(255) DEFAULT '0' COMMENT '留存数量（环比，上期活跃，本期留存）',
    `suv_rekeep` int(255) DEFAULT '0' COMMENT '激活数量(环比，上期流失，本期激活）',
    `suv_out` int(255) DEFAULT '0' COMMENT '流失数量(环比，上期活跃，本期流失）',
    `suv_end` int(255) DEFAULT '0' COMMENT '统计是否结束 0未结束 1已结束',
    KEY `suv_dt` (`suv_dt`),
    KEY `suv_dw` (`suv_dw`),
    KEY `suv_proxyid` (`suv_proxyid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户基础分析报表（代理版）';
SQL;
            $this->execute($sql);
        }
        //用户留存和激活报表
        if (!$this->existTable("dq_stat_user_keepre")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_keepre` (
    `sukr_dt` int(255) DEFAULT '0' COMMENT '日期',
    `sukr_reg` int(255) DEFAULT '0' COMMENT '注册人数',
    `sukr_keepone` int(255) DEFAULT '0' COMMENT '留存区间一段，ENV配置',
    `sukr_keeptwo` int(255) DEFAULT '0' COMMENT '二段',
    `sukr_keepthree` int(255) DEFAULT '0',
    `sukr_keepfour` int(255) DEFAULT '0',
    `sukr_keepfive` int(255) DEFAULT '0',
    `sukr_keepsix` int(255) DEFAULT '0',
    `sukr_keepseven` int(255) DEFAULT '0',
    `sukr_rekeepone` int(255) DEFAULT '0' COMMENT '激活区间一段,ENV配置',
    `sukr_rekeeptwo` int(255) DEFAULT '0' COMMENT '二段',
    `sukr_rekeepthree` int(255) DEFAULT '0',
    `sukr_rekeepfour` int(255) DEFAULT '0',
    `sukr_rekeepfive` int(255) DEFAULT '0',
    `sukr_rekeepsix` int(255) DEFAULT '0',
    `sukr_rekeepseven` int(255) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户留存和激活报表';
SQL;
            $this->execute($sql);
        }
        //用户统计用中间数据表
        if (!$this->existTable("dq_stat_user_midtemp")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_midtemp` (
    `sut_dt` int(255) NOT NULL DEFAULT '0' COMMENT '日期,或月份',
    `sut_dw` int(255) DEFAULT '0' COMMENT '周',
    `sut_uid` int(11) DEFAULT '0' COMMENT '用户UID',
    `sut_reg` int(11) DEFAULT '0' COMMENT '是否注册',
    `sut_login` int(11) DEFAULT '0' COMMENT '是否登录',
    `sut_active` int(255) DEFAULT '0' COMMENT '是否活跃 0 或天数',
    `sut_loss` int(255) DEFAULT '1' COMMENT '是否流失 0或天数',
    `sut_rekeep` int(255) DEFAULT '0' COMMENT '是否激活（即重新活跃）',
    `sut_game` int(255) DEFAULT '0' COMMENT '是否游戏(投注次数）',
    `sut_payin` int(255) DEFAULT '0' COMMENT '是否充值 0或次数',
    `sut_withraw` int(255) DEFAULT '0' COMMENT '是否提现 0或次数',
    `sut_balance` decimal(20,2) DEFAULT '0.00' COMMENT '当天余额',
    `sut_withrawprice` decimal(20,2) DEFAULT '0.00' COMMENT '提现金额',
    `sut_payinprice` decimal(20,2) DEFAULT '0.00' COMMENT '充值金额',
    `sut_winlose` decimal(20,2) DEFAULT '0.00' COMMENT '输赢金额',
    `sut_playprice` decimal(10,2) DEFAULT '0.00' COMMENT '投注金额',
    `sut_playwin` int(255) DEFAULT '0' COMMENT '投注赢次数',
    `sut_playlose` int(255) DEFAULT '0' COMMENT '投注输次数',
    KEY `sut_dt` (`sut_dt`),
    KEY `sut_uid` (`sut_uid`),
    KEY `sut_dw` (`sut_dw`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户统计用中间数据表';
SQL;
            $this->execute($sql);
        }
        //用户统计用中间数据表
        if (!$this->existTable("dq_stat_user_midtemp_temp")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_midtemp_temp` (
    `sut_uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户UID',
    `sut_reg` int(11) DEFAULT '0' COMMENT '是否注册',
    `sut_login` int(11) DEFAULT '0' COMMENT '是否登录',
    `sut_active` int(255) DEFAULT '0' COMMENT '是否活跃 0 或天数',
    `sut_loss` int(255) DEFAULT '1' COMMENT '是否流失 0或天数',
    `sut_rekeep` int(255) DEFAULT '0' COMMENT '是否激活（即重新活跃）',
    `sut_game` int(255) DEFAULT '0' COMMENT '是否游戏',
    `sut_payin` int(255) DEFAULT '0' COMMENT '是否充值 0或次数',
    `sut_withraw` int(255) DEFAULT '0' COMMENT '是否提现 0或次数',
    `sut_balance` decimal(20,2) DEFAULT '0.00' COMMENT '当天余额',
    `sut_withrawprice` decimal(20,2) DEFAULT '0.00' COMMENT '提现金额',
    `sut_payinprice` decimal(20,2) DEFAULT '0.00' COMMENT '充值金额',
    `sut_winlose` decimal(20,2) DEFAULT '0.00' COMMENT '输赢金额',
    `sut_playprice` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额',
    `sut_playwin` int(255) DEFAULT '0' COMMENT '投注赢次数',
    `sut_playlose` int(255) DEFAULT '0' COMMENT '投注输次数',
PRIMARY KEY (`sut_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户统计用中间数据表';
SQL;
            $this->execute($sql);
        }
        //用户充值次数和金额分布图
        if (!$this->existTable("dq_stat_user_payincnt")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_payincnt` (
    `supc_dt` int(255) NOT NULL COMMENT '日或月',
    `supc_dw` int(255) DEFAULT '0' COMMENT ' 周',
    `supc_one` int(255) DEFAULT '0' COMMENT '充值次数区间一段，ENV配置',
    `supc_two` int(255) DEFAULT '0' COMMENT '二段',
    `supc_three` int(255) DEFAULT '0',
    `supc_four` int(255) DEFAULT '0',
    `supc_five` int(255) DEFAULT '0',
    `supc_six` int(255) DEFAULT '0',
    `supc_seven` int(255) DEFAULT '0',
    `supc_pone` int(255) DEFAULT '0' COMMENT '充值金额区间一段，ENV配置',
    `supc_ptwo` int(255) DEFAULT '0' COMMENT '二段',
    `supc_pthree` int(255) DEFAULT '0',
    `supc_pfour` int(255) DEFAULT '0',
    `supc_pfive` int(255) DEFAULT '0',
    `supc_psix` int(255) DEFAULT '0',
    `supc_pseven` int(255) DEFAULT '0',
    KEY `supc_dt` (`supc_dt`),
    KEY `supc_dw` (`supc_dw`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户充值次数和金额分布图';
SQL;
            $this->execute($sql);
        }
        //注册用户首次充值周期统计表
        if (!$this->existTable("dq_stat_user_payindays")) {
            $sql = <<<SQL
CREATE TABLE `dq_stat_user_payindays` (
    `sup_dt` int(255) NOT NULL COMMENT '日',
    `sup_user` int(255) DEFAULT '0' COMMENT '注册用户数',
    `sup_one` int(255) DEFAULT '0' COMMENT '首日充值数',
    `sup_two` int(255) DEFAULT '0' COMMENT '次日充值数',
    `sup_three` int(255) DEFAULT '0' COMMENT '三日充值数',
    `sup_four` int(255) DEFAULT '0' COMMENT '四日内充值数',
    `sup_five` int(255) DEFAULT '0' COMMENT '五日内充值数',
    `sup_six` int(255) DEFAULT '0' COMMENT '六日内充值数',
    `sup_week` int(255) DEFAULT '0' COMMENT '周内充值数',
    `sup_fifteen` int(255) DEFAULT '0' COMMENT '半月内充值数',
    `sup_month` int(255) DEFAULT '0' COMMENT '月内充值数',
    PRIMARY KEY (`sup_dt`),
    KEY `sup_dt` (`sup_dt`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='注册用户首次充值周期统计表';
SQL;
            $this->execute($sql);
        }
    }
}