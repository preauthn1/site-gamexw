<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver72 extends VersionBase {

    public function run() {
        //任务管理
        $taskNode = Db::name('admin_node')->where('node_url', '/task')->find();
        if(empty($taskNode)) {
            $node = [
                'node_name' => '任务管理',
                'node_url' => '/task',
                'path' => '/task',
                'name' => 'task',
                'icon' => 'IF-icon-jine',
                'pid' => 0,
                'sort' => 9,
                'create_time' => time(),
                'update_time' => time()
            ];
            $taskNodeId = Db::name('admin_node')->insertGetId($node);
        }else{
            $taskNodeId = $taskNode['id'];
        }
        //任务列表
        $taskListNode = Db::name('admin_node')->where('node_url', '/task/taskList')->find();
        if(empty($taskListNode)){
            //列表
            $node = [
                'node_name' => '任务列表',
                'node_url' => '/task/taskList',
                'path' => '/task/taskList',
                'name' => 'taskList',
                'pid' => $taskNodeId,
                'sort' => 9,
                'create_time' => time(),
                'update_time' => time()
            ];
            $taskListNodeId = Db::name('admin_node')->insertGetId($node);
            $saveAll = [];
            //添加
            $saveAll[] = [
                'node_name' => '添加',
                'node_url' => 'add',
                'path' => 'add',
                'name' => 'add',
                'pid' => $taskListNodeId,
                'sort' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            //编辑
            $saveAll[] = [
                'node_name' => '编辑',
                'node_url' => 'edit',
                'path' => 'edit',
                'name' => 'edit',
                'pid' => $taskListNodeId,
                'sort' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            //删除
            $saveAll[] = [
                'node_name' => '删除',
                'node_url' => 'delete',
                'path' => 'delete',
                'name' => 'delete',
                'pid' => $taskListNodeId,
                'sort' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            //设置内容
            $saveAll[] = [
                'node_name' => '设置内容',
                'node_url' => 'content',
                'path' => 'content',
                'name' => 'content',
                'pid' => $taskListNodeId,
                'sort' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            //批量添加添加，编辑，删除
            Db::name('admin_node')->insertAll($saveAll);
        }
        //任务领取记录
        $taskRecordNode = Db::name('admin_node')->where('node_url', '/task/record')->find();;
        if( empty($taskRecordNode) ){
            $node = [
                'node_name' => '领取记录',
                'node_url' => '/task/record',
                'path' => '/task/record',
                'name' => 'record',
                'pid' => $taskNodeId,
                'sort' => 8,
                'create_time' => time(),
                'update_time' => time()
            ];
            Db::name('admin_node')->insertGetId($node);
        }

    }
}