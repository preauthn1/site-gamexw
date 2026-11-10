<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver229 extends VersionBase
{

    public function run()
    {
        //最低出款金额
        if (!$this->existTableField("dq_user_msg", "read_uid")){
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `read_uid` varchar(255) NOT NULL DEFAULT '-1' COMMENT '已读用户uid(多个逗号分隔)，系统消息专用，该字段有个默认值，为了提高后面写入效率';";
            $this->execute($sql);
        }
    }
}