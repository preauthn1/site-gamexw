<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver220 extends VersionBase
{
    public function run()
    {
        //任务指定流水
        if (!$this->existTableField("dq_task",'water_type')) {
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `water_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '流水类型（0所有，1指定平台，2指定游戏）';";
            $this->execute($sql);
        }

        // 流水类型对应id字符串
        if (!$this->existTableField("dq_task", "water_type_ids")){
            $sql = "ALTER TABLE `dq_task` ADD COLUMN `water_type_ids` varchar(255) null COMMENT '流水类型对应id字符串';";
            $this->execute($sql);
        }

        // 流水类型对应id字符串
        if (!$this->existTableField("dq_user_award_cfgs", "uac_type")){
            $sql = "ALTER TABLE `dq_user_award_cfgs` ADD COLUMN `uac_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0活动，1任务，其他看模型）';";
            $this->execute($sql);
        }
    }
}