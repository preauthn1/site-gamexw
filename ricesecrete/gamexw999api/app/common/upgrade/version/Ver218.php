<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver218 extends VersionBase
{
    public function run()
    {
        //代理留存率索引
        if ($this->existTableField("dq_pdd_invite_record",'uid')) {
            $sql = "ALTER TABLE dq_pdd_invite_record ADD INDEX(uid), ADD INDEX(invite_uid);";
            $this->execute($sql);
        }

    }
}