<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver123 extends VersionBase {

    public function run() {
        //快捷银行充值金额
        if (!$this->existTableField("dq_user_agent_website_config", "quick_recharge")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `quick_recharge` varchar(255) NULL COMMENT '快捷银行充值金额';";
            $this->execute($sql);
        }
        //快捷银行提现金额
        if (!$this->existTableField("dq_user_agent_website_config", "quick_withdrawal")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `quick_withdrawal` varchar(255) NULL COMMENT '快捷银行提现金额';";
            $this->execute($sql);
        }
        //快捷USDT充值金额
        if (!$this->existTableField("dq_user_agent_website_config", "usdt_quick_recharge")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `usdt_quick_recharge` varchar(255) NULL COMMENT '快捷USDT充值金额';";
            $this->execute($sql);
        }
        //快捷USDT提现金额
        if (!$this->existTableField("dq_user_agent_website_config", "usdt_quick_withdrawal")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `usdt_quick_withdrawal` varchar(255) NULL COMMENT '快捷USDT提现金额';";
            $this->execute($sql);
        }

        //注册账号类型1 手机，2 邮箱，3 同时开启
        if (!$this->existTableField("dq_user_agent_website_config", "phone_bind")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `phone_bind` tinyint(1) NOT NULL DEFAULT 3 COMMENT '注册账号类型1手机，2邮箱，3同时开启';";
            $this->execute($sql);
        }
        //需要额外填信息0否，1 手机，2 邮箱
        if (!$this->existTableField("dq_user_agent_website_config", "is_reg_type")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `is_reg_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '需要额外填信息0否，1手机，2邮箱';";
            $this->execute($sql);
        }
        //注册验证码1 开启，0 关闭
        if (!$this->existTableField("dq_user_agent_website_config", "phone_check")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `phone_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '注册验证码1开启，0关闭';";
            $this->execute($sql);
        }
        //上级分配1开启，0关闭，开启后使用上面域名注册的用户，注册的用户上级为该代理的子代理（平均分配）
        if (!$this->existTableField("dq_user_agent_website_config", "parent_allot")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `parent_allot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '上级分配1开启，0关闭，开启后使用上面域名注册的用户，注册的用户上级为该代理的子代理（平均分配）';";
            $this->execute($sql);
        }
    }
}