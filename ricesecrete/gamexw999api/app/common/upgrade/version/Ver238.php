<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver238 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 用户备注
        if (!$this->existTableField("dq_user_offline", "user_remarks")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_offline` ADD COLUMN `user_remarks` varchar(255)  COMMENT '用户备注';";
            $this->execute($sql);
        }

        //快捷充值备注
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'offline_notes') ){
            //用户备注
            $this->addSysSets(
                'offline_notes', '快捷充值备注', '', 'text', '快捷充值备注'
            );
        }
    }

}
