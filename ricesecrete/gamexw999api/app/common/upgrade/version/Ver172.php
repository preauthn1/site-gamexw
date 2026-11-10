<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver172 extends VersionBase
{

    public function run()
    {
        //充值-设置权限
        $pid = $this->getNodeIdByNodeUrlName('/offline/useroffline','PermissionPage');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }

        //提现-设置权限
        $pid = $this->getNodeIdByNodeUrlName('/offline/userwithdrawal','userwithdrawal');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
        //代理列表-设置权限
        $pid = $this->getNodeIdByNodeUrlName('/user/pages/agent','agentList');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }

        //人工调整-设置权限
        $pid = $this->getNodeIdByNodeUrlName('/offline/useradjust','useradjust');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }

        //代理返佣-设置权限
        $pid = $this->getNodeIdByNodeUrlName('/threeGames/gameRebate','gameRebate');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
        $pid = $this->getNodeIdByNodeUrlName('/threeGames/gameRebateOut','gameRebateOut');
        if (!empty($pid)) {
            $ret = Db::name('admin_node')->where(['path' => 'setting', 'pid' => $pid])->find();
            if (empty($ret)) {
                $in_all = [
                    'node_name' => '设置',
                    'node_url' => 'setting',
                    'path' => 'setting',
                    'name' => 'setting',
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