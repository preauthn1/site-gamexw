<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver26 extends VersionBase {
    
    public function run() {
        $full_table_name = 'dq_admin_permission';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '下层注单报表' AND `permission_url` = '/admin/agent_report/userzhudan' AND `pid` = '124' ";
        if(!$this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('下层注单报表','/admin/agent_report/userzhudan','124');";
            $this->execute($sql);
        }
    }

}
