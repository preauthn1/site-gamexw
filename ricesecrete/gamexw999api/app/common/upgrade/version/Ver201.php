<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver201 extends VersionBase
{
    public function run()
    {
        //推广奖金设置 代理线
        if (!$this->existTableField("dq_agent_setting",'agent_id')) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `agent_id` varchar(255) NOT NULL DEFAULT '' COMMENT '领取代理id';";
            $this->execute($sql);
        }
    }
}