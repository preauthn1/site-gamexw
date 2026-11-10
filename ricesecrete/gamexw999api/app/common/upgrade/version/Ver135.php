<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver135 extends VersionBase
{

    public function run()
    {
        //任务代理id
        if ($this->existTableField("dq_task", "receive_agent_id")) {
            $sql = "ALTER TABLE dq_task MODIFY COLUMN receive_agent_id varchar(255) NOT NULL DEFAULT 0 COMMENT '领取代理id';";
            $this->execute($sql);
        }
        //签到代理id
        if ($this->existTableField("dq_sign", "agent_id")) {
            $sql = "ALTER TABLE dq_sign MODIFY COLUMN agent_id varchar(255) NOT NULL DEFAULT 0 COMMENT '领取代理id';";
            $this->execute($sql);
        }
        //活动代理id
        if ($this->existTableField("dq_action", "agent_id")) {
            $sql = "ALTER TABLE dq_action MODIFY COLUMN agent_id varchar(255) NOT NULL DEFAULT 0 COMMENT '领取代理id';";
            $this->execute($sql);
        }
        //轮播图代理id
        if ($this->existTableField("dq_banner", "agent_id")) {
            $sql = "ALTER TABLE dq_banner MODIFY COLUMN agent_id varchar(255) NOT NULL DEFAULT 0 COMMENT '领取代理id';";
            $this->execute($sql);
        }
        //pdd代理id
        if ($this->existTableField("dq_pdd_turntable", "agent_id")) {
            $sql = "ALTER TABLE dq_pdd_turntable MODIFY COLUMN agent_id varchar(255) NOT NULL DEFAULT 0 COMMENT '领取代理id';";
            $this->execute($sql);
        }
    }
}