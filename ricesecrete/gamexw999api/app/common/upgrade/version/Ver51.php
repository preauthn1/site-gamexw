<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver51 extends VersionBase {
    
    public function run() {

        $sql = "ALTER TABLE `dq_user_msg` MODIFY COLUMN `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '消息内容';";
        $this->execute($sql);

        if (!$this->existTableField("dq_system", "recharge_rebate_channel")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `recharge_rebate_channel` TINYINT(1) DEFAULT 0 NULL COMMENT '返利渠道 0 全部 1 USDT 2 法币';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "user_google_status")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `user_google_status` tinyint(1) NULL DEFAULT 0 COMMENT '用户谷歌验证 0关 1开';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_keyword")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '搜索关键字';";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_games", "g_show")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1显示 0不显示';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_games", "g_exfield")) {
            $sql = "ALTER TABLE dq_games ADD COLUMN g_exfield varchar(32) NULL COMMENT '冗余字段，用于某些特殊平台对游戏有额外的附加标志';";
            $this->execute($sql);
        }

        if($this->existTableField("dq_third_configs", "tc_token")){
            $sql1 = "ALTER TABLE `dq_third_configs` MODIFY COLUMN `tc_token` varchar(2000)  NULL DEFAULT '' COMMENT '三方平台API验证TOKEN';";
            $this->execute($sql1);
        }

        if(!$this->existTableField("dq_game_apis", "ga_urlpre")){
            $sql = "ALTER TABLE dq_game_apis 
                ADD COLUMN ga_urlpre varchar(255)  NULL DEFAULT NULL COMMENT '特别的接口地址，优先级高于三方平台中的配置' ";
            $this->execute($sql);
        }

        if($this->existTableField("dq_game_apis", "ga_type")){
            $sql = "ALTER TABLE dq_game_apis 
MODIFY COLUMN ga_type enum('getauthtoken','reg','first','other','task','getusertoken')  NULL DEFAULT NULL COMMENT '流程类型 getauthtoken 获取三方访问TOKEN reg 单独注册 first隐式注册 other其它，task任务类API，用于用户第一次与第三方游戏对接数据，reg优先';";
            $this->execute($sql);
        }

        if($this->existTableField("dq_user_thirds", "ut_token")){
            $sql = "ALTER TABLE dq_user_thirds
            MODIFY COLUMN ut_token varchar(1500)  NULL DEFAULT NULL COMMENT '用户在第三方游戏的token'";
            $this->execute($sql);
        }

        if(!$this->existTableField("dq_user_thirds", "ut_tokentime")){
            $sql = "ALTER TABLE dq_user_thirds ADD COLUMN ut_tokentime int NULL COMMENT '用户在第三方平台的token更新时间';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action_apply", "operator")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD COLUMN `operator` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人'";
            $this->execute($sql);
        }


        if ($this->existTableField("dq_currency", "recharge_rate") && $this->existTableField("dq_currency", "withdrawal_rate")) {
            $sql = "ALTER TABLE `dq_currency` 
                    MODIFY COLUMN `recharge_rate` decimal(20, 8) NOT NULL COMMENT '充值汇率' AFTER `country_code`,
                    MODIFY COLUMN `withdrawal_rate` decimal(20, 8) NOT NULL COMMENT '提现汇率' AFTER `recharge_rate`;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_admin", "usertokenkey")) {
            $sql = "ALTER TABLE `dq_admin` ADD COLUMN `usertokenkey` varchar(32) COMMENT '登录key';";
            $this->execute($sql);
        }
    
        if (!$this->existTableField("dq_admin", "usertoken")) {
            $sql = "ALTER TABLE `dq_admin` ADD COLUMN `usertoken` text(0) COMMENT '登录token';";
            $this->execute($sql);
        }
        
        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '站务管理' AND `node_url` = '' AND `pid` = '0' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '新闻分类' AND `node_url` = '/journalism/type' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('新闻分类', '/journalism/type', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '新闻列表' AND `node_url` = '/journalism/list' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('新闻列表', '/journalism/list', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
        }


        if (!$this->existTable("dq_game_pay_logs")) {
            $sql = "CREATE TABLE `dq_game_pay_logs`  (
                      `gpl_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `gpl_tcid` int(11) NOT NULL DEFAULT 0,
                      `gpl_gid` int(11) NOT NULL DEFAULT 0,
                      `gpl_uid` int(11) NOT NULL DEFAULT 0,
                      `gpl_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1加 2减 ',
                      `gpl_price` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '交易价格',
                      `gpl_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '交易单号',
                      `gpl_info` text NOT NULL COMMENT '原始数据',
                      `gpl_text` varchar(64) NOT NULL DEFAULT '' COMMENT '类型：比如 下注扣分 打赏小费',
                      `gpl_state` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1正常 2无效（比如回滚了）',
                      `gpl_create_time` datetime(6),
                      `gpl_update_time` datetime(6),
                      PRIMARY KEY (`gpl_id`) USING BTREE,
                      INDEX `gpl_tcid`(`gpl_tcid`) USING BTREE,
                      INDEX `gpl_gid`(`gpl_gid`) USING BTREE,
                      INDEX `gpl_uid`(`gpl_uid`) USING BTREE,
                      INDEX `gpl_sn`(`gpl_sn`) USING BTREE
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '游戏支付记录' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_game_cards_special")) {
            $sql = "CREATE TABLE `dq_game_cards_special`  (
                      `gs_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `gs_tcid` int(11) NOT NULL,
                      `gs_gid` int(11) NOT NULL DEFAULT 0,
                      `gs_uid` int(11) NOT NULL DEFAULT 0,
                      `gs_price` decimal(20, 2) NOT NULL COMMENT '操作金额',
                      `gs_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '单号',
                      `gs_type` varchar(255) NOT NULL COMMENT '1是增加2减少3无效',
                      `gs_type_name` varchar(255) NULL DEFAULT NULL COMMENT '收支类型 1.小费支出 2.活动派彩 ',
                      `gs_time` int(11) NULL DEFAULT NULL COMMENT '操作时间',
                      `gs_info` text NULL COMMENT '原始信息',
                      `gs_create_time` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`gs_id`) USING BTREE,
                      INDEX `gs_tcid`(`gs_tcid`) USING BTREE,
                      INDEX `gs_gid`(`gs_gid`) USING BTREE,
                      INDEX `gs_uid`(`gs_uid`) USING BTREE,
                      INDEX `gs_sn`(`gs_sn`) USING BTREE,
                      INDEX `gs_time`(`gs_time`) USING BTREE
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '特殊收支表' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_game_km_special")) {
            $sql = "CREATE TABLE `dq_game_km_special`  (
                      `gs_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `gs_tcid` int(11) NOT NULL,
                      `gs_gid` int(11) NOT NULL DEFAULT 0,
                      `gs_uid` int(11) NOT NULL DEFAULT 0,
                      `gs_price` decimal(20, 2) NOT NULL COMMENT '操作金额',
                      `gs_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '单号',
                      `gs_type` varchar(255) NOT NULL COMMENT '1是增加2减少',
                      `gs_type_name` varchar(255) NULL DEFAULT NULL COMMENT '收支类型 1.小费支出 2.活动派彩',
                      `gs_time` int(11) NULL DEFAULT NULL COMMENT '操作时间',
                      `gs_info` text NULL COMMENT '原始信息',
                      `gs_create_time` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`gs_id`) USING BTREE,
                      INDEX `gs_tcid`(`gs_tcid`) USING BTREE,
                      INDEX `gs_gid`(`gs_gid`) USING BTREE,
                      INDEX `gs_uid`(`gs_uid`) USING BTREE,
                      UNIQUE INDEX `gs_sn`(`gs_sn`) USING BTREE,
                      INDEX `gs_time`(`gs_time`) USING BTREE
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '特殊收支表' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }


        if (!$this->existTable("dq_game_wm_special")) {
            $sql = "CREATE TABLE `dq_game_wm_special`  (
                      `gs_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `gs_tcid` int(11) NOT NULL,
                      `gs_gid` int(11) NOT NULL DEFAULT 0,
                      `gs_uid` int(11) NOT NULL DEFAULT 0,
                      `gs_price` decimal(20, 2) NOT NULL COMMENT '操作金额',
                      `gs_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '单号',
                      `gs_type` varchar(255) NOT NULL COMMENT '1是增加2减少',
                      `gs_type_name` varchar(255) NULL DEFAULT NULL COMMENT '收支类型 1.小费支出',
                      `gs_time` int(11) NULL DEFAULT NULL COMMENT '操作时间',
                      `gs_info` text NULL COMMENT '原始信息',
                      `gs_create_time` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`gs_id`) USING BTREE,
                      INDEX `gs_tcid`(`gs_tcid`) USING BTREE,
                      INDEX `gs_gid`(`gs_gid`) USING BTREE,
                      INDEX `gs_uid`(`gs_uid`) USING BTREE,
                      UNIQUE INDEX `gs_sn`(`gs_sn`) USING BTREE,
                      INDEX `gs_time`(`gs_time`) USING BTREE
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '特殊收支表' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_journalism")) {
            $sql = "CREATE TABLE `dq_journalism`  (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `type_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类id',
                      `title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '标题',
                      `pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '封面图',
                      `brief` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '简介',
                      `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '内容',
                      `addtime` int(11) NOT NULL DEFAULT 0 COMMENT '添加时间',
                      `sort` int(7) NOT NULL DEFAULT 0 COMMENT '排序',
                      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0关 1开',
                      PRIMARY KEY (`id`) USING BTREE
              );";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_journalism_type")) {
            $sql = "CREATE TABLE `dq_journalism_type` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '分类名称',
                  `sort` int(5) NOT NULL DEFAULT 0 COMMENT '排序',
                  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0关 1开',
                  PRIMARY KEY (`id`) USING BTREE
              );";

            $this->execute($sql);
        }

        if (!$this->existTable("dq_user_google")) {
            $sql = "CREATE TABLE `dq_user_google` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
                      `goodsle_bind` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否绑定 0未绑定 1已绑定',
                      `google_secret` varchar(60) NOT NULL DEFAULT '' COMMENT '谷歌秘钥',
                      `login_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '登录验证 0关 1开',
                      `login_password_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '修改登录密码验证 0关 1开',
                      `withdrawal_password_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '修改提现密码验证 0关 1开',
                      `bankcard_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑定银行卡验证 0关 1开',
                      `withdrawal_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '提现验证 0关 1开',
                      PRIMARY KEY (`id`) USING BTREE
              );";
            $this->execute($sql);
        }

    }

}
