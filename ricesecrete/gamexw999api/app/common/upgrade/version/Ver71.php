<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver71 extends VersionBase {

    public function run() {
        // 任务
        if (!$this->existTableField("dq_agent_setting", "is_check")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD `is_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要审核（0不需要，1需要）';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_agent_setting", "receive_agent_type")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD `receive_agent_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '领取代理类型（0不限制，1内部代理，2外部代理）';";
            $this->execute($sql);
        }
        if ($this->existTableField("dq_action", "base_type")) {
            $sql = "ALTER TABLE `dq_action` MODIFY COLUMN `base_type` int(1) NULL DEFAULT 1 COMMENT '1注册首存2在存优惠3一般优惠4体验金6游戏救援金';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_action", "date_type")) {
            $sql = "ALTER TABLE `dq_action` ADD `date_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '日期类型（1昨日，2上周）';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_action", "is_consist")) {
            $sql = "ALTER TABLE `dq_action` ADD `is_consist` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否在内（1是，2否）针对 代理线和会员标签';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_action", "game_type")) {
            $sql = "ALTER TABLE `dq_action` ADD `game_type` text COMMENT '游戏分类id 空为全部';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_action", "level_json")) {
            $sql = "ALTER TABLE `dq_action` ADD `level_json` text COMMENT '等级比例配置';";
            $this->execute($sql);
        }
    }
}