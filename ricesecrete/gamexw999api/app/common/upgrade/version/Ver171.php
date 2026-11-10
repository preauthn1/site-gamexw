<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver171 extends VersionBase
{

    public function run()
    {
        //代理报表-查看报表-导出权限
        $pid = $this->getNodeIdByNodeUrlName('/weishang/reportdetail','reportdetail');
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