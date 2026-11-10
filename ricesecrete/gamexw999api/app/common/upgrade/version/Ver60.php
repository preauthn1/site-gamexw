<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver60 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_action", "jump_url")) {
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `jump_url` varchar(100) NULL COMMENT '跳转链接';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_url")) {
            $sql = <<<SQL
CREATE TABLE `dq_url` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT '' COMMENT '名称',
  `url` varchar(50) DEFAULT '' COMMENT '路径',
  `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='活动链接';
SQL;
            $this->execute($sql);
        }

        $game_stat_node = Db::name('admin_node')->where('node_url', '/threeGames/gameStat')->find();
        if(empty($game_stat_node)) {
            $pid = Db::name('admin_node')->where('node_name', '第三方游戏')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '游戏统计',
                    'node_url' => '/threeGames/gameStat',
                    'path' => '/threeGames/gameStat',
                    'name' => 'gameStat',
                    'pid' => $pid,
                    'sort' => 91,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

        $game_stat_node = Db::name('admin_node')->where('node_url', '/url/list')->find();
        if(empty($game_stat_node)) {
            $pid = Db::name('admin_node')->where('node_name', '活动管理')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '链接管理',
                    'node_url' => '/url/list',
                    'path' => '/url/list',
                    'name' => 'urlList',
                    'pid' => $pid,
                    'sort' => 9,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }
    }
}