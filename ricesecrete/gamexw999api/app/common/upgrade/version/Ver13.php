<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver13 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表是否已存在
        if(!$this->existTable("dq_lottery_config")){
            $sql = "CREATE TABLE `dq_lottery_config`  (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `rule` varchar(355) NULL DEFAULT '' COMMENT '规则',
              `starttime` int(11) NOT NULL DEFAULT 0 COMMENT '抽奖开始时间',
              `endtime` int(11) NOT NULL DEFAULT 0 COMMENT '抽奖结束时间',
              `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '活动状态 0关 1开',
              `free_count` int(10) NOT NULL DEFAULT 0 COMMENT '用户免费次数',
              `required_balance` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '消耗金额',
              `limit_count` int(10) NOT NULL DEFAULT 0 COMMENT '用户每天抽奖次数',
              `img` varchar(255) NULL DEFAULT '' COMMENT '活动图',
              PRIMARY KEY (`id`)
            ) ENGINE = MyISAM AUTO_INCREMENT = 20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        // 判断表是否已存在
        if(!$this->existTable("dq_lottery_prize")){
            $sql = "CREATE TABLE `dq_lottery_prize`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '奖品类型 1金额红包 2实体物品 3谢谢参与',
                  `pic` varchar(255) NULL DEFAULT '' COMMENT '图片',
                  `title` varchar(100) NULL DEFAULT '' COMMENT '名称',
                  `amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '金额',
                  `stock` int(10) NULL DEFAULT 0 COMMENT '库存',
                  `probability` decimal(10, 2) NULL DEFAULT 0 COMMENT '中奖几率%',
                  PRIMARY KEY (`id`) 
                ) ENGINE = MyISAM AUTO_INCREMENT = 9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        // 判断表是否已存在
        if(!$this->existTable("dq_lottery_record")){
            $sql = "CREATE TABLE `dq_lottery_record`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
                  `prize_id` int(11) NOT NULL DEFAULT 0 COMMENT '奖品id',
                  `title` varchar(100) NULL DEFAULT '' COMMENT '奖品名称',
                  `time` int(11) NULL DEFAULT 0 COMMENT '抽奖时间',
                  `prize_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖品类型',
                  `price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '消费金额',
                  `is_free` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否免费 0否 1是',
                  `prize_price` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '奖品金额',
                  PRIMARY KEY (`id`)
                ) ENGINE = MyISAM AUTO_INCREMENT = 58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '活动管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '抽奖配置' AND `permission_url` = '/admin/action/lottery_config' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('抽奖配置', '/admin/action/lottery_config', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '奖品列表' AND `permission_url` = '/admin/action/lottery_prize' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('奖品列表', '/admin/action/lottery_prize', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '抽奖记录' AND `permission_url` = '/admin/action/lottery_record' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('抽奖记录', '/admin/action/lottery_record', '{$pid}');";
                $this->execute($sql);
            }
        }

        // 判断表是否已存在
        if(!$this->existTable("dq_bank_sk_set")){
         $sql = "CREATE TABLE `dq_bank_sk_set` (
                `id`  int(11) NOT NULL AUTO_INCREMENT ,
                `set_content`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '银行卡设置内容' ,
                PRIMARY KEY (`id`)
                );";
         $this->execute($sql);
        }
        
        $full_table_name = "dq_liansai_zhudan";
        $field = "update_time";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}` bigint NULL COMMENT '更新时间';";
            $this->execute($sql);
        }
        
        $full_table_name = "dq_agent_rake_back";
        $index = "idx_uid";
        if (!$this->existTableIndex($full_table_name, $index)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` 
                    ADD INDEX `idx_uid`(`uid`),
                    ADD INDEX `idx_zd_number`(`zd_number`),
                    ADD INDEX `idx_bottom_uid`(`bottom_uid`);";
            $this->execute($sql);
        }
    }

}
