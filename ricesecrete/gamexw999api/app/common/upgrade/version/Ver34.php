<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver34 extends VersionBase {

    public function run() {
        $full_table_name = 'dq_admin_permission';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '一键派送' AND `permission_url` = '/admin/action_apply/distribute' AND `pid` = '76' ";
        if(!$selectData = $this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('一键派送', '/admin/action_apply/distribute', '76');";
            $this->execute($sql);
        }
        
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '一键拒绝' AND `permission_url` = '/admin/agent_rakeback/refused' AND `pid` = '118' ";
        if(!$selectData = $this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('一键拒绝', '/admin/agent_rakeback/refused', '118');";
            $this->execute($sql);
        }
    }

}
