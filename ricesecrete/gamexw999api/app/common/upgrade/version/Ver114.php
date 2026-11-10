<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver114 extends VersionBase {

    public function run() {
        // 游戏反水
        if (!$this->existTableField("dq_operating", "game_back")){
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `game_back` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '游戏反水';";
            $this->execute($sql);
        }
        // 签到统计
        if (!$this->existTableField("dq_operating", "sign_reward")){
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `sign_reward` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '签到奖励';";
            $this->execute($sql);
        }
        // 代理返佣统计
        if (!$this->existTableField("dq_operating", "agent_fyfy")){
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `agent_fyfy` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '代理返佣';";
            $this->execute($sql);
        }
        // 领取码
        if (!$this->existTableField("dq_operating", "record_reward")){
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `record_reward` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '领取码';";
            $this->execute($sql);
        }
        // 未结算注单
        if (!$this->existTableField("dq_operating", "no_settle_order")){
            $sql = "ALTER TABLE `dq_operating` ADD COLUMN `no_settle_order` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '未结算注单';";
            $this->execute($sql);
        }

    }
}