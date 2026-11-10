<?php

namespace app\common\upgrade\version;

class Ver131 extends VersionBase
{

    public function run()
    {
        //抽奖设置成必中转盘余额，范围中间用-隔开,例如:2-3，为空时不生效
        if (!$this->existTableField("dq_pdd_turntable", "odds_of_winning_range")) {
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `odds_of_winning_range` varchar(50) DEFAULT '' COMMENT '抽奖设置成必中转盘余额，范围中间用-隔开,例如:2-3，为空时不生效';";
//            print_r($sql);
			$this->execute($sql);
        }
    }
}