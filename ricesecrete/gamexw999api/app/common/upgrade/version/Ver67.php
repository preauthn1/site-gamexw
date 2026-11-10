<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver67 extends VersionBase {

    public function run() {
        // 彩票相关 start
        if (!$this->existTable("dq_mirror_site")) {
            $sql = <<<SQL
CREATE TABLE `dq_mirror_site` (
  `id` int NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL COMMENT 'url',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1在线，0离线）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='镜像站点';
SQL;
            $this->execute($sql);
        }
    }
}