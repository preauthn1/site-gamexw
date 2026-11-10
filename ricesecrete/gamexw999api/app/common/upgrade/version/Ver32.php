<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver32 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_admin", "status")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_admin` ADD COLUMN `status` int(1) NOT NULL DEFAULT 1 COMMENT '状态 开启 1 禁用 0';";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_admin_log", "idx_uid")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_admin_log` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_withdrawal", "idx_uid")) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_offline", "idx_uid")) {
            $sql = "ALTER TABLE `dq_user_offline` ADD INDEX `idx_uid` (`uid`);";
            $this->execute($sql);
        }

        if (!$this->existTableIndex("dq_user", "idx_addtime")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD INDEX `idx_addtime` (`add_time`);";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '活动管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '抽奖报表' AND `permission_url` = '/admin/action/lottery_data_record' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('抽奖报表', '/admin/action/lottery_data_record', '{$pid}');";
                $this->execute($sql);
            }
        }
    }

}
