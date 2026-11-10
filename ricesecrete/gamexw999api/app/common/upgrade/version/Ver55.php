<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver55 extends VersionBase {

    public function run() {
        if (!$this->existTable("dq_user_tag")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int DEFAULT NULL COMMENT '用户ID',
  `tag_id` int DEFAULT NULL,
  `create_time` int DEFAULT NULL,
  `update_time` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='用户标签关联表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_tag")) {
            $sql = <<<SQL
CREATE TABLE `dq_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '标签',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `is_withdraw` tinyint(1) DEFAULT '0' COMMENT '-1时禁止提现',
  `is_recharge` int DEFAULT '0' COMMENT '限制充值，-1 禁止充值，大于0时限制使用某一个充值渠道',
  `create_time` int DEFAULT NULL,
  `update_time` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='标签表';
SQL;
            $this->execute($sql);
        }


        $user_tag_node = Db::name('admin_node')->where('node_url', '/user/tag')->find();
        if(empty($user_tag_node)) {
            $pid = Db::name('admin_node')->where('node_name', '会员管理')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '用户标签',
                    'node_url' => '/user/tag',
                    'path' => '/user/tag',
                    'name' => 'user_tag',
                    'pid' => $pid,
                    'sort' => 60,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
                $node = [
                    'node_name' => '标签列表',
                    'node_url' => '/tag/list',
                    'path' => '/tag/list',
                    'name' => 'tag_list',
                    'pid' => $pid,
                    'sort' => 65,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

        if (!$this->existTableField("dq_action", "tag_id")) {
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `tag_id` int NULL DEFAULT 0 COMMENT '用户标签ID', ADD COLUMN `agent_id` int NULL DEFAULT 0 COMMENT '代理线ID';";
            $this->execute($sql);
        }
    }
}