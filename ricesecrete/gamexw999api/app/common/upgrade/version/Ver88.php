<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver88 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_url", "is_menu")) {
            $sql = "ALTER TABLE `dq_url` ADD COLUMN `is_menu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为菜单链接';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_url", "icon")) {
            $sql = "ALTER TABLE `dq_url` ADD COLUMN `icon` varchar(255) NULL COMMENT '图标';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_url", "sort")) {
            $sql = "ALTER TABLE `dq_url` ADD COLUMN `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_tag", "is_activity")) {
            $sql = <<<SQL
ALTER TABLE `dq_tag` 
ADD COLUMN `is_activity` int(11) NULL DEFAULT 0 COMMENT '限制参与活动，-1禁止参与优惠活动',
ADD COLUMN `is_game` tinyint(1) NULL DEFAULT 0 COMMENT '-1时禁止玩游戏',
ADD COLUMN `is_reward` tinyint(1) NULL DEFAULT 0 COMMENT '-1时禁止获得推广奖励',
ADD COLUMN `is_task` tinyint(1) NULL DEFAULT 0 COMMENT '-1时禁止参与任务';
SQL;
            $this->execute($sql);
        }


    }
}