<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver236 extends VersionBase
{

    public function run()
    {
        // 禁止访问该平台的代理id串
        if (!$this->existTableField("dq_third_configs", "tc_disable_agent_ids")){
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_disable_agent_ids` varchar(255) NOT NULL DEFAULT '0' COMMENT '禁止访问该平台的代理id串';";
            $this->execute($sql);
        }
    }
}