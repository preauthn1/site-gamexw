<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver168 extends VersionBase
{

    public function run()
    {
        //五福-兑换记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/wufu/withdrawalRecord','withdrawalRecord');
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

        //五福-卡片记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/wufu/cardRecord','cardRecord');
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
        //任务管理-领取记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/task/record','record');
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