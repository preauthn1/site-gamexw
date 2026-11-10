<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver180 extends VersionBase
{

    public function run()
    {
        //留存率-导出权限
        $pid = $this->getNodeIdByNodeUrlName('/report/remain','report_remain');
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
        //运营总表-导出权限
        $pid = $this->getNodeIdByNodeUrlName('/report/reportManage','reportManage');
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