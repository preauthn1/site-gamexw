<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver197 extends VersionBase
{
    public function run()
    {
        if (!$this->existTable("dq_music")) {
            $sql = <<<SQL
CREATE TABLE `dq_music`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(200) NOT NULL COMMENT '名称',
  `file` varchar(500)NOT NULL COMMENT '文件',
  `size` decimal(10, 2) NOT NULL COMMENT '大小（单位：KB）',
  `download_num` int(10) NOT NULL DEFAULT 0 COMMENT '下载次数',
  `play_num` int(10) NOT NULL DEFAULT 0 COMMENT '播放次数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1启用，0禁用）',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序（大到小排序）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT='音乐表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_user_music")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_music`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0下载，1播放）',
  `uid` int(11) NOT NULL COMMENT '用户uid',
  `music_id` int(11) NOT NULL COMMENT '音乐id',
  `count` int(11) NOT NULL DEFAULT 0 COMMENT '对应次数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `music_id`(`music_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT='用户音乐表';
SQL;
            $this->execute($sql);
        }
        $zwNodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($zwNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/music/musicList');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '音乐列表',
                    '/music/musicList',
                    'musicList',
                    '/music/musicList',
                    $zwNodeId,
                    0,
                    true,
                    0
                );
            }
            $NodeId = $this->getNodeIdByNodeUrl('/user/pages/userMusic');
            if (!$NodeId) {
                $this->addPublicParentNode(
                    '用户音乐',
                    '/user/pages/userMusic',
                    'userMusic',
                    '/user/pages/userMusic',
                    $zwNodeId,
                    9,
                    false,
                    0,
                    0
                );
            }
        }
    }
}