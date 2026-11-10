<?php

namespace app\common\upgrade\version;

class Ver177 extends VersionBase
{

    public function run()
    {
        //每人每天领取次数
        if (!$this->existTableField("dq_receive_code", "per_person_per_day_receive_num")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `per_person_per_day_receive_num` int(10) DEFAULT 0 COMMENT '每人每天限领次数，0不限制';";
            $this->execute($sql);
        }
    }
}