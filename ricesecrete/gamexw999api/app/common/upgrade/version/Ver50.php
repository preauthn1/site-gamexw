<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver50 extends VersionBase {
    
    public function run() {
        if (!$this->existTableField("dq_user_level", "first_user_level")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `first_user_level` int(10) DEFAULT 0 NULL COMMENT '直属会员等级';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_banner", "show_position")) {
            $sql = "ALTER TABLE `dq_banner` ADD COLUMN `show_position` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示位置 1首页 2游戏';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_bets", "gb_cflag")) {
            $sql = "ALTER TABLE dq_game_bets ADD COLUMN gb_cflag int NULL DEFAULT 0 COMMENT '注单统计标志，有值表示已经被统计到用户报表中了', ADD INDEX(gb_cflag);";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_bets", "gb_cflagt")) {
            $sql = "ALTER TABLE dq_game_bets ADD COLUMN gb_cflagt int(11) NULL DEFAULT 0 COMMENT '注单投注统计标志，有值表示已经被统计到用户报表中了', ADD INDEX(gb_cflagt);";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_banner", "sort")) {
            $sql = "ALTER TABLE dq_banner ADD COLUMN `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序从大到小';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "recharge_rebate")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `recharge_rebate` DECIMAL(10,2) DEFAULT 0.00 NULL COMMENT '充值返利 %';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "recharge_rec_rebate")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `recharge_rec_rebate` DECIMAL(10,2) DEFAULT 0.00 NULL COMMENT '上级返利 %';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_bets", "gb_gcflag")) {
            $sql = "ALTER TABLE dq_game_bets ADD COLUMN gb_gcflag int(11) NULL DEFAULT 0 COMMENT '注单结算统计标志(综合)，有值表示已经被统计到用户报表中了',ADD INDEX(gb_gcflag);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_bets", "gb_gcflagt")) {
            $sql = "ALTER TABLE dq_game_bets ADD COLUMN gb_gcflagt int(11) NULL DEFAULT 0 COMMENT '注单投注统计标志（综合），有值表示已经被统计到用户报表中了', ADD INDEX(gb_gcflagt);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_third_configs", "tc_min_bet")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_min_bet` DECIMAL(20,2) DEFAULT 0.10 NULL COMMENT '最低投注';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_third_configs", "tc_max_bet")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_max_bet` DECIMAL(20,2) DEFAULT 100000.00 NULL COMMENT '最高投注';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_games", "g_rtp")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_rtp` INT(10) DEFAULT 0 NULL COMMENT '胜率';";
            $this->execute($sql);
        }
        /*
        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '第三方游戏' AND `node_url` = '' AND `pid` = '0' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '游戏记录(时区)' AND `node_url` = '/game_bets/list_zone' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('游戏记录(时区)', '/game_bets/list_zone', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '游戏报表(时区)' AND `node_url` = '/game/report_zone' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('游戏报表(时区)', '/game/report_zone', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
        }
        */
        if (!$this->existTableField("dq_user_msg", "img")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `img` varchar(255) NULL DEFAULT '' COMMENT '图片';";
            $this->execute($sql);
        }
        
    }
}
