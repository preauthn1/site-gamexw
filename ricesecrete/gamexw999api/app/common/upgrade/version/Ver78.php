<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver78 extends VersionBase {

    public function run() {
        if (!$this->existTableFullName("dq_journalism_game")) {
            $sql = <<<SQL
CREATE TABLE `dq_journalism_game` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `journalism_id` int(11) NOT NULL COMMENT '新闻id',
  `game_id` int(11) NOT NULL COMMENT '游戏id',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '新闻游戏关联表';
SQL;
            $this->execute($sql);
        }
    }
}