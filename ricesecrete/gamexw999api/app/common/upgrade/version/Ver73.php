<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver73 extends VersionBase {

    public function run() {
        //任务管理
        $taskNode = Db::name('admin_node')->where('node_url', '/task')->find();
        if($taskNode) {
            $taskNodeId = $taskNode['id'];
            //任务分类
            $taskTypeNode = Db::name('admin_node')->where('node_url', '/task/taskType')->find();
            if(empty($taskTypeNode)){
                //列表
                $node = [
                    'node_name' => '任务分类',
                    'node_url' => '/task/taskType',
                    'path' => '/task/taskType',
                    'name' => 'taskType',
                    'pid' => $taskNodeId,
                    'sort' => 1,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                $taskTypeNodeId = Db::name('admin_node')->insertGetId($node);
                $saveAll = [];
                //添加
                $saveAll[] = [
                    'node_name' => '添加',
                    'node_url' => 'add',
                    'path' => 'add',
                    'name' => 'add',
                    'pid' => $taskTypeNodeId,
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
                    'pid' => $taskTypeNodeId,
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
                    'pid' => $taskTypeNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                //批量添加添加，编辑，删除
                Db::name('admin_node')->insertAll($saveAll);
            }
        }

        //任务分类字段
        if (!$this->existTableField("dq_task", "task_type")) {
            $sql = "ALTER TABLE `dq_task` ADD `task_type` int(11) NOT NULL DEFAULT 0 COMMENT '任务类型';";
            $this->execute($sql);
        }

        //任务类型
        if (!$this->existTable("dq_task_type")) {
            $sql = <<<SQL
CREATE TABLE `dq_task_type`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL COMMENT '名称',
  `icon` varchar(255) NOT NULL COMMENT '图标',
  `sort` tinyint(3)  NOT NULL COMMENT '排序',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '任务分类';
SQL;
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_remain_report", "recharge_user")) {
            $sql = <<<SQL
ALTER TABLE `dq_remain_report` 
ADD COLUMN `recharge_user` int(10) NULL DEFAULT 0 COMMENT '当天首充人数' AFTER `remain_stat`,
ADD COLUMN `recharge_amount` decimal(20, 4) NULL DEFAULT 0 COMMENT '当天首充金额' AFTER `recharge_user`,
ADD COLUMN `remain_amount1` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids1`,
ADD COLUMN `remain_amount2` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids2`,
ADD COLUMN `remain_amount3` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids3`,
ADD COLUMN `remain_amount4` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids4`,
ADD COLUMN `remain_amount5` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids5`,
ADD COLUMN `remain_amount7` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids7`,
ADD COLUMN `remain_amount15` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids15`,
ADD COLUMN `remain_amount30` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids30`,
ADD COLUMN `remain_amount60` decimal(20, 4) NULL DEFAULT 0 AFTER `remain_uids60`;
SQL;
            $this->execute($sql);
        }

    }
}