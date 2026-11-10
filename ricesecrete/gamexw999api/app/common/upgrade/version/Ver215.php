<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver215 extends VersionBase
{
    public function run()
    {
        //代理留存率索引
        if ($this->existTableField("dq_operating_agent",'day_time')) {
            $sql = "ALTER TABLE dq_operating_agent ADD INDEX(user_id),ADD INDEX(day_time);";
            $this->execute($sql);
        }
        //总用户限制
        if ($this->existTableField("dq_game_bets_temp",'gb_uname')) {
            $sql = "ALTER TABLE dq_game_bets_temp ADD INDEX(gb_uname);";
            $this->execute($sql);
        }

    }
}