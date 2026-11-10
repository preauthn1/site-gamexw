<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver52 extends VersionBase {

    public function run() {
        if (!$this->existTableIndex("dq_artificial_balance", "idx_uid")) {
            $sql="ALTER TABLE `dq_artificial_balance` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_configs", "country")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `country` text NULL COMMENT '不支持国家';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "game_default_pic")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `game_default_pic` varchar(255) NULL DEFAULT '' COMMENT '游戏默认图片';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "withdrawal_pwd_check")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `withdrawal_pwd_check` tinyint(1) NULL DEFAULT '1' COMMENT '设置/修改交易密码验证：1登录密码，2邮箱，3手机';";
            $this->execute($sql);
        }

        if (!$this->existTableIndex("dq_artificial_balance", "idx_uid")) {
            $sql="ALTER TABLE `dq_artificial_balance` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "free_type")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `free_type` TINYINT(1) DEFAULT 0 NULL COMMENT '免手续费类型：0 总数 1 当天 2 当月';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_system", "trans_status")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `trans_status` TINYINT(1) DEFAULT 0 NULL COMMENT '修改交易密码验证开关 0 关 1 开';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_sc", "bet_level")) {
            $sql = "ALTER TABLE `dq_liansai_sc` ADD COLUMN `bet_level` INT(10) DEFAULT 0 NULL COMMENT '下注等级';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_sc", "ind_level")) {
            $sql = "ALTER TABLE `dq_liansai_sc` ADD COLUMN `ind_level` INT(10) DEFAULT 0 NULL COMMENT '包赔等级';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_msg", "link_title")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `link_title` varchar(255) NULL DEFAULT '' COMMENT '链接标题名称';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_msg", "link")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `link` varchar(255) NULL DEFAULT '' COMMENT '跳转链接';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_is_new")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_is_new` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否最新 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_is_optimum")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_is_optimum` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否最佳 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_is_self")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_is_self` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否内部游戏 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action", "is_hot")) {
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `is_hot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否热门 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_journalism", "lang")) {
            $sql = "ALTER TABLE `dq_journalism` ADD COLUMN `lang` varchar(30) NOT NULL DEFAULT '' COMMENT '语言';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_currency_set")) {
            $sql = "CREATE TABLE `dq_currency_set` (
                `id` int(10) NOT NULL AUTO_INCREMENT,
                `auto_rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '实时汇率 0 否 1 是',
                `auto_time` int(10) NOT NULL DEFAULT '0' COMMENT '获取实时汇率间隔时间/分钟',
                PRIMARY KEY (`id`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_game_type", "game_enter_limit")) {
            $sql = "ALTER TABLE `dq_game_type` ADD COLUMN `game_enter_limit` TINYINT(1) DEFAULT 0 NULL COMMENT '0 不限制 1 充值限制';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_balance_translog", "ubtl_status")) {
            $sql = "ALTER TABLE `dq_user_balance_translog` 
MODIFY COLUMN ubtl_status int(11) NULL DEFAULT NULL COMMENT '状态：0 空置 1准备执行 2失败 3成功 4失败已退还余额 5失败无需退回余额';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_balance_translog", "ubtl_errproc")) {
            $sql = "ALTER TABLE `dq_user_balance_translog` 
ADD COLUMN ubtl_errproc int(11) NULL COMMENT '错误处理时间';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_balance_translog", "ubtl_errprocinfo")) {
            $sql = "ALTER TABLE `dq_user_balance_translog` ADD COLUMN ubtl_errprocinfo varchar(255) NULL COMMENT '错误处理信息';";
            $this->execute($sql);
        }
    }
}
