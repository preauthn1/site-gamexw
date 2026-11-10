<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver199 extends VersionBase
{
    public function run()
    {
        //默认图标
        if( !$this->existTableField('dq_game_type', 'default_icon') ){
            $sql = "ALTER TABLE `dq_game_type` ADD COLUMN `default_icon` varchar(500) DEFAULT '' COMMENT '默认图标（未选中时）';";
            $this->execute($sql);
        }
    }
}