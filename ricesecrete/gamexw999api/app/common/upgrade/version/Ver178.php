<?php

namespace app\common\upgrade\version;

use app\model\GameBetsex;

class Ver178 extends VersionBase
{

    public function run() {

        echo "开始升级\n";
        if (!$this->existTableFullName("dq_game_bets_hot")) {
            echo "创建hot表\n";
            $gbe_id = GameBetsex::where('gbe_id','>',0)->order('gbe_id DESC')->value('gbe_id');
            if(empty($gbe_id)){
                $gbe_id = 1;
            }else{
                $gbe_id = $gbe_id + 1;
            }
            $sql = "CREATE TABLE `dq_game_bets_hot` (
  `gb_id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `gb_gid` int(11) NOT NULL COMMENT '投注游戏（为NULL表示此投注记录失去用户联系）',
  `gb_uid` int(11) NOT NULL DEFAULT '0' COMMENT '投注用户（为NULL表示此投注记录失去用户关系）',
  `gb_intime` int(11) NOT NULL COMMENT '系统获取记录时间',
  `gb_price` decimal(20,2) NOT NULL COMMENT '投注金额',
  `gb_cjprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢的金额（彩金部分）',
  `gb_sn` varchar(64) DEFAULT NULL COMMENT '投注单号或KEY',
  `gb_winlose` int(11) DEFAULT '0' COMMENT '输赢状态 0无效 1未结算 2输 3赢 4平',
  `gb_bettime` int(11) DEFAULT NULL COMMENT '投注时间',
  `gb_overtime` int(11) DEFAULT NULL COMMENT '结算时间',
  `gb_winprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢的金额',
  `gb_wincjprice` decimal(10,2) DEFAULT '0.00' COMMENT '赢的金额（彩金部分）',
  `gb_backstateks` varchar(64) DEFAULT NULL COMMENT '代理结算状态（结算方法使用）',
  `gb_cusfield` varchar(64) DEFAULT NULL COMMENT '一个带索引的自定义字段，对于特殊的三方注单列表处理时可以用于存放特殊的值',
  `gb_betinfo` text COMMENT '注单原始信息',
  `gb_tcid` int(11) DEFAULT NULL COMMENT '三方平台ID',
  `is_kj_ls` tinyint(3) unsigned DEFAULT '0' COMMENT '是否扣减流水',
  `gb_cflag` int(11) DEFAULT '0' COMMENT '注单统计标志，有值表示已经被统计到用户报表中了',
  `gb_cflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志，有值表示已经被统计到用户报表中了',
  `gb_gcflag` int(11) DEFAULT '0' COMMENT '注单结算统计标志(综合)，有值表示已经被统计到用户报表中了',
  `gb_gcflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志（综合），有值表示已经被统计到用户报表中了',
  `gb_transflag` bigint(20) DEFAULT '0' COMMENT '注单转移标志，表示注单已经从热表转移到bets表中了',
  `gb_trans_no` varchar(128) DEFAULT NULL COMMENT '交易号',
  `gb_otherwin` decimal(20,2) DEFAULT '0.00' COMMENT '统计输赢金额（派彩-投注）',
  `gb_validwater` decimal(20,2) DEFAULT '0.00' COMMENT '统计有效流水 if(派彩>投注，投注，投注-派彩）',
  PRIMARY KEY (`gb_id`) USING BTREE,
  UNIQUE KEY `b_sn` (`gb_sn`,`gb_gid`) USING BTREE,
  KEY `b_gid` (`gb_gid`) USING BTREE,
  KEY `b_uid` (`gb_uid`) USING BTREE,
  KEY `b_intime` (`gb_intime`) USING BTREE,
  KEY `b_bettime` (`gb_bettime`) USING BTREE,
  KEY `gb_overtime` (`gb_overtime`) USING BTREE,
  KEY `gb_cusfield` (`gb_cusfield`) USING BTREE,
  KEY `idx_tcid` (`gb_tcid`) USING BTREE,
  KEY `gb_transflag` (`gb_transflag`) USING BTREE,
  KEY `gb_cflag` (`gb_cflag`),
  KEY `gb_cflagt` (`gb_cflagt`),
  KEY `gb_gcflag` (`gb_gcflag`),
  KEY `gb_gcflagt` (`gb_gcflagt`),
  KEY `gb_trans_no` (`gb_trans_no`),
  KEY `gb_winlose` (`gb_winlose`)
) ENGINE=InnoDB AUTO_INCREMENT={$gbe_id} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户投注记录表'";
            $this->execute($sql);
            echo "创建完成\n";

//            $sql_charset = "ALTER TABLE dq_game_bets CONVERT TO ;";
//            $this->execute($sql_charset);

            $sql3 = "insert into dq_game_bets_hot (gb_id,gb_gid,gb_uid,gb_intime,gb_price,gb_cjprice,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_wincjprice,gb_backstateks,gb_cusfield,gb_betinfo,gb_tcid,is_kj_ls,gb_cflag,gb_cflagt,gb_gcflag,gb_gcflagt,gb_trans_no,gb_otherwin,gb_validwater)
select ta.gb_id,ta.gb_gid,ta.gb_uid,ta.gb_intime,ta.gb_price,ta.gb_cjprice,ta.gb_sn,ta.gb_winlose,ta.gb_bettime,ta.gb_overtime,ta.gb_winprice,ta.gb_wincjprice,ta.gb_backstateks,ta.gb_cusfield,ta.gb_betinfo,ta.gb_tcid,ta.is_kj_ls,ta.gb_cflag,ta.gb_cflagt,ta.gb_gcflag,ta.gb_gcflagt,ta.gb_trans_no,ta.gb_otherwin,ta.gb_validwater from dq_game_bets ta
left join dq_game_bets_hot tb on ta.gb_gid=tb.gb_gid and ta.gb_sn=tb.gb_sn
where ta.gb_winlose=1 and tb.gb_id is null";
            $this->execute($sql3);
        }else{
            echo "找到表dq_game_bets_hot，不用升级\n";
        }

        if (!$this->existTableFullName("dq_game_bets_backex")) {
            echo "创建dq_game_bets_backex\n";
            $sql = "CREATE TABLE `dq_game_bets_backex` (
  `gb_id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `gb_oldid` bigint(20) NOT NULL COMMENT '原ID',
  `gb_gid` int(11) NOT NULL COMMENT '投注游戏（为NULL表示此投注记录失去用户联系）',
  `gb_uid` int(11) NOT NULL DEFAULT '0' COMMENT '投注用户（为NULL表示此投注记录失去用户关系）',
  `gb_intime` int(11) NOT NULL COMMENT '系统获取记录时间',
  `gb_price` decimal(20,2) NOT NULL COMMENT '投注金额',
  `gb_cjprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢的金额（彩金部分）',
  `gb_sn` varchar(64) DEFAULT NULL COMMENT '投注单号或KEY',
  `gb_winlose` int(11) DEFAULT '0' COMMENT '输赢状态 0无效 1未结算 2输 3赢 4平',
  `gb_bettime` int(11) DEFAULT NULL COMMENT '投注时间',
  `gb_overtime` int(11) DEFAULT NULL COMMENT '结算时间',
  `gb_winprice` decimal(20,2) DEFAULT '0.00' COMMENT '赢的金额',
  `gb_wincjprice` decimal(10,2) DEFAULT '0.00' COMMENT '赢的金额（彩金部分）',
  `gb_backstateks` varchar(64) DEFAULT NULL COMMENT '代理结算状态（结算方法使用）',
  `gb_cusfield` varchar(64) DEFAULT NULL COMMENT '一个带索引的自定义字段，对于特殊的三方注单列表处理时可以用于存放特殊的值',
  `gb_betinfo` text COMMENT '注单原始信息',
  `gb_tcid` int(11) DEFAULT NULL COMMENT '三方平台ID',
  `gb_cflag` int(11) DEFAULT '0' COMMENT '注单统计标志，有值表示已经被统计到用户报表中了',
  `gb_cflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志，有值表示已经被统计到用户报表中了',
  `gb_gcflag` int(11) DEFAULT '0' COMMENT '注单结算统计标志(综合)，有值表示已经被统计到用户报表中了',
  `gb_gcflagt` int(11) DEFAULT '0' COMMENT '注单投注统计标志（综合），有值表示已经被统计到用户报表中了',
  `gb_trans_no` varchar(128) DEFAULT NULL COMMENT '交易号',
  `gb_otherwin` decimal(20,2) DEFAULT '0.00' COMMENT '统计输赢金额（派彩-投注）',
  `gb_validwater` decimal(20,2) DEFAULT '0.00' COMMENT '统计有效流水 if(派彩>投注，投注，投注-派彩）',
  `gb_transtime` int(11) DEFAULT NULL COMMENT '备份时间',
  PRIMARY KEY (`gb_id`) USING BTREE,
  KEY `b_gid` (`gb_gid`) USING BTREE,
  KEY `b_uid` (`gb_uid`) USING BTREE,
  KEY `b_intime` (`gb_intime`) USING BTREE,
  KEY `b_bettime` (`gb_bettime`) USING BTREE,
  KEY `gb_overtime` (`gb_overtime`) USING BTREE,
  KEY `gb_cusfield` (`gb_cusfield`) USING BTREE,
  KEY `idx_tcid` (`gb_tcid`) USING BTREE,
  KEY `b_sn` (`gb_sn`) USING BTREE,
  KEY `gb_transtime` (`gb_transtime`),
  KEY `gb_oldid` (`gb_oldid`),
  KEY `gb_trans_no` (`gb_trans_no`),
  KEY `gb_winlose` (`gb_winlose`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户投注记录表热表迁移重复记录备份表';";
            $this->execute($sql);
            echo "创建dq_game_bets_backex完成\n";
        }
    }
}