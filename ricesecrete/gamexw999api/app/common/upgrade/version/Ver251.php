<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver251 extends VersionBase
{

    public function run()
    {
        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '会员列表' AND `node_url` = '/user/pages/list'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $time = time();
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '批量清零彩金流水' AND `node_url` = 'cjwater' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`node_name`, `node_url`, `pid`, `create_time`, `update_time`, `path`, `name`, `is_menu`) VALUES ('批量清零彩金流水', 'cjwater', '{$pid}','{$time}','{$time}', 'cjwater', 'cjwater', 1);";
                $this->execute($sql);
            }
        }
    }
}