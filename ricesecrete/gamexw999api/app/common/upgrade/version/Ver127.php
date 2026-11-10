<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver127 extends VersionBase {

    public function run() {
        //代理线id
        if (!$this->existTableField("dq_pdd_turntable", "agent_id")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理线id';";
            $this->execute($sql);
        }
        //开始时间
        if (!$this->existTableField("dq_pdd_turntable", "start_time")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `start_time` int(11) NOT NULL DEFAULT 0 COMMENT '开始时间';";
            $this->execute($sql);
        }
        //结束时间
        if (!$this->existTableField("dq_pdd_turntable", "end_time")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '结束时间';";
            $this->execute($sql);
        }
        //会员注册n天后才能显示
        if (!$this->existTableField("dq_pdd_turntable", "register_day_show")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `register_day_show` int(11) NOT NULL DEFAULT 0 COMMENT '会员注册n天后才能显示';";
            $this->execute($sql);
        }
    }
}