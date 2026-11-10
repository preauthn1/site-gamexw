<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

/**
 *
 *游戏接口  返还数据是否需要解密
 * Class Ver261
 * @package app\common\upgrade\version
 */
class Ver261 extends VersionBase {

    public function run() {
        //接口数据是否需要解密 0 需要  1需要  默认解密方法DecData
        if (!$this->existTableField("dq_game_apis", "ga_decode")) {
            $sql = "ALTER TABLE `dq_game_apis` ADD COLUMN `ga_decode` tinyint(1) DEFAULT '0' COMMENT '接口数据是否需要解密 0 需要  1需要  默认解密方法DecData';";
            $this->execute($sql);
        }
    }

}
