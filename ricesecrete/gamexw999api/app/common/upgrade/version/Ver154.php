<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver154 extends VersionBase
{

    public function run()
    {

        if ($this->existTable("dq_game_bets_time") && $this->existTableField("dq_game_bets_time", "gb_id")) {
            //存在旧版本的数据表先将旧版本删除
            $sql = 'DROP TABLE dq_game_bets_time;';
            $this->execute($sql);
        }

        //注单时间关联表
        if (!$this->existTable("dq_game_bets_time")) {
            $sql = <<<SQL
CREATE TABLE `dq_game_bets_time` (
    `ts` int(255) NOT NULL,
    `gb_intime_mingbid` int(11) DEFAULT '0',
    `gb_intime_maxgbid` int(11) DEFAULT '0',
    `gb_bettime_mingbid` int(11) DEFAULT '0',
    `gb_bettime_maxgbid` int(11) DEFAULT '0',
    `gb_overtime_mingbid` int(11) DEFAULT '0',
    `gb_overtime_maxgbid` int(11) DEFAULT '0',
    PRIMARY KEY (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ;       
SQL;
            $this->execute($sql);
        }
    }
}