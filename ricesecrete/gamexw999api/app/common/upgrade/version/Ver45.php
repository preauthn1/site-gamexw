<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;
use think\exception\PDOException;

class Ver45 extends VersionBase {

    public function run() {

        if (!$this->existTable("dq_feedback")) {
            $sql = "CREATE TABLE `dq_feedback`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
                  `content` text NULL COMMENT '内容',
                  `add_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '时间',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '站务管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '反馈管理' AND `permission_url` = '/admin/feedback/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('反馈管理', '/admin/feedback/list', '{$pid}');";
                $this->execute($sql);
            }

        }

        if (!$this->existTableIndex("dq_game_bets", "b_sn")) {
            $sql="ALTER TABLE `dq_game_bets` ADD UNIQUE INDEX `b_sn`(`gb_sn`, `gb_gid`) USING BTREE;";
            $this->execute($sql);
        }
        /*$sql = "ALTER TABLE dq_games MODIFY COLUMN `g_code` varchar(16)  NOT NULL COMMENT '游戏代码，全英文，大写' AFTER `g_tcid`;";
        $this->execute($sql);
        $sql = "ALTER TABLE `dq_third_configs` MODIFY COLUMN `tc_tokentime` int(11) UNSIGNED NULL DEFAULT 0 COMMENT '三方平台token有效时间（秒）' AFTER `tc_token`;";
        $this->execute($sql);
        $sql = "ALTER TABLE `dq_user_thirds` DROP PRIMARY KEY;";
        $this->execute($sql);
        $sql="ALTER TABLE `dq_game_bets` DROP INDEX `b_sn`;";
        $this->execute($sql);
        $sql="ALTER TABLE `dq_game_bets` MODIFY COLUMN `gb_sn` varchar(64)  NULL DEFAULT NULL COMMENT '投注单号或KEY' AFTER `gb_price`;";
        $this->execute($sql);
        $sql="ALTER TABLE `dq_game_bets` MODIFY COLUMN `gb_backstateks` varchar(64)  NULL DEFAULT NULL COMMENT '代理结算状态（结算方法使用）' AFTER `gb_winprice`;";
        $this->execute($sql);
        $sql="ALTER TABLE `dq_game_bets` ADD UNIQUE INDEX `b_sn`(`gb_sn`, `gb_gid`) USING BTREE;";
        $this->execute($sql);*/

        $res = $this->existTableIndex('dq_agent_rake_back', 'idx_uid_status_time');
        if(!$res){
            $sql = "ALTER TABLE dq_agent_rake_back  ADD INDEX idx_uid_status_time (uid, status, create_time);";
            $this->execute($sql);
        }
    }


}
