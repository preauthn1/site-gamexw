<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver206 extends VersionBase
{
    public function run()
    {
        //logo
        if (!$this->existTableField("dq_agent_setting",'agent_type')) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `agent_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '代理线类型（0指定代理线不能参与，1指定代理线才能参与）';";
            $this->execute($sql);
        }

    }
}