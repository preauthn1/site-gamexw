<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver124 extends VersionBase {

    public function run() {
        //返利比例类型（游戏救援金用到，0等级区间，1金额区间）
        if (!$this->existTableField("dq_action", "rebate_type")){
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `rebate_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '返利比例类型（游戏救援金用到，0等级区间，1金额区间）';";
            $this->execute($sql);
        }
        //奖励上限（救援金用到）
        if (!$this->existTableField("dq_action", "reward_max")){
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `reward_max` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '奖励上限（救援金用到）';";
            $this->execute($sql);
        }
    }
}