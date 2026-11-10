<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver214 extends VersionBase
{
    public function run()
    {
        //红包雨IP限制
        if (!$this->existTableField("dq_red_packet",'ip_limit')) {
            $sql = "ALTER TABLE `dq_red_packet` ADD COLUMN `ip_limit` tinyint(5) NOT NUll DEFAULT 0 COMMENT '红包雨IP限制';";
            $this->execute($sql);
        }
        //总用户限制
        if (!$this->existTableField("dq_red_packet",'total_user_limit')) {
            $sql = "ALTER TABLE `dq_red_packet` ADD COLUMN `total_user_limit` int(10) NOT NUll DEFAULT 0 COMMENT '总用户参与次数限制';";
            $this->execute($sql);
        }

    }
}