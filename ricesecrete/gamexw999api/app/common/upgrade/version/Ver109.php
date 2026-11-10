<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver109 extends VersionBase {

    public function run() {
        //添加注单表索引
        if ($this->existTableField("dq_game_bets", "gb_trans_no")){
            //获取索引列表，判断索引不存在则添加索引
            $indexArr = $this->getIndexs('dq_game_bets');
            if( !in_array('gb_trans_no', $indexArr) ){
                $sql = "ALTER TABLE dq_game_bets ADD INDEX(`gb_trans_no`);";
                $this->execute($sql);
            }
        }
    }
}