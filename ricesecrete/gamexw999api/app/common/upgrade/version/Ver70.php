<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver70 extends VersionBase {

    public function run() {
        // 任务
        if (!$this->existTable("dq_user_log")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_log`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `type` varchar(255) NOT NULL COMMENT '类型（online_time在线时长，game_time游戏时长）',
  `data` text  NOT NULL COMMENT '数据',
  `datetime` int(11) NOT NULL COMMENT '数据日期时间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '用户日志';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "cumulative_recharge")) {
            $sql = "ALTER TABLE `dq_task` ADD `cumulative_recharge` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '累计充值';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "online_time")) {
            $sql = "ALTER TABLE `dq_task` ADD `online_time` int(10) NOT NULL COMMENT '在线时长（分钟）';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "cumulative_bet")) {
            $sql = "ALTER TABLE `dq_task` ADD `cumulative_bet` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '累计下注';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_task", "game_online_time")) {
            $sql = "ALTER TABLE `dq_task` ADD `game_online_time` int(10) NOT NULL COMMENT '游戏时长（分钟）';";
            $this->execute($sql);
        }
    }
}