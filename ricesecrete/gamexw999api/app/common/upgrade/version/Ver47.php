<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;
use think\exception\PDOException;

class Ver47 extends VersionBase {

    public function run() {
        if ($this->existTableField("dq_user_games", "ug_url")) {
            $sql = "ALTER TABLE `dq_user_games` 
MODIFY COLUMN `ug_url` text  NULL COMMENT '游戏访问地址';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_configs", "tc_exchangerate")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_exchangerate` decimal(20, 8) UNSIGNED NULL DEFAULT 1 COMMENT '本平台与三方平台的币值汇率，以本平台为1';";
            $this->execute($sql);
        }

        if (!$this->existTableIndex("dq_third_configs", "idx_tc_code")) {
            $sql="ALTER TABLE `dq_third_configs` ADD UNIQUE INDEX `idx_tc_code`(`tc_code`);";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '第三方游戏' AND `node_url` = '' AND `pid` = '0' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '游戏记录' AND `node_url` = '/game_bets/list' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('游戏记录', '/game_bets/list', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
        }
        
        if (!$this->existTableField("dq_games", "g_type")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_type` INT(10) DEFAULT 0 NULL COMMENT '游戏分类ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_games", "g_sort")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_sort` INT(10) DEFAULT 0 NULL COMMENT '排序';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_games", "g_hot")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_hot` TINYINT(1) DEFAULT 0 NULL COMMENT '热门 0 否 1 是';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_games", "g_sports")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_sports` TINYINT(1) DEFAULT 0 NULL COMMENT '体育 0 否 1 是';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "proxy_topuid")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `proxy_topuid` INT(10) DEFAULT 0 NULL COMMENT '内部顶级代理ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "proxy_upuid")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `proxy_upuid` INT(10) DEFAULT 0 NULL COMMENT '内部上级代理ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "proxy_tree")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `proxy_tree` VARCHAR(64) DEFAULT '' NULL COMMENT '内部代理关系树';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "proxy_level")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `proxy_level` TINYINT(4) DEFAULT 0 NULL COMMENT '内部代理层级';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_proxy_upuid")) {
            $sql="ALTER TABLE `dq_user_agent` ADD INDEX `idx_proxy_upuid` (`proxy_upuid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_proxy_topuid")) {
            $sql="ALTER TABLE `dq_user_agent` ADD INDEX `idx_proxy_topuid` (`proxy_topuid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_proxy_tree")) {
            $sql="ALTER TABLE `dq_user_agent` ADD INDEX `idx_proxy_tree` (`proxy_tree`);";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '游戏分类' AND `node_url` = '/game_set/type'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '返水设置' AND `node_url` = '/game_set/backconfig_list' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`) VALUES ('返水设置', '/game_set/backconfig_list', '{$pid}','{$time}','{$time}');";
                $this->execute($sql);
            }
        }
    }
}
