<?php

namespace app\common\upgrade\version;

class Ver252 extends VersionBase
{

    public function run()
    {
        //实名认证审核备注
        if (!$this->existTableField("dq_user_verify", "remarks")) {
            $sql = "ALTER TABLE `dq_user_verify` ADD COLUMN `remarks` varchar(255) NULL COMMENT '审核备注';";
            $this->execute($sql);
        }
        //实名认证快捷审核备注
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'user_verify_notes') ){
            //实名认证快捷审核备注
            $this->addSysSets(
                'user_verify_notes', '实名认证快捷审核备注', '', 'text', '基础设置', '实名认证快捷审核备注', 'a:2:{i:0;a:1:{s:5:"value";s:27:"Wrong information filled in";}i:1;a:1:{s:5:"value";s:40:"Real-name authentication has been passed";}}'
            );
        }
    }

}
