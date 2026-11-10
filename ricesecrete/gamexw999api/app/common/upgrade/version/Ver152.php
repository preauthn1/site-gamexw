<?php

namespace app\common\upgrade\version;

class Ver152 extends VersionBase
{

    public function run()
    {
        //邮箱是否验证
        if (!$this->existTableField("dq_user", "mail_bind_status")) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `mail_bind_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '邮箱绑定状态（0未绑定，1已验证，2未验证）';";
            $this->execute($sql);
            //修改原来的账号为已验证
            $sql = "update dq_user set mail_bind_status = 1 where `mail` is not null and mail <> '';";
            $this->execute($sql);
        }
        //号码是否验证
        if (!$this->existTableField("dq_user", "phone_bind_status")) {
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `phone_bind_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '号码绑定状态（0未绑定，1已验证，2未验证）';";
            $this->execute($sql);
            //修改原来的账号为已验证
            $sql = "update dq_user set phone_bind_status = 1 where `phone` is not null and phone <> '';";
            $this->execute($sql);
        }
        //是否开启邮箱验证或手机验证
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'is_open_email_or_phone_check') ){
            //是否开启邮箱验证或手机验证
            $this->addSysSets(
                'is_open_email_or_phone_check', '是否开启邮箱验证或手机验证', 0, 'int', '注册设置', '是否开启邮箱验证或手机验证，开启后添加银行必须要该账号已经验证过手机或邮箱'
            );
        }
    }
}