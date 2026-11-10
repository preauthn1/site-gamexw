<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;
use think\exception\PDOException;

class Ver49 extends VersionBase {

    public function run() {
        if (!$this->existTable("dq_game_award")) {
            $sql = "CREATE TABLE `dq_game_award`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `gaw_uid` int(11) NOT NULL DEFAULT 0 COMMENT '投注用户',
                  `gaw_intime` int(11) NOT NULL DEFAULT 0 COMMENT '系统获取记录时间',
                  `gaw_sn` varchar(64) DEFAULT NULL COMMENT '投注单号或KEY',
                  `gaw_winprice` decimal(20,2) DEFAULT 0.00 COMMENT '奖金',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "fyfy_valid_user")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `fyfy_valid_user` DECIMAL(20,2) DEFAULT 0.00 NULL COMMENT '盈利返佣有效会员充值量';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "fyfy_valid_order")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `fyfy_valid_order` DECIMAL(20,2) DEFAULT 0.00 NULL COMMENT '盈利返佣有效会员投注量';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_bets_temp", "gb_op")) {
            $sql = "ALTER TABLE `dq_game_bets_temp`  ADD COLUMN `gb_op` enum('c','u','n') NULL DEFAULT 'c' COMMENT '注单操作状态 c 创建 u 更新';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_sc", "match_id")) {
            $sql = "ALTER TABLE `dq_liansai_sc` ADD COLUMN `match_id` INT(10) DEFAULT 0 NULL COMMENT '赛事ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_type", "min_amount")) {
            $sql = "ALTER TABLE `dq_game_type` ADD COLUMN `min_amount` DECIMAL(20,2) DEFAULT 0.00 NOT NULL;";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_type", "max_amount")) {
            $sql = "ALTER TABLE `dq_game_type` ADD COLUMN `max_amount` DECIMAL(20,2) DEFAULT 0.00 NOT NULL;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_type", "max_amount")) {
            $sql = "ALTER TABLE `dq_game_type` ADD COLUMN `max_amount` DECIMAL(20,2) DEFAULT 0.00 NOT NULL;";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_game_bets_temp", "gb_cusfield")) {
            $sql = "ALTER TABLE dq_game_bets_temp 
ADD COLUMN gb_cusfield varchar(64) NULL COMMENT '一个带索引的自定义字段，对于特殊的三方注单列表处理时可以用于存放特殊的值',ADD INDEX(gb_cusfield);";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_game_bets", "gb_cusfield")) {
            $sql = "ALTER TABLE dq_game_bets 
ADD COLUMN gb_cusfield varchar(64) NULL COMMENT '一个带索引的自定义字段，对于特殊的三方注单列表处理时可以用于存放特殊的值',ADD INDEX(gb_cusfield);";
            $this->execute($sql);
        }

        
        if (!$this->existTableField("dq_game_bets", "gb_tcid")) {
            $sql = "ALTER TABLE dq_game_bets ADD COLUMN gb_tcid INT NULL COMMENT '三方平台ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_bets_temp", "gb_tcid")) {
            $sql = "ALTER TABLE dq_game_bets_temp ADD COLUMN gb_tcid INT NULL COMMENT '三方平台ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_third_configs", "tc_getbetlisttype")) {
            $sql = "ALTER TABLE dq_third_configs ADD COLUMN tc_getbetlisttype ENUM('all','bygame') NULL DEFAULT 'bygame' COMMENT '（主动模式下）三方平台获取游戏注单列表的模式：all仅按起始结束标志获取，不区分游戏； bygame：需要指定游戏标志，按起始和结束标志获取';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_game_bets", "idx_tcid")) {
            $sql="ALTER TABLE `dq_game_bets` ADD INDEX `idx_tcid` (`gb_tcid`);";
            $this->execute($sql);
        } 
        
        if (!$this->existTableIndex("dq_game_bets_temp", "idx_tcid")) {
            $sql="ALTER TABLE `dq_game_bets_temp` ADD INDEX `idx_tcid` (`gb_tcid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_level", "water")) {
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `water` DECIMAL(20,2) DEFAULT 0.00 NULL COMMENT '游戏流水';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_msg", "action_id")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `action_id` int(11) NOT NULL DEFAULT 0 COMMENT '活动ID';";
            $this->execute($sql);
        }    

        if ($this->existTableField("dq_game_bets_temp", "gb_op")) {
            $sql = "ALTER TABLE `dq_game_bets_temp` MODIFY COLUMN `gb_op` enum('c','u','n')  NULL DEFAULT 'c' COMMENT '注单操作状态 c 创建 u 更新';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_configs", "tc_currency")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_currency` varchar(16) NOT NULL DEFAULT 'usdt' COMMENT '三方平台货币单位，必须填写，请填写与三方平台接口要求的货币字估完全一致的字符串，区分大小写'";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_configs", "tc_useridentify")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_useridentify` enum('default','username','reccode','trduserid','trdusername')  NULL DEFAULT 'default' COMMENT '三方平台用户识别标识：default 默认，封装中指定的uname元数据字段，username本平台用户名 ,reccode 本平台用户推广码 trduserid 三方平台唯一标识 trdusername 三方平台用户名';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "fyfy_losstype")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `fyfy_losstype` TINYINT(1) DEFAULT 0 NULL COMMENT '0表示清零 1 表示负盈累积到下个月';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_agent", "telegram")) {
            $sql = "ALTER TABLE `dq_agent` ADD COLUMN `telegram` varchar(255) NOT NULL DEFAULT '' COMMENT 'telegram联系方式'";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_agent", "whats_app")) {
            $sql = "ALTER TABLE `dq_agent` ADD COLUMN `whats_app` varchar(255) NOT NULL DEFAULT '' COMMENT 'whats_app联系方式'";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_agent", "delete_time")) {
            $sql = "ALTER TABLE `dq_agent` ADD COLUMN `delete_time` varchar(255) NOT NULL DEFAULT '' COMMENT '删除时间'";
            $this->execute($sql);
        }
    }
}
