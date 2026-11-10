<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver163 extends VersionBase
{

    public function run()
    {
        //火箭活动记录导出权限
        $pid = $this->getNodeIdByNodeUrl('/huodongguanli/rocketActivityRecord');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'export', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '导出',
                    'node_url' => 'export',
                    'path' => 'export',
                    'name' => 'export',
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