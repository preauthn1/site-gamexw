<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver208 extends VersionBase
{
    public function run()
    {
        //title
        if ($this->existTableField("dq_pdd_turntable",'title')) {
            $sql = "ALTER TABLE dq_pdd_turntable 
MODIFY title VARCHAR(100) NULL COMMENT '标题（弃用）',
MODIFY introduce TEXT NULL COMMENT '规则（弃用）';";
            $this->execute($sql);
        }

    }
}