<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver112 extends VersionBase {

    public function run() {
        // 客服管理链接字段
        if ($this->existTableField("dq_service", "link")){
            $sql = "ALTER TABLE dq_service MODIFY COLUMN link text NULL COMMENT '客服链接,多个用英文逗号隔开';";
            $this->execute($sql);
        }

    }
}