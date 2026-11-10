<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver205 extends VersionBase
{
    public function run()
    {
        //logo
        if (!$this->existTableField("dq_lottery_config",'agent_id')) {
            $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `agent_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '代理线';";
            $this->execute($sql);
        }

        if ($this->existTableField("dq_third_configs",'tc_api_prikey')) {
            $sql = "ALTER TABLE `dq_third_configs` MODIFY COLUMN `tc_api_prikey`  varchar(1024)  NOT NULL COMMENT '三方平台私钥' AFTER `tc_api_urlpre`;";
            $this->execute($sql);
        }

        if ($this->existTableField("dq_third_configs",'tc_api_signkey')) {
            $sql = "ALTER TABLE `dq_third_configs` MODIFY COLUMN `tc_api_signkey`  varchar(1024)  NULL DEFAULT NULL COMMENT '三方平台签名密钥，有些平台签名密钥为独立的' AFTER `tc_api_prikey`;";
            $this->execute($sql);
        }

        if ($this->existTableField("dq_third_configs",'tc_tag')) {
            $sql = "ALTER TABLE `dq_third_configs` MODIFY COLUMN `tc_tag`  varchar(1024)  NOT NULL COMMENT '本平台在三方平台的唯一标识，比如商户号' AFTER `tc_api_signkey`;";
            $this->execute($sql);
        }

    }
}