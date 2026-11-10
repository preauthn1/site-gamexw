<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver90 extends VersionBase {

    public function run() {

        // 优惠表添加累积充值天数
        if (!$this->existTableField("dq_action", "cumulative_days")) {
            $sql = "ALTER TABLE `dq_action` ADD `cumulative_days` int(5) DEFAULT 0 COMMENT '累积充值天数';";
            $this->execute($sql);
        }

        // 优惠表添加每日充值金额
        if (!$this->existTableField("dq_action", "day_recharge_amount")) {
            $sql = "ALTER TABLE `dq_action` ADD `day_recharge_amount` int(11) DEFAULT 0 COMMENT '每日充值金额';";
            $this->execute($sql);
        }

        // 优惠领取记录添加下级id
        if (!$this->existTableField("dq_action_apply", "contributor")) {
            $sql = "ALTER TABLE `dq_action_apply` ADD `contributor` int(11) DEFAULT 0 COMMENT '类型为7的下级id';";
            $this->execute($sql);
        }

        // 添加月活动字段
        if (!$this->existTableField('dq_action','month_activity')){
            $sql = "ALTER TABLE `dq_action` ADD `month_activity` text COMMENT '月活动信息';";
            $this->execute($sql);
        }

        // 添加联系充值天数
        if (!$this->existTableField('dq_action_apply','consecutive_days')){
            $sql = "ALTER TABLE `dq_action_apply` ADD `consecutive_days` int(11) DEFAULT 0 COMMENT '连续充值天数';";
            $this->execute($sql);
        }

    }
}