<?php

namespace app\common\upgrade\version;

class Ver249 extends VersionBase
{

    public function run()
    {
        //当前流水
        if (!$this->existTableField("dq_user_water", "current_savings")) {
            $sql = "ALTER TABLE `dq_user_water` ADD COLUMN `current_savings` decimal(20, 4) NOT NULL DEFAULT 0 COMMENT '当前流水';";
            $this->execute($sql);
        }
    }

}
