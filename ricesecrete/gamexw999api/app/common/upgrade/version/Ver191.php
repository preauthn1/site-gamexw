<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver191 extends VersionBase
{

    public function run()
    {
        if ( $this->existTableField("dq_gonggao", "agent_id") ) {
            $sql = 'ALTER TABLE dq_gonggao MODIFY COLUMN agent_id VARCHAR(255) NOT NULL DEFAULT "0" COMMENT "代理id，多个逗号分割";';
            $this->execute($sql);
        }
    }
}