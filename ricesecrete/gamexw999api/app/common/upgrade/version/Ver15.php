<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver15 extends VersionBase {

    public function run() {

        if (!$this->existTableField("dq_lottery_config", "uprice")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `uprice`  decimal(20,2) NULL DEFAULT 0 COMMENT '充值usdt金额';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_config", "required_integral")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `required_integral` int(10) NULL DEFAULT 0 COMMENT '消耗积分';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_config", "integral")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `integral` int(10) NULL DEFAULT 0 COMMENT '赠送积分';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_record", "integral")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_record` ADD COLUMN `integral` int(10) NOT NULL DEFAULT 0 COMMENT '消费积分';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_record", "is_fake")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_record` ADD COLUMN `is_fake` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否假数据 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_lottery_record", "is_away")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_lottery_record` ADD COLUMN `is_away` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否用户赠送的免费抽奖的状态 0否 1是';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user", "integral")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `integral` int(10) NOT NULL DEFAULT 0 COMMENT '积分';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user", "free_count")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `free_count` int(10) NOT NULL DEFAULT 0 COMMENT '转盘免费次数';";
            $this->execute($sql);
        }

    }

}
