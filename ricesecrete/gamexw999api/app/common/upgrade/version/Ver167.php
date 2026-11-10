<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver167 extends VersionBase
{

    public function run()
    {
        //代理返佣导出权限
        $pid = $this->getNodeIdByNodeUrl('/threeGames/gameRebate');
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
        //外部代理返佣导出权限
        $pid = $this->getNodeIdByNodeUrl('/threeGames/gameRebateOut');
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
        //返水管理导出权限
        $pid = $this->getNodeIdByNodeUrl('/threeGames/gameRebates');
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
        //代理列表导出权限
        $pid = $this->getNodeIdByNodeUrl('/user/pages/agent');
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
        //代理报表导出权限
        $pid = $this->getNodeIdByNodeUrlName('/weishang/report','sonreport');
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
        //PDD活动邀请记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/pdd/pddInviteRecord','pddInviteRecord');
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
                    'is_menu' => 1
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
        //PDD活动取款记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/pdd/pddWithdrawalRecord','pddWithdrawalRecord');
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
                    'is_menu' => 1
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
        //签到记录导出权限
        $pid = $this->getNodeIdByNodeUrlName('/sign/record','signRecord');
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
                    'is_menu' => 1
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }
    }
}