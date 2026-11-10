<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver164 extends VersionBase
{

    public function run()
    {
        if (!$this->existTableField("dq_admin_node", "is_menu")) {
            $sql = "ALTER TABLE `dq_admin_node` ADD COLUMN `is_menu` TINYINT(1) DEFAULT 0 NULL COMMENT '是否菜单 0 是 1 否';";
            $this->execute($sql);
        }
        if ($this->existTableField("dq_admin_node", "is_menu")) {
            $node = Db::name('admin_node')->select();
            foreach($node as $val){
                if(!strstr($val['node_url'], "/")){
                    if($val['is_menu'] == 0){
                        Db::name('admin_node')->where(['id' => $val['id']])->update(['is_menu' => 1]);
                    }
                }
            }

        }
    }
}