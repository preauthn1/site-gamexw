<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver27 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_gonggao", "is_show_time")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_gonggao` ADD COLUMN `is_show_time` int(1) NOT NULL DEFAULT 1 COMMENT '前端是否显示时间,1显示，0不显示';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "agent_id")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `agent_id`  int(10) NOT NULL DEFAULT 0 COMMENT '绑定代理ID';";
            $this->execute($sql);
        }
    }
}
