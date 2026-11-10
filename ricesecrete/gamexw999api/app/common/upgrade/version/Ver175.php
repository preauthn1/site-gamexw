<?php

namespace app\common\upgrade\version;

class Ver175 extends VersionBase
{

    public function run()
    {
        //领取码自动领取
        if (!$this->existTableField("dq_receive_code", "is_auto")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `is_auto` tinyint(1) DEFAULT '0' COMMENT '0手动领取  1自动领取';";
            $this->execute($sql);
        }
    }
}