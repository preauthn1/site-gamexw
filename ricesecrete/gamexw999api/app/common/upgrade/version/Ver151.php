<?php

namespace app\common\upgrade\version;

class Ver151 extends VersionBase
{

    public function run()
    {
        //方式支付状态
        if (!$this->existTableField("dq_payment_type", "status")) {
            $sql = "ALTER TABLE `dq_payment_type` ADD COLUMN `status` tinyint(1) DEFAULT '1' COMMENT '状态';";
            $this->execute($sql);
        }
    }
}