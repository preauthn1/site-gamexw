<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver232 extends VersionBase
{

    public function run()
    {
        if (!$this->existTableField("dq_user_withdrawal", "check_time")){
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `check_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '审核时间';";
            $this->execute($sql);
        }


        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '支付平台' AND `node_url` = '/payset/list'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '查询余额' AND `node_url` = 'query_balance' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`, `path`, `name`, `is_menu`) VALUES ('查询余额', 'query_balance', '{$pid}','{$time}','{$time}', 'query_balance', 'query_balance', 1);";
                $this->execute($sql);
            }
        }
    }
}