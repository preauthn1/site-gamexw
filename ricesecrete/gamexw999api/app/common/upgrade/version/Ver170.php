<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver170 extends VersionBase
{

    public function run()
    {
        //充值删除权限菜单
        $pid = $this->getNodeIdByNodeUrl('/offline/useroffline');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'delete', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '删除',
                    'node_url' => 'delete',
                    'path' => 'delete',
                    'name' => 'delete',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
    }
}