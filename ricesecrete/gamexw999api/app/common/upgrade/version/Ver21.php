<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver21 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        
        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '站内信' AND `permission_url` = '/admin/usermsg/list' AND `pid` = '33' ";
        if(!$this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('站内信', '/admin/usermsg/list', '33');";
            $this->execute($sql);
            $selectData = $this->getTableData($select_sql);
        }
        if(isset($selectData[0])){
            $pid = $selectData[0]['id'];

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '站内信添加' AND `permission_url` = '/admin/usermsg/add' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('站内信添加', '/admin/usermsg/add', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '站内信删除' AND `permission_url` = '/admin/usermsg/del' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('站内信删除', '/admin/usermsg/del', '{$pid}');";
                $this->execute($sql);
            }
        }
    }
        

}
