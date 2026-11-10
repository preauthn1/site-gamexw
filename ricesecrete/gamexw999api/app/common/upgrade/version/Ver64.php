<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver64 extends VersionBase {

    public function run() {

        // 彩票相关 start
        if (!$this->existTable("dq_action_type")) {
            $sql = <<<SQL
CREATE TABLE `dq_action_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '名称',
  `icon` varchar(500) NOT NULL COMMENT '图标',
  `sort` tinyint(3) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='活动类型';
SQL;
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_action", "type_id")) {
            $sql = "ALTER TABLE `dq_action` ADD `type_id` int(11)  NULL  DEFAULT 0  COMMENT '活动类型id';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_ck", "ck_r_id")) {
            $sql = "ALTER TABLE `dq_user_ck` ADD `ck_r_id` INT  NULL  DEFAULT '0'  COMMENT '充值记录ID，单笔充值活动有效';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_ck", "action_id")) {
            $sql = "ALTER TABLE `dq_user_ck` ADD `action_id` INT  NULL  DEFAULT '0'  COMMENT '活动ID';";
            $this->execute($sql);
        }

        $e_node = Db::name('admin_node')->where('node_url', '/huodongguanli/typeManage')->find();
        if(empty($e_node)) {
            $pid = Db::name('admin_node')->where('node_name', '活动管理')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '类型管理',
                    'node_url' => '/huodongguanli/typeManage',
                    'path' => '/huodongguanli/typeManage',
                    'name' => 'typeManage',
                    'pid' => $pid,
                    'sort' => 8,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }
    }
}