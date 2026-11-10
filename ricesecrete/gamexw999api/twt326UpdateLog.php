<?php
exit(0);
?>
==================20241114增加三方配置表扩展字段===================
ALTER TABLE `dq_third_configs`
ADD COLUMN `tc_exfield` varchar(1000) NULL DEFAULT NULL COMMENT '扩展信息存放字段，比如自已拼接游戏地址的URL前缀等，自定义，建议使用JSON' AFTER `tc_winning_fee`;

==================20241006增加赠送用户免费游戏的功能========================
CREATE TABLE `dq_game_apis_extpl` (
`gae_id` int NOT NULL AUTO_INCREMENT,
`gae_tcid` int NOT NULL COMMENT '平台ID',
`gae_gid` int NOT NULL DEFAULT '0' COMMENT '游戏ID，0为通用',
`gae_apiname` varchar(32)  NOT NULL COMMENT 'API名',
`gae_forminfo` varchar(1000)  DEFAULT NULL COMMENT '填写此模板数据时的提示性文字，将出现在使用此模板的表单上',
`gae_param_gettpl` varchar(1000)  DEFAULT NULL COMMENT '补充的URL参数模板,querystring格式',
`gae_param_posttpl` varchar(4000)  DEFAULT NULL COMMENT '补充的POST参数模板,JSON格式',
`gae_param_headtpl` varchar(4000)  DEFAULT NULL COMMENT '补充的HEADER参数模板,JSON格式',
`gae_cfg` varchar(4000)  DEFAULT NULL COMMENT '自定义配置保存字段,null或JSON',
PRIMARY KEY (`gae_id`) USING BTREE,
UNIQUE KEY `gae_gid` (`gae_gid`,`gae_apiname`,`gae_tcid`) USING BTREE,
KEY `gae_tcid` (`gae_tcid`) USING BTREE,
KEY `gae_apiname` (`gae_apiname`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='三方平台API补充参数模板表，用于某些需要动态手动输入参数的情况，此表中的数据将会合并到原API参数中（覆盖）';


CREATE TABLE `dq_user_freebets` (
`ufg_id` int NOT NULL AUTO_INCREMENT COMMENT '同时作为给三方平台的唯一标识,标签为freesn',
`ufg_uid` int NOT NULL COMMENT '用户ID',
`ufg_tcid` int NOT NULL COMMENT '平台ID',
`ufg_gid` int NOT NULL COMMENT '游戏ID',
`ufg_freerounds` int DEFAULT '1' COMMENT '赠送局数，默认1，此值会用于附加参数模板，标签为freebetRounds',
`ufg_intime` int NOT NULL COMMENT '赠送时间，此值会用于附加参数模板，标签为freebetStarttime',
`ufg_state` int DEFAULT '0' COMMENT '赠送状态 0失败 1成功',
`ufg_endfreetime` int DEFAULT '0' COMMENT '有效期，此值会用于附加参数模板，标签为 freebetEndtime 0表示不限制',
`ufg_pricecount` decimal(20,4) DEFAULT NULL COMMENT '单局游戏总赠送投注金额，需要手动填写',
`ufg_roundspricecount` decimal(20,4) DEFAULT '0.0000' COMMENT '总赠送金额=局数*单局金额，自动计算',
`ufg_usedprice` decimal(20,4) DEFAULT '0.0000' COMMENT '某些游戏一局有多注的时候可能需要用到字个字段记录奖励余额',
`ufg_betsinfo` varchar(4000)  DEFAULT NULL COMMENT '投注信息，根据API模板写入',
`ufg_usedrounds` int DEFAULT '0' COMMENT '核销注数',
`ufg_staffid` int DEFAULT NULL COMMENT '操作管理员ID',
PRIMARY KEY (`ufg_id`) USING BTREE,
KEY `ufg_uid` (`ufg_uid`) USING BTREE,
KEY `ufg_tcid` (`ufg_tcid`) USING BTREE,
KEY `ufg_gid` (`ufg_gid`) USING BTREE,
KEY `ufg_intime` (`ufg_intime`) USING BTREE,
KEY `ufg_pricecount` (`ufg_pricecount`) USING BTREE,
KEY `ufg_roundspricecount` (`ufg_roundspricecount`),
KEY `ufg_freerounds` (`ufg_freerounds`),
KEY `ufg_usedrounds` (`ufg_usedrounds`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='赔送用户三方平台免费游戏记录表';

CREATE TABLE `dq_user_freebets_used` (
`ufu_id` int NOT NULL AUTO_INCREMENT,
`ufu_ufgid` int NOT NULL COMMENT '免费游戏赠送ID',
`ufu_gbid` int DEFAULT '0' COMMENT '核销注单ID',
`ufu_gbsn` varchar(64)  NOT NULL COMMENT '核销注单SN',
`ufu_overtime` int DEFAULT NULL COMMENT '核销时间冗余gb_overtime',
`ufu_tcid` int NOT NULL COMMENT '三方平台ID冗余',
`ufu_gid` int NOT NULL COMMENT '游戏ID冗余',
`ufu_uid` int NOT NULL COMMENT '用户ID冗余',
`ufu_winprice` decimal(20,4) DEFAULT '0.0000' COMMENT '赢金额',
PRIMARY KEY (`ufu_id`) USING BTREE,
UNIQUE KEY `ufu_ufgid` (`ufu_ufgid`,`ufu_gbsn`),
KEY `ufu_gbid` (`ufu_gbid`),
KEY `ufu_gbsn` (`ufu_gbsn`),
KEY `ufu_winprice` (`ufu_winprice`),
KEY `ufu_tcid` (`ufu_tcid`),
KEY `ufu_gid` (`ufu_gid`),
KEY `ufu_uid` (`ufu_uid`),
KEY `ufu_overtime` (`ufu_overtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='赠送免费游戏核销注单表';

ALTER TABLE `big`.`dq_game_configs`
ADD COLUMN `gc_freebet_rate` int NULL DEFAULT 0 COMMENT '赠送免费游戏时的单注倍率' AFTER `gc_betlist_getsize`;

















===============20241017增加支持流水扣减按真金和彩金分开及支持流水扣减比例按分类设置========
ALTER TABLE `dq_user_award_cfgs`
ADD COLUMN `uac_balancetype` int(255) NULL DEFAULT 1 COMMENT '流水要求（/赠送金额）真金0还是彩金1' AFTER `uac_game_type_ids`;

ALTER TABLE `dq_user_award_temp`
MODIFY COLUMN `uat_vaildwater` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT '阶段有效流水（剩余）' AFTER `uat_gid`,
MODIFY COLUMN `uat_subwater` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '单次统计扣减额中间结果（避免多表更新时前字段值计算结果会影响后值）' AFTER `uat_vaildwater`,
ADD COLUMN `uat_validcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '阶段有彩金流水（剩余），真金彩金流水分开计算时使用' AFTER `uat_subwater`,
ADD COLUMN `uat_subcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '单次统计扣减额中间结果（彩金）' AFTER `uat_validcjwater`;

ALTER TABLE `dq_user_award_logs`
MODIFY COLUMN `ual_subwater` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '此游戏扣减（贡献）流水' AFTER `ual_gid`,
ADD COLUMN `ual_subcjwater` decimal(20, 4) NULL DEFAULT 0 COMMENT '此游戏扣减（贡献）彩金流水' AFTER `ual_subwater`;

CREATE TABLE `dq_game_water_rates` (
`gwr_gid` int(11) NOT NULL,
`gwr_gtid` int(11) NOT NULL,
`gwr_water_rate` decimal(20,4) DEFAULT '1.0000',
`gwr_cjwater_rate` decimal(20,4) DEFAULT '1.0000',
PRIMARY KEY (`gwr_gid`),
KEY `gwr_gtid` (`gwr_gtid`),
KEY `gwr_water_rate` (`gwr_water_rate`),
KEY `gwr_cjwater_rate` (`gwr_cjwater_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏游戏水折扣比率表，扣减流水时将按照此比率实际扣减流水';

ENV新增：
;0默认 扣减流水按1倍比率，先扣指定游戏游戏流水再扣真金流水再扣彩金流水
;1 扣减流水区分真金有效投注和彩金有效投注，且按分类游戏设置的比率折扣后再扣减
WATER_SUB_RATE_TYPE=1

================20240905在线用户每天一条记录==========
ALTER TABLE `dq_user_online`
ADD COLUMN `uo_dt` int(11) NULL COMMENT '日期' FIRST,
DROP PRIMARY KEY,
DROP INDEX `uo_nowpage`,
DROP INDEX `uo_nowgame`,
DROP INDEX `uo_onstate`,
DROP INDEX `uo_nowstate`,
ADD INDEX `uo_nowpage`(`uo_nowpage`) USING BTREE,
ADD INDEX `uo_nowgame`(`uo_nowgame`) USING BTREE,
ADD INDEX `uo_onstate`(`uo_onstate`) USING BTREE,
ADD INDEX `uo_nowstate`(`uo_nowstate`) USING BTREE,
ADD INDEX(`uo_dt`) USING BTREE,
ADD INDEX(`uo_uid`) USING BTREE,
ADD UNIQUE INDEX(`uo_dt`, `uo_uid`) USING BTREE,
ENGINE = InnoDB;
ALTER TABLE `dq_user_online_temp`
ADD COLUMN `uot_dt` int(11) NULL COMMENT '日期' FIRST,
DROP PRIMARY KEY,
ADD INDEX(`uot_dt`),
ADD INDEX(`uot_uid`),
ENGINE = InnoDB;
update dq_user_online set uo_dt=FROM_UNIXTIME(uo_intime,'%Y%m%d')
where uo_dt is null
==================20240817增加三方平台转账是否要求整数的配置=======
ALTER TABLE `dq_third_configs`
ADD COLUMN `tc_exchangeint` int NULL DEFAULT 0 COMMENT '三方平台转帐是否要求整数 0不要求 1要求' AFTER `tc_timezone`;
===============20240330增加运营报表支持之一阶段（用户报表和财务报表）=============
SQL:
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
`sut_gamesum` int(255) DEFAULT '0' COMMENT '游戏累计次数',
`sut_payin` int(255) DEFAULT '0' COMMENT '是否充值 0或次数',
`sut_payinsum` int(255) DEFAULT '0' COMMENT '充值累计次数',
`sut_withraw` int(255) DEFAULT '0' COMMENT '是否提现 0或次数',
`sut_withrawsum` int(255) DEFAULT '0' COMMENT '提现累计次数',
`sut_balance` decimal(20,2) DEFAULT '0.00' COMMENT '当天余额',
`sut_withrawprice` decimal(20,2) DEFAULT '0.00' COMMENT '提现金额',
`sut_withrawpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '提现累计金额',
`sut_payinprice` decimal(20,2) DEFAULT '0.00' COMMENT '充值金额',
`sut_payinpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '充值累计金额',
`sut_winlose` decimal(20,2) DEFAULT '0.00' COMMENT '输赢金额',
`sut_winlosesum` decimal(20,2) DEFAULT '0.00' COMMENT '输赢累计金额',
`sut_playprice` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额',
`sut_playpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '投注累计金额',
`sut_playwin` int(255) DEFAULT '0' COMMENT '投注赢次数',
`sut_playwinsum` int(255) DEFAULT '0' COMMENT '投注累计赢次数',
`sut_playlose` int(255) DEFAULT '0' COMMENT '投注输次数',
`sut_playlosesum` int(255) DEFAULT '0' COMMENT '投注累计输次数',
KEY `sut_dt` (`sut_dt`),
KEY `sut_uid` (`sut_uid`),
KEY `sut_dw` (`sut_dw`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户统计用中间数据表';


CREATE TABLE `dq_stat_user_midtemp_temp` (
`sut_uid` int(11) DEFAULT '0' COMMENT '用户UID',
`sut_reg` int(11) DEFAULT '0' COMMENT '是否注册',
`sut_login` int(11) DEFAULT '0' COMMENT '是否登录',
`sut_active` int(255) DEFAULT '0' COMMENT '是否活跃 0 或天数',
`sut_loss` int(255) DEFAULT '1' COMMENT '是否流失 0或天数',
`sut_rekeep` int(255) DEFAULT '0' COMMENT '是否激活（即重新活跃）',
`sut_game` int(255) DEFAULT '0' COMMENT '是否游戏(投注次数）',
`sut_gamesum` int(255) DEFAULT '0' COMMENT '游戏累计次数',
`sut_payin` int(255) DEFAULT '0' COMMENT '是否充值 0或次数',
`sut_payinsum` int(255) DEFAULT '0' COMMENT '充值累计次数',
`sut_withraw` int(255) DEFAULT '0' COMMENT '是否提现 0或次数',
`sut_withrawsum` int(255) DEFAULT '0' COMMENT '提现累计次数',
`sut_balance` decimal(20,2) DEFAULT '0.00' COMMENT '当天余额',
`sut_withrawprice` decimal(20,2) DEFAULT '0.00' COMMENT '提现金额',
`sut_withrawpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '提现累计金额',
`sut_payinprice` decimal(20,2) DEFAULT '0.00' COMMENT '充值金额',
`sut_payinpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '充值累计金额',
`sut_winlose` decimal(20,2) DEFAULT '0.00' COMMENT '输赢金额',
`sut_winlosesum` decimal(20,2) DEFAULT '0.00' COMMENT '输赢累计金额',
`sut_playprice` decimal(20,2) DEFAULT '0.00' COMMENT '投注金额',
`sut_playpricesum` decimal(20,2) DEFAULT '0.00' COMMENT '投注累计金额',
`sut_playwin` int(255) DEFAULT '0' COMMENT '投注赢次数',
`sut_playwinsum` int(255) DEFAULT '0' COMMENT '投注累计赢次数',
`sut_playlose` int(255) DEFAULT '0' COMMENT '投注输次数',
`sut_playlosesum` int(255) DEFAULT '0' COMMENT '投注累计输次数',
KEY `sut_uid` (`sut_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='用户统计用中间数据表';

CREATE TABLE `dq_stat_distribution` (
`sd_dt` int(255) DEFAULT '0' COMMENT '统计日期',
`sd_type` varchar(32) DEFAULT NULL COMMENT '主指标，比如：生存周期',
`sd_subtype` varchar(16) DEFAULT NULL COMMENT '副指标，比如：贡献度',
`sd_interval_a` decimal(20,4) DEFAULT '0.0000' COMMENT '主指标段数值',
`sd_interval_av` decimal(20,4) DEFAULT '0.0000' COMMENT '副指标段数值',
`sd_interval_b` decimal(20,4) DEFAULT '0.0000',
`sd_interval_bv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_c` decimal(20,4) DEFAULT '0.0000',
`sd_interval_cv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_d` decimal(20,4) DEFAULT '0.0000',
`sd_interval_dv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_e` decimal(20,4) DEFAULT '0.0000',
`sd_interval_ev` decimal(20,4) DEFAULT '0.0000',
`sd_interval_f` decimal(20,4) DEFAULT '0.0000',
`sd_interval_fv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_g` decimal(20,4) DEFAULT '0.0000',
`sd_interval_gv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_h` decimal(20,4) DEFAULT '0.0000',
`sd_interval_hv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_i` decimal(20,4) DEFAULT '0.0000',
`sd_interval_iv` decimal(20,4) DEFAULT '0.0000',
`sd_interval_j` decimal(20,4) DEFAULT '0.0000',
`sd_interval_jv` decimal(20,4) DEFAULT '0.0000',
UNIQUE KEY `sd_type` (`sd_dt`,`sd_type`,`sd_subtype`) USING BTREE,
KEY `sd_dt` (`sd_dt`),
KEY `sd_type_2` (`sd_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='分布类统计报表';

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


===============20240328增加GBID缓存=============
CREATE TABLE `dq_game_bets_time` (
`ts` int(255) NOT NULL,
`gb_intime_mingbid` int(11) DEFAULT '0',
`gb_intime_maxgbid` int(11) DEFAULT '0',
`gb_bettime_mingbid` int(11) DEFAULT '0',
`gb_bettime_maxgbid` int(11) DEFAULT '0',
`gb_overtime_mingbid` int(11) DEFAULT '0',
`gb_overtime_maxgbid` int(11) DEFAULT '0',
PRIMARY KEY (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


===================20240313导出报表任务=============================
CREATE TABLE `dq_export_reports` (
`er_id` int(11) NOT NULL AUTO_INCREMENT,
`er_muid` int(11) DEFAULT '0' COMMENT '管理员ID',
`er_intime` int(11) DEFAULT NULL COMMENT '任务写入时间',
`er_filename` varchar(255) DEFAULT NULL COMMENT '文件名前缀',
`er_downloadurl` varchar(1000) DEFAULT '' COMMENT '下载文件名',
`er_process` int(255) DEFAULT '0' COMMENT '已处理量',
`er_datacount` int(255) DEFAULT '0' COMMENT '数据总量',
`er_started` int(255) DEFAULT '0' COMMENT '0未开始/排队中 1已开始',
`er_ended` int(255) DEFAULT '0' COMMENT '0未完成 1已完成 2已打包 3已上传',
`er_endtime` int(11) DEFAULT '0' COMMENT '数据生成完成时间',
`er_invalidtime` int(11) DEFAULT '0' COMMENT '失效时间',
`er_taskjson` text NOT NULL COMMENT '任务包信息',
PRIMARY KEY (`er_id`),
KEY `er_ended` (`er_ended`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

分钟任务：reportexport

后台翻译：
导出状态
导出进度
结束时间
打包状态
下载
排队中
正在导出
导出完成
等待打包
打包完成
上传完成
进度
数据
文件名


==================20240301增加恢复被偷宝箱功能，且增加统计团队二级人数功能=====================
ALTER TABLE `dq_bx_stat_teamcnt`
ADD COLUMN `bt_sndnum` int NULL DEFAULT 0 COMMENT '二级人数' AFTER `bt_fstnum`,
ADD COLUMN `bt_trdnum` int NULL DEFAULT 0 COMMENT '三级人数' AFTER `bt_sndnum`;

update dq_bx_stat_teamlog set btl_flag=0;
TRUNCATE table dq_bx_stat_teamcnt;

接口返回数据更新：
my/infobx 返回值中新增了二三级团队人数

后端两个AgentReport.php 中的 index 方法返回的数据中也增加了二三级团人人数


=============20240123更改为一级宝箱只发一次等====================
sql:
ALTER TABLE `dq_user_bx`
DROP COLUMN `bx_step`,
DROP COLUMN `bx_realnumup`,
DROP COLUMN `bx_validup`,
DROP COLUMN `bx_boxpriceup`,
DROP INDEX `bx_dt`,
ADD UNIQUE INDEX `bx_dt`(`bx_dt`, `bx_uid`) USING BTREE;

任务（与上一版一致，以下是不同的部分）
宝箱发放任务 bxTask 每日 00：00后执行，具体时间根据运营方需求定

system set :（与上一版一致，以下是不同的部分）
BX_PAY_COUNT_RECHARGE=30;//代理邀请额外奖励要求用户累积充值额算有效 type:unumber
BX_AWARD_LADDER_LIST=5000|0|0|0,10000|50|0.002|0.001,2000|80|0.002|0.001,50000|150|0.002|0.001,100000|300|0.002|0.001,300000|500|0.002|0.001,500000|800|0.002|0.00,|800000|1200|0.002|0.001,999990000|1500|0.002|0.001;//打码量额外奖励设置列表 格式：打码量阶梯值|初始奖励值|一级打码量奖励比率|二级打码量奖励比率 打码量阶梯值不含设置值在内，最后一个打码量设置值请设置无限大

---------------前端接口（与上一版一致，以下是不同的部分）--------------------
@当前用户宝箱发放历史记录（界面需说明：下午的统计数据包含上午【首充总额，团队平均首充金额】）
改：my_infobxlogs
入参：page 可选 default 1
pagesize 可选  default 10
dts 开始日期 可选 default 当天开始 格式：YYYY-mm-dd or yyyymmdd
dte 结束日期 可选 default 当天结束 格式：YYYY-mm-dd or yyyymmdd
返回值：
array(
bx_dt 日期
bx_step 上午1还是下午2  -- 此字段不再提供
bx_realnum 宝箱个数（一级有效人数）
bx_recharge 首充总额
bx_avgrecharge 团队平均首充金额
bx_boxprice 首充一级宝箱奖励金额
bx_seccntprice 首充二级人头奖励金额
bx_trdcntprice 首充三级人头奖励金额
bx_cntprice 总奖励金额
bx_payed 是否领取 1 领取 2冻结
nowdt 是否当前 1当天 0非当天
)




============================================20231115宝箱开发涉及的变更======================================
-------------------------SQL:
ALTER TABLE `dq_user` ADD COLUMN `feebalance` decimal(20, 2) NULL DEFAULT 0 COMMENT '佣金钱包余额';

CREATE TABLE `dq_user_feebalance_mx` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`add_time` bigint(20) DEFAULT '0' COMMENT '添加时间',
`uid` int(11) DEFAULT '0' COMMENT '用户id',
`old_price` decimal(20,2) DEFAULT '0.00' COMMENT '旧的金额记录',
`new_price` decimal(20,2) DEFAULT '0.00' COMMENT '新的金额记录',
`price` decimal(20,2) DEFAULT '0.00' COMMENT '交易金额',
`type` int(11) DEFAULT '1' COMMENT '1是增加2减少',
`msg` varchar(100) DEFAULT NULL COMMENT '备注',
`source_id` int(11) DEFAULT '0',
PRIMARY KEY (`id`) USING BTREE,
KEY `idx_uid` (`uid`) USING BTREE,
KEY `price` (`price`) USING BTREE,
KEY `add_time` (`add_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户佣金钱包明细，对应user.feebalance';

CREATE TABLE `dq_user_bx` (
`bx_dt` int(255) unsigned DEFAULT NULL COMMENT '宝箱日期',
`bx_uid` int(11) DEFAULT NULL COMMENT '用户ID',
`bx_num` int(11) DEFAULT '0' COMMENT '宝箱个数',
`bx_realnum` int(11) DEFAULT '0' COMMENT '实得宝箱个数',
`bx_stealnum` int(11) DEFAULT '0' COMMENT '被偷个数冗余',
`bx_stealrate` decimal(10,4) DEFAULT '0.0000' COMMENT '被偷比率冗余',
`bx_price` decimal(10,2) DEFAULT '0.00' COMMENT '单个宝箱对应金额',
`bx_cntprice` decimal(10,2) DEFAULT '0.00' COMMENT '总应派发金额',
`bx_avgrecharge` decimal(10,2) DEFAULT '0.00' COMMENT '平均团队充值金额',
`bx_payed` int(255) DEFAULT '0' COMMENT '宝箱是否派发 0未派发 1已派发 2未达条件不派发',
`bx_paytime` int(11) DEFAULT '0' COMMENT '宝箱派发时间',
UNIQUE KEY `bx_dt` (`bx_dt`,`bx_uid`),
KEY `bx_uid` (`bx_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户宝箱发放记录';

CREATE TABLE `dq_user_bxitems` (
`bxi_id` int(11) NOT NULL AUTO_INCREMENT,
`bxi_intime` int(11) NOT NULL COMMENT '写入时间',
`bxi_uid` int(11) NOT NULL COMMENT '宝箱归属用户ID',
`bxi_srcid` int(11) NOT NULL COMMENT '充值记录ID',
`bxi_subuid` int(11) NOT NULL COMMENT '充值用户ID',
`bxi_recharge` decimal(10,2) DEFAULT '0.00' COMMENT '充值金额冗余',
`bxi_steal` int(255) DEFAULT '0' COMMENT '是否被偷 0 未确定状态 1被偷 2未被偷',
PRIMARY KEY (`bxi_id`),
UNIQUE KEY `bxi_srcid` (`bxi_srcid`) USING BTREE,
KEY `bxi_uid` (`bxi_uid`),
KEY `bxi_subuid` (`bxi_subuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='赠送宝箱明细记录';

ALTER TABLE `dq_user_agent`
ADD COLUMN `bx_save` int(255) NULL DEFAULT 0 COMMENT '宝箱保护数，此数值内宝箱不会被偷，超出数值则按设置来' AFTER `proxy_level`;

CREATE TABLE `dq_user_bxex` (
`bxe_dt` int(255) unsigned DEFAULT NULL COMMENT '日期',
`bxe_uid` int(11) DEFAULT NULL COMMENT '用户ID',
`bxe_water` decimal(10,2) DEFAULT '0.00' COMMENT '总有效流水',
`bxe_baseprice` decimal(10,2) DEFAULT '0.00' COMMENT '基础奖励',
`bxe_owater` decimal(10,2) DEFAULT '0.00' COMMENT '一级有效流水',
`bxe_orate` decimal(10,4) DEFAULT '0.0000' COMMENT '一级比率',
`bxe_oprice` decimal(10,2) DEFAULT '0.00' COMMENT '一级贡献佣金',
`bxe_twater` decimal(10,2) DEFAULT '0.00' COMMENT '二级有效流水',
`bxe_trate` decimal(10,4) DEFAULT '0.0000' COMMENT '二级比率',
`bxe_tprice` decimal(10,2) DEFAULT '0.00' COMMENT '二级贡献佣金',
`bxe_cntprice` decimal(10,2) DEFAULT '0.00' COMMENT '总佣金',
`bxe_payed` int(255) DEFAULT '0' COMMENT '是否发放 0未发放 1已发放 2不予发放',
`bxe_paytime` int(11) DEFAULT '0' COMMENT '发放时间',
UNIQUE KEY `bxe_dt` (`bxe_dt`,`bxe_uid`),
KEY `bxe_uid` (`bxe_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请用户额外阶梯奖励统计、发放表';

CREATE TABLE `dq_user_bxex_logs` (
`bxel_dt` int(255) NOT NULL COMMENT '统计日期',
`bxel_proxyid` int(11) DEFAULT NULL COMMENT '代理ID',
`bxel_uid` int(11) DEFAULT NULL COMMENT '用户ID',
`bxel_payin` decimal(10,2) DEFAULT '0.00' COMMENT '用户累计充值金额',
`bxel_water` decimal(10,2) DEFAULT '0.00' COMMENT '用户有效流水',
`bxel_lvl` int(255) DEFAULT '0' COMMENT '用户所属层级',
UNIQUE KEY `bxel_dt` (`bxel_dt`,`bxel_proxyid`,`bxel_uid`),
KEY `bxel_proxyid` (`bxel_proxyid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请用户额外阶梯奖励统计明细表';

CREATE TABLE `dq_temp_fyfy_proxy_users_bx` (
`tfpu_proxyuid` int(11) DEFAULT NULL COMMENT '内部总代理ID，用户代理为0',
`tfpu_proxysubuid` int(11) DEFAULT NULL COMMENT '内部下级代理ID，或用户代理ID',
`tfpu_proxytype` int(11) DEFAULT NULL COMMENT '0内部代理 1用户代理',
`tfpu_uid` int(11) DEFAULT NULL COMMENT '需要统计分佣的用户ID',
`tfpu_comlvl` int(11) DEFAULT '0' COMMENT '相对级别，用于按级别进行分佣时的统计',
KEY `tfpu_proxyuid` (`tfpu_proxyuid`),
KEY `tfpu_proxysubuid` (`tfpu_proxysubuid`),
KEY `tfpu_uid` (`tfpu_uid`),
KEY `tfpu_comlvl` (`tfpu_comlvl`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='代理-用户关系临时表，完整树，专用于宝箱分支中处理特殊邀请奖励的';

ALTER TABLE `dq_user_withdrawal`
ADD COLUMN `balancetype` int(255) NULL DEFAULT 0 COMMENT '提现钱包类型 0余额钱包 1佣金钱包' AFTER `type`;
------------------------.ENV:

---------------SYSTEMCONFIGS:
SYS_FEE_WALLET=1;//是否开启佣金钱包  --- 当前此配置不生效，默认开启
SYS_FEE_WALLET_TRANSWATERRATE=12;//佣金钱包转余额流水倍数，默认12倍

BX_PAY_AVG_RECHARGE=30;//宝箱自动派发要求团队平均充值金额（周期内：一天） type:unumber
BX_PAY_COUNT_RECHARGE=30;//代理邀请额外奖励要求用户累积充值额算有效 type:unumber
BX_VAILD_RECHARGE=30;//赠送宝箱要求有效充值金额 type:unumber
BX_VAILD_FIRSTRECHARGE=0;//1赠送宝箱要求首充达成有效金额 0不要求首充达成有效金额（即当天内有充值超过有效金额即可） 不管配置如何，都要求用户当天是第一次在平台充值 type:uint --当前此配置不生效，默认为0
BX_AWARD_SET_LIST=9|10,14|15,19|20,24|24,29|26,3000000|30;//赠送宝箱设置列表 格式：有效客户个数（含）以内|奖励宝箱的金额 首值从客户数1开始，尾值请设置无限大 type:list
BX_STEAL_SET_LIST=30|0.2,30000|0.4;//偷宝箱上限设置 格式：人数|最高可偷比率
BX_STEAL_MODE=1;//偷宝箱模式 默认1 单次随机（每一次按比例随机是否被偷，好处是可以实时给用户显示宝箱个数，坏处是被偷的总机率离散度较高，且几乎很难达到最高值）  2统计时随机（每日统计时再随机确定比率，好处是可以方便控制偷宝箱的比率，比如可以设置最低比率，坏处是无法给用户实时展示宝箱数量--注：此方案暂未实现）
BX_AWARD_LADDER_LIST=5000|0|0|0,10000|50|0.002|0.001,2000|80|0.002|0.001,50000|150|0.002|0.001,100000|300|0.002|0.001,300000|500|0.002|0.001,500000|800|0.002|0.00,|800000|1200|0.002|0.001,999990000|1500|0.002|0.001;//打码量额外奖励设置列表 格式：打码量阶梯值|初始奖励值|一级打码量奖励比率|二级打码量奖励比率 打码量阶梯值不含设置值在内，最后一个打码量设置值请设置无限大



---------------前端接口--------------------
@获取佣金钱包余额
原：my/get_balance
返回值新增:
feebalance 佣金钱包余额
feebalance1 格式化后的佣金钱包余额

@获取当前用户佣金钱包变帐记录
增：my/feebalanceRecord
入参：page 可选 default 1
    pagesize 可选 default 10
    dts 开始日期 可选 default 当天开始 格式：YYYY-mm-dd or yyyymmdd
    dte 结束日期 可选 default 当天结束 格式：YYYY-mm-dd or yyyymmdd
    type 记录类型 可选 default null 值：null 全部 1 增加 2减少
返回值：变帐记录列表
array(
    array(id,add_time,uid,old_price,new_price,price,type,msg,source_id,username,rec_code)
)

@当前用户佣金转入余额
增：recharge/transfeetobalance
入参：price 必选 数值
返回值：钱包和佣金钱包最新余额
array(balance,feebalance)

@当前用户佣金钱包提现
增：withdraw/applyfee
用法同：withdraw/apply

@当前用户宝箱锁定金额（当天）及佣金累计总额信息
增：my/infobx
入参：无
返回值： 带1的是格式化后的金额
array(bx_money,bx_money1,fee_money,fee_money1)

----------------------------------------------- 以上是老版宝箱的 ------------------------------
================================= 以下是新版宝箱的 =======================================
=====================================SQL:
ALTER TABLE `dq_user` ADD COLUMN `feebalance` decimal(20, 2) NULL DEFAULT 0 COMMENT '佣金钱包余额';

ALTER TABLE `dq_user_agent`
ADD COLUMN `bx_save` int(255) NULL DEFAULT 0 COMMENT '宝箱保护数，此数值内宝箱不会被偷，超出数值则按设置来' AFTER `proxy_level`;

CREATE TABLE `dq_user_feebalance_mx` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`add_time` bigint(20) DEFAULT '0' COMMENT '添加时间',
`uid` int(11) DEFAULT '0' COMMENT '用户id',
`old_price` decimal(20,2) DEFAULT '0.00' COMMENT '旧的金额记录',
`new_price` decimal(20,2) DEFAULT '0.00' COMMENT '新的金额记录',
`price` decimal(20,2) DEFAULT '0.00' COMMENT '交易金额',
`type` int(11) DEFAULT '1' COMMENT '1是增加2减少',
`msg` varchar(100) DEFAULT NULL COMMENT '备注',
`source_id` int(11) DEFAULT '0',
PRIMARY KEY (`id`) USING BTREE,
KEY `idx_uid` (`uid`) USING BTREE,
KEY `price` (`price`) USING BTREE,
KEY `add_time` (`add_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户佣金钱包明细，对应user.feebalance';

CREATE TABLE `dq_temp_fyfy_proxy_users_bx` (
`tfpu_proxyuid` int(11) DEFAULT NULL COMMENT '内部总代理ID，用户代理为0',
`tfpu_proxysubuid` int(11) DEFAULT NULL COMMENT '内部下级代理ID，或用户代理ID',
`tfpu_proxytype` int(11) DEFAULT NULL COMMENT '0内部代理 1用户代理',
`tfpu_uid` int(11) DEFAULT NULL COMMENT '需要统计分佣的用户ID',
`tfpu_comlvl` int(11) DEFAULT '0' COMMENT '相对级别，用于按级别进行分佣时的统计',
KEY `tfpu_proxyuid` (`tfpu_proxyuid`),
KEY `tfpu_proxysubuid` (`tfpu_proxysubuid`),
KEY `tfpu_uid` (`tfpu_uid`),
KEY `tfpu_comlvl` (`tfpu_comlvl`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='代理-用户关系临时表，完整树，专用于宝箱分支中处理特殊邀请奖励的';

ALTER TABLE `dq_user_withdrawal`
ADD COLUMN `balancetype` int(255) NULL DEFAULT 0 COMMENT '提现钱包类型 0余额钱包 1佣金钱包' AFTER `type`;

CREATE TABLE `dq_user_bx` (
`bx_id` bigint(20) NOT NULL AUTO_INCREMENT,
`bx_dt` int(255) unsigned DEFAULT NULL COMMENT '宝箱日期',
`bx_uid` int(11) DEFAULT NULL COMMENT '用户ID',
`bx_step` int(11) DEFAULT '1' COMMENT '区分上下半天 1上半天，2下半天',
`bx_realnumup` int(255) DEFAULT '0' COMMENT '上半天实得宝箱个数冗余',
`bx_num` int(11) DEFAULT '0' COMMENT '宝箱个数',
`bx_realnum` int(11) DEFAULT '0' COMMENT '实得宝箱个数',
`bx_price` decimal(10,2) DEFAULT '0.00' COMMENT '单个宝箱对应金额',
`bx_boxprice` decimal(10,2) DEFAULT '0.00' COMMENT '宝箱派发金额',
`bx_secnum` int(11) DEFAULT '0' COMMENT '二级人头奖励个数',
`bx_secrealnum` int(11) DEFAULT '0' COMMENT '二级人头奖励实际个数',
`bx_secprice` decimal(10,2) DEFAULT '0.00' COMMENT '二级人头奖励单价',
`bx_seccntprice` decimal(10,2) DEFAULT '0.00' COMMENT '二级人头奖励总额',
`bx_trdnum` int(11) DEFAULT '0' COMMENT '三级人头奖励个数',
`bx_trdrealnum` int(11) DEFAULT '0' COMMENT '三级人头奖励实际个数',
`bx_trdprice` decimal(10,2) DEFAULT '0.00' COMMENT '三级人头奖励单价',
`bx_trdcntprice` decimal(10,2) DEFAULT '0.00' COMMENT '三级人头奖励总额',
`bx_cntprice` decimal(10,2) DEFAULT '0.00' COMMENT '总应派发金额',
`bx_recharge` decimal(10,2) DEFAULT '0.00' COMMENT '团队首充总额冗余（不含暗扣）下半天含上半天',
`bx_rechargenum` int(11) DEFAULT '0' COMMENT '团队充值人数冗余（不含暗扣）下半天含上半天',
`bx_avgrecharge` decimal(10,2) DEFAULT '0.00' COMMENT '平均团队首充金额，下半天含上半天',
`bx_payed` int(255) DEFAULT '0' COMMENT '宝箱是否派发 0未派发 1已派发 2未达条件不派发',
`bx_paytime` int(11) DEFAULT '0' COMMENT '宝箱派发时间',
`bx_fstrepayinnum` int(11) DEFAULT '0' COMMENT '一级复充次数',
`bx_fstrepayinrealnum` int(11) DEFAULT '0' COMMENT '一级实得复充次数',
`bx_fstrepayinprice` decimal(10,2) DEFAULT '0.00' COMMENT '一级复充单价',
`bx_fstrepayincntprice` decimal(10,2) DEFAULT '0.00' COMMENT '一级复充奖励总额',
`bx_secrepayinnum` int(11) DEFAULT '0' COMMENT '二级复充次数',
`bx_secrepayinrealnum` int(11) DEFAULT '0' COMMENT '二级实得复充次数',
`bx_secrepayinprice` decimal(10,2) DEFAULT '0.00' COMMENT '二级复充单价',
`bx_secrepayincntprice` decimal(10,2) DEFAULT '0.00' COMMENT '二级复充奖励总额',
`bx_fstpayin` decimal(10,2) DEFAULT '0.00' COMMENT '一级充值总额冗余（不含暗扣）',
`bx_secpayin` decimal(10,2) DEFAULT '0.00' COMMENT '二级充值总额冗余（不含暗扣）',
`bx_trdpayin` decimal(10,2) DEFAULT '0.00' COMMENT '三级充值总客冗余（不含暗扣）',
`bx_fstregnum` int(11) DEFAULT '0' COMMENT '一级注册人数',
PRIMARY KEY (`bx_id`),
UNIQUE KEY `bx_dt` (`bx_dt`,`bx_step`,`bx_uid`) USING BTREE,
KEY `bx_uid` (`bx_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='用户宝箱发放记录';

CREATE TABLE `dq_user_bxitems` (
`bxi_id` bigint(11) NOT NULL AUTO_INCREMENT,
`bxi_intime` int(11) NOT NULL COMMENT '写入时间',
`bxi_uid` int(11) NOT NULL COMMENT '宝箱归属用户ID',
`bxi_srcid` int(11) NOT NULL COMMENT '充值记录ID',
`bxi_subuid` int(11) NOT NULL COMMENT '充值用户ID',
`bxi_sublvl` int(255) DEFAULT '0' COMMENT '充值用户相对于宝箱归属用户的级别',
`bxi_recharge` decimal(10,2) DEFAULT '0.00' COMMENT '充值金额冗余',
`bxi_fstpayin` int(255) DEFAULT '0' COMMENT '首充还是复充 0首充 1复充',
`bxi_steal` int(255) DEFAULT '0' COMMENT '是否被偷 0 未确定状态 1被偷 2未被偷',
`bxi_valid` int(11) DEFAULT '0' COMMENT '是否有效 0无效 1有效 当前只使用了有效值，如更改为统计式则无效值可被启用',
PRIMARY KEY (`bxi_id`),
UNIQUE KEY `bxi_srcid` (`bxi_srcid`) USING BTREE,
KEY `bxi_uid` (`bxi_uid`),
KEY `bxi_subuid` (`bxi_subuid`),
KEY `bxi_intime` (`bxi_intime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='赠送宝箱明细记录';

ALTER TABLE `dq_user_bxitems` DROP INDEX `bxi_srcid`, ADD UNIQUE `bxi_srcid` (`bxi_srcid`, `bxi_uid`) USING BTREE;

ALTER TABLE `big`.`dq_user_bx`

ALTER TABLE `dq_user_bx`
ADD COLUMN `bx_validup` int(10) NULL DEFAULT 0 COMMENT '上半天宝箱是否达到发放标准（冗余，实际是payed的预计算）0不发放，1发放' AFTER `bx_boxprice`,
ADD COLUMN `bx_boxpriceup` decimal(10, 2) NULL DEFAULT 0 COMMENT '上半天宝箱跳档补发金额（上半天未达成时不补发）' AFTER `bx_validup`;

ALTER TABLE `dq_user_feebalance_mx`
ADD COLUMN `msgdata` varchar(1000) NULL COMMENT '备注数据' AFTER `msg`;

CREATE TABLE `dq_bx_stat_teamcnt` (
`bt_proxyuid` int(11) NOT NULL COMMENT '代理UID',
`bt_teamnum` int(11) DEFAULT '0' COMMENT '团队人数',
`bt_fstnum` int(11) DEFAULT '0' COMMENT '直属人数',
PRIMARY KEY (`bt_proxyuid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `dq_bx_stat_teamlog` (
`btl_uid` int(11) NOT NULL COMMENT '用户ID',
`btl_regdate` int(11) NOT NULL COMMENT '注册时间',
`btl_payindate` int(11) DEFAULT '0' COMMENT '首次充值时间',
`btl_fstpayid` int(11) DEFAULT '0' COMMENT '首次充值ID',
`btl_steal` int(255) DEFAULT '0' COMMENT '是否被偷 0 未被偷 1被偷',
`btl_flag` int(255) DEFAULT '0' COMMENT '统计标志',
PRIMARY KEY (`btl_uid`),
UNIQUE KEY `btl_uid` (`btl_uid`),
KEY `btl_payindate` (`btl_payindate`),
KEY `btl_fstpayid` (`btl_fstpayid`),
KEY `btl_flag` (`btl_flag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `dq_temp_bx_proxy_users` (
`tfpu_proxyuid` int(11) DEFAULT NULL COMMENT '代理ID',
`tfpu_uid` int(11) DEFAULT NULL COMMENT '用户ID',
`tfpu_comlvl` int(11) DEFAULT '0' COMMENT '用户层级',
KEY `tfpu_proxyuid` (`tfpu_proxyuid`),
KEY `tfpu_uid` (`tfpu_uid`),
KEY `tfpu_scdm` (`tfpu_comlvl`) USING HASH
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='宝箱代理团队人数统计（不含暗扣）代理-用户关系临时表';

=====================================ENV增加：
;爆单流水增加任务配置
[BoomBetWaterProcTask]
;任务执行间隔时间（秒）
step=10
;任务健康检查时间，不得小于step，亦不可时间过长
runingstep=60
;单次任务解析注单条数（每次任务之间暂停step秒）
stepbetnum=1000
;执行多少次任务后关掉任务（待重启，避免内存泄露）
runcnt=1000


=====================================SYSTEMCONFIGS:

SYS_FEE_WALLET_TRANSWATERRATE=12;//佣金钱包转余额流水倍数，默认12倍

BX_STEAL_RATE_FSTPAYIN=0.4;//首充暗扣比例 type:unumber
BX_STEAL_RATE_FSTPAYIN_COUNT=0.3;//首充总额暗扣比例，影响平均团队首充 type:unumber
BX_STEAL_LOWLIMIT_FSTPAYIN=1000;//首充总额暗扣保护，超过此值扣才开始暗扣 type:number
BX_STEAL_SAVE_GLOBAL=30;//首充宝箱全局保护数量，优先级低于单独设置的宝箱保护 type:uint
BX_STEAL_RATE_REPAYIN=0.5;//复充暗扣比例 type:unumber
//解释：由于暗扣是单次随机暗扣，因此在概率学上结果应是偏向于设置的暗扣比例
//但实际上有可能出现一定的离散区间，比如暗扣比例设置为40%，随机的结果会出散在0%~40%区间内，只是偏向于40%的可能性更大一些
//通过设置大于暗扣比例的调整值可以尽可能的让实际比例更加贴近设置的比例，但会导致随机性不明显
//建议仅当在运营过程中观察实际暗扣比例偏离设置值较远时开启此值
BX_STEAL_RATE_ADJUST=0;//暗扣调整比例，默认为0，可设置为1以下的数值（如设置小于任一暗扣比例则设置无效）此值越大，实际暗扣百分比将越贴近设置的暗扣比例。

BX_VAILD_FIRSTRECHARGE=20;//有效首充金额 type:unumber
BX_VAILD_SECRECHARGE=30;//有效复充金额 type:unumber

BX_PAY_AVG_RECHARGE=30;//宝箱自动派发要求团队平均首充金额（上半天和全天） type:unumber
BX_AWARD_SET_LIST=9|5,14|7,19|9,29|12,3000000|15;//赠送宝箱设置列表 格式：有效客户个数（含）以内|奖励宝箱的金额 首值从客户数1开始，尾值请设置无限大 type:list
BX_AWARD_SET_LIST_FST='3,1';//首充宝箱二三级人头奖励金额设置 格式：二级人头奖励，三级人头奖励 type:list
BX_AWARD_SET_LIST_SEC='3,1';//复充宝箱一二级人头奖励金额设置 格式：一级人头奖励，二级人头奖励 type:list

BOOM_BET_SAVING_RATE=1;//爆单流水倍数
BOOM_BET_PRICE_RATE=2;//爆单金额基数倍数

====================================任务
爆单分钟任务 bxBoomBetTask
宝箱发放任务 bxTask 每日 00：00后 和12：00后执行，具体时间根据运营方需求定

代理团队人数（不含暗扣）统计任务 ，频率不低于3分钟 bxTeamCntTask

---------------前端接口--------------------
@获取佣金钱包余额
原：my/get_balance
返回值新增:
feebalance 佣金钱包余额
feebalance1 格式化后的佣金钱包余额

@获取当前用户佣金钱包变帐记录
增：my/feebalanceRecord
入参：page 可选 default 1
pagesize 可选 default 10
dts 开始日期 可选 default 当天开始 格式：YYYY-mm-dd or yyyymmdd
dte 结束日期 可选 default 当天结束 格式：YYYY-mm-dd or yyyymmdd
type 记录类型 可选 default null 值：null 全部 1 增加 2减少
返回值：变帐记录列表
array(
array(id,add_time,uid,old_price,new_price,price,type,msg,source_id,username,rec_code)
)

@当前用户佣金转入余额
增：recharge/transfeetobalance
入参：price 必选 数值
返回值：钱包和佣金钱包最新余额
array(balance,feebalance)

@当前用户佣金钱包提现
增：withdraw/applyfee
用法同：withdraw/apply

@当前用户宝箱锁定金额（当天的当前时段）及佣金累计总额信息
增：my/infobx
入参：无
返回值： 带1的是格式化后的金额
array(bx_money =>宝箱锁定金额（即当前时段宝箱待发放金额）
,bx_money1
,bx_premoney=>上一阶段宝箱金额
,bx_premoney1
,bx_cntmoney=>累计宝箱金额
,bx_cntmoney1
,fee_money=>累计佣金总额
,fee_money1
,cntTeamNum=>团队总人数（排除暗扣）
,fstTeamNum=>直属团队人数（排除暗扣）
)

@当前用户宝箱发放历史记录（界面需说明：下午的统计数据包含上午【首充总额，团队平均首充金额】）
增：my_infobxlogs
入参：page 可选 default 1
    pagesize 可选  default 10
    dts 开始日期 可选 default 当天开始 格式：YYYY-mm-dd or yyyymmdd
    dte 结束日期 可选 default 当天结束 格式：YYYY-mm-dd or yyyymmdd
返回值：
array(
bx_dt 日期
bx_step 上午1还是下午2
bx_realnum 宝箱个数（一级有效人数）
bx_recharge 首充总额
bx_avgrecharge 团队平均首充金额
bx_boxprice 首充一级宝箱奖励金额
bx_seccntprice 首充二级人头奖励金额
bx_trdcntprice 首充三级人头奖励金额
bx_cntprice 总奖励金额
bx_payed 是否领取 1 领取 2冻结
nowdt 是否当前 1当天 0非当天
)

@当前用户宝箱发放历史记录的另一个版本
增：my/infobxlogsanother
入参：pagefor 可选 default 1  0表示向前翻页 1 表示向后翻页 注意，由于是时间倒排序，向后翻页是查看更早的记录，向前翻页是查看更新的记录
    pagesize 可选 default 10
    dt 可选 default 当天 格式yyyymmdd 详见下述说明
返回值：
array(
    'bx_dt'=>日期,
 'regnum'=>一级注册人数,
 'fstnum'=>一级首充有效人数,
 'fstmonty'=>宝箱奖励累计（一级首充奖励）,
 'recharge'=>一级有效首充金额,
 'avgrecharge'=>一级有效平均首充金额,
 --'fstpayin'=>一级总充值金额,
 'secnum'=>二级首充有效人数,
 'secprice'=>二级首充奖励累计,
 --'secpayin'=>二级总充值金额,
 'trdnum'=>三级首充有效人数,
 'trdprice'=>三级首充奖励累计,
 --'trdpayin'=>三级总充值金额,
 'refstnum'=>一级复充有效人数,
 'refstprice'=>一级复充奖励累计,
 'resecnum'=>二级复充有效人数,
 'resecprice'=>二级复充奖励累计
)
参数dt说明：
首次获取列表时此值可以不传，表示查询当前以前（向后翻页）的pagesize条数据
用户点击向前翻页时传入上次返回记录列表第一条的bx_dt
用户点击向后翻页时传入上次返回记录列表最后一条的bx_dt
-- 由于此方法需要分组统计，数据量大的时候性能会急剧下降，因此不提供传统的数据总条数和页数指示的功能，因此前端判断如果查询返回数据为空则表示没有前一页或后一页的数据了，要给用户提示，并在数据列表为空的时候不要更新前端的界面
如果想做的更人性化一点的话，前端需要提前多查一次，比如用户点向前翻页时，除正常查一次数据外，还要模拟再往前翻一页查一下数据，如果没有数据了，则不再显示前一页的翻页按钮，后一页类同
首次展示时不显示前一页的翻页按钮

@98流水进度条
增：my/waterprocess
入参：无
返回值：
array (st=>缓存更新时间,rate=>进度百分比，saved=>已完成流水，savings=>总需要流水)
* saved savings并不一定是准确的,前端仅需要使用 rate 和 savings这个两个字段的值展示百分比即可，如果savings==0则显示灰色进度条，即不需要完成流水任务，否则应展示rate%的进茺条


=============20240123更改为一级宝箱只发一次等====================
sql:
ALTER TABLE `dq_user_bx`
DROP COLUMN `bx_step`,
DROP COLUMN `bx_realnumup`,
DROP COLUMN `bx_validup`,
DROP COLUMN `bx_boxpriceup`,
DROP INDEX `bx_dt`,
ADD UNIQUE INDEX `bx_dt`(`bx_dt`, `bx_uid`) USING BTREE;

任务（与上一版一致，以下是不同的部分）
宝箱发放任务 bxTask 每日 00：00后执行，具体时间根据运营方需求定

system set :（与上一版一致，以下是不同的部分）
BX_PAY_COUNT_RECHARGE=30;//代理邀请额外奖励要求用户累积充值额算有效 type:unumber
BX_AWARD_LADDER_LIST=5000|0|0|0,10000|50|0.002|0.001,2000|80|0.002|0.001,50000|150|0.002|0.001,100000|300|0.002|0.001,300000|500|0.002|0.001,500000|800|0.002|0.00,|800000|1200|0.002|0.001,999990000|1500|0.002|0.001;//打码量额外奖励设置列表 格式：打码量阶梯值|初始奖励值|一级打码量奖励比率|二级打码量奖励比率 打码量阶梯值不含设置值在内，最后一个打码量设置值请设置无限大

---------------前端接口（与上一版一致，以下是不同的部分）--------------------
@当前用户宝箱发放历史记录（界面需说明：下午的统计数据包含上午【首充总额，团队平均首充金额】）
改：my_infobxlogs
入参：page 可选 default 1
pagesize 可选  default 10
dts 开始日期 可选 default 当天开始 格式：YYYY-mm-dd or yyyymmdd
dte 结束日期 可选 default 当天结束 格式：YYYY-mm-dd or yyyymmdd
返回值：
array(
bx_dt 日期
bx_step 上午1还是下午2  -- 此字段不再提供
bx_realnum 宝箱个数（一级有效人数）
bx_recharge 首充总额
bx_avgrecharge 团队平均首充金额
bx_boxprice 首充一级宝箱奖励金额
bx_seccntprice 首充二级人头奖励金额
bx_trdcntprice 首充三级人头奖励金额
bx_cntprice 总奖励金额
bx_payed 是否领取 1 领取 2冻结
nowdt 是否当前 1当天 0非当天
)


==================20240301增加恢复被偷宝箱功能，且增加统计团队二级人数功能=====================
ALTER TABLE `dq_bx_stat_teamcnt`
ADD COLUMN `bt_sndnum` int NULL DEFAULT 0 COMMENT '二级人数' AFTER `bt_fstnum`,
ADD COLUMN `bt_trdnum` int NULL DEFAULT 0 COMMENT '三级人数' AFTER `bt_sndnum`;

update dq_bx_stat_teamlog set btl_flag=0;
TRUNCATE table dq_bx_stat_teamcnt;

接口返回数据更新：
my/infobx 返回值中新增了二三级团队人数

后端两个AgentReport.php 中的 index 方法返回的数据中也增加了二三级团人人数



===================20240313导出报表任务=============================
CREATE TABLE `dq_export_reports` (
`er_id` int(11) NOT NULL AUTO_INCREMENT,
`er_muid` int(11) DEFAULT '0' COMMENT '管理员ID',
`er_intime` int(11) DEFAULT NULL COMMENT '任务写入时间',
`er_filename` varchar(255) DEFAULT NULL COMMENT '文件名前缀',
`er_downloadurl` varchar(1000) DEFAULT '' COMMENT '下载文件名',
`er_process` int(255) DEFAULT '0' COMMENT '已处理量',
`er_datacount` int(255) DEFAULT '0' COMMENT '数据总量',
`er_started` int(255) DEFAULT '0' COMMENT '0未开始/排队中 1已开始',
`er_ended` int(255) DEFAULT '0' COMMENT '0未完成 1已完成 2已打包 3已上传',
`er_endtime` int(11) DEFAULT '0' COMMENT '数据生成完成时间',
`er_invalidtime` int(11) DEFAULT '0' COMMENT '失效时间',
`er_taskjson` text NOT NULL COMMENT '任务包信息',
PRIMARY KEY (`er_id`),
KEY `er_ended` (`er_ended`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

分钟任务：reportexport

后台翻译：
导出状态
导出进度
结束时间
打包状态
下载
排队中
正在导出
导出完成
等待打包
打包完成
上传完成
进度
数据
文件名


===============20240328增加GBID缓存=============
CREATE TABLE `dq_game_bets_time` (
`ts` int(255) NOT NULL,
`gb_intime_mingbid` int(11) DEFAULT '0',
`gb_intime_maxgbid` int(11) DEFAULT '0',
`gb_bettime_mingbid` int(11) DEFAULT '0',
`gb_bettime_maxgbid` int(11) DEFAULT '0',
`gb_overtime_mingbid` int(11) DEFAULT '0',
`gb_overtime_maxgbid` int(11) DEFAULT '0',
PRIMARY KEY (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
