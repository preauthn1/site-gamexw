<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

/**
 *
 * 游戏管理 增加电子游戏标签
 * Class Ver262
 * @package app\common\upgrade\version
 */
class Ver262 extends VersionBase {

    public function run() {
        //游戏添加 电子游戏标签
        if (!$this->existTableField("dq_games", "g_slot")) {
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_slot` tinyint(1) DEFAULT '0' COMMENT '电子游戏 0 否 1 是';";
            $this->execute($sql);
        }
    }

}
