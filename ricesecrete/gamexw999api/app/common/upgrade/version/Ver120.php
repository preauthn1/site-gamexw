<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver120 extends VersionBase {

    public function run() {
        // 领取码金额字段
        if ($this->existTableField("dq_receive_code", "reward_amount")){
            $sql = "ALTER TABLE dq_receive_code MODIFY COLUMN reward_amount varchar(50) NOT NULL DEFAULT 0 COMMENT '奖励金额（随机中间用-分开）';";
            $this->execute($sql);
        }
        // 同ip可领取数量
        if (!$this->existTableField("dq_receive_code", "receive_ip_num")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `receive_ip_num` tinyint(5) NOT NULL DEFAULT 0 COMMENT '同IP可领取数量';";
            $this->execute($sql);
        }
        // 领取记录流水字段
        if (!$this->existTableField("dq_receive_code_record", "savings")){
            $sql = "ALTER TABLE `dq_receive_code_record` ADD COLUMN `savings` int(10) NOT NULL DEFAULT 1 COMMENT '流水字段';";
            $this->execute($sql);
        }
        // 领取ip
        if (!$this->existTableField("dq_receive_code_record", "ip")){
            $sql = "ALTER TABLE `dq_receive_code_record` ADD COLUMN `ip` varchar(50) NULL COMMENT '领取ip';";
            $this->execute($sql);
        }
        // 金额类型
        if (!$this->existTableField("dq_receive_code", "amount_type")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `amount_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '金额类型（0普通金额，累计金额）';";
            $this->execute($sql);
        }
        // 金额类型
        if (!$this->existTableField("dq_receive_code_record", "amount_type")){
            $sql = "ALTER TABLE `dq_receive_code_record` ADD COLUMN `amount_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '金额类型（0普通金额，累计金额）';";
            $this->execute($sql);
        }
        // 金额类型
        if (!$this->existTableField("dq_receive_code", "amount_type")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `amount_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '金额类型（0普通金额，累计金额）';";
            $this->execute($sql);
        }
        // 领取条件
        if (!$this->existTableField("dq_receive_code", "receive_condition")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `receive_condition` tinyint(1) NOT NULL DEFAULT 1 COMMENT '参与条件（1首充金额，2单笔金额，3累计金额）';";
            $this->execute($sql);
        }
        // 充值金额
        if (!$this->existTableField("dq_receive_code", "recharge_amount")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `recharge_amount` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '充值金额';";
            $this->execute($sql);
        }
        // 金额类型
        if (!$this->existTableField("dq_receive_code", "condition_date_type")){
            $sql = "ALTER TABLE `dq_receive_code` ADD COLUMN `condition_date_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '条件对应时间（0所有，1上月，2本月，3昨天，4今天，5本周';";
            $this->execute($sql);
        }

    }
}