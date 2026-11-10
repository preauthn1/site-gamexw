<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver117 extends VersionBase {

    public function run() {
        // 客服链接
        if (!$this->existTable("dq_service_url")) {
            $sql = <<<SQL
CREATE TABLE `dq_service_url` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0telegram，1WhatsApp）',
  `mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '机型（0pc，1手机）',
  `url` varchar(500) NOT NULL COMMENT '链接',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服链接';
SQL;
            $this->execute($sql);
        }
        // 客服链接日志
        if (!$this->existTable("dq_service_url_log")) {
            $sql = <<<SQL
CREATE TABLE `dq_service_url_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT '用户uid',
  `url` varchar(500) NOT NULL COMMENT '链接',
  `ip` varchar(500) NOT NULL COMMENT 'IP',
  `address` varchar(255) NOT NULL COMMENT '地区',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `url`(`url`) USING BTREE,
  INDEX `address`(`address`) USING BTREE,
  INDEX `ip`(`ip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服链接统计';
SQL;
            $this->execute($sql);
        }

        //客服链接
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if($systemNodeId) {
            $serviceUrlNodeId = $this->getNodeIdByNodeUrl('/admin/serviceUrl');
            if (!$serviceUrlNodeId) {
                //客服链接
                $telegramNodeId = $this->addPublicParentNode(
                    '客服链接',
                    '/admin/serviceUrl',
                    'serviceUrlList',
                    '/admin/serviceUrl',
                    $systemNodeId,
                    4,
                    true
                );

                //客服链接统计
                $node = [
                    'node_name' => '统计',
                    'node_url' => 'serviceUrlLog',
                    'path' => 'serviceUrlLog',
                    'name' => 'serviceUrlLog',
                    'pid' => $telegramNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

    }
}