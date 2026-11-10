<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver223 extends VersionBase
{
    public function run()
    {
        //平台增加是否整数提现
        if (!$this->existTableField("dq_third_configs",'tc_exchangeint')) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_exchangeint` int NULL DEFAULT 0 COMMENT '三方平台转帐是否要求整数 0不要求 1要求' AFTER `tc_timezone`;";
            $this->execute($sql);
        }
    }
}