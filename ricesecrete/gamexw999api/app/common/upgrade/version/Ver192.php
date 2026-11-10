<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver192 extends VersionBase
{

    public function run()
    {
        if ( !$this->existTableField("dq_pdd_turntable", "start_give_count") ) {
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN  `start_give_count` int(10) DEFAULT 1 COMMENT '开始赠送次数';";
            $this->execute($sql);
        }
        if ( !$this->existTableField("dq_banner", "mobile_img") ) {
            $sql = "ALTER TABLE `dq_banner` ADD COLUMN `mobile_img` varchar(255) NULL COMMENT '移动端图片';";
            $this->execute($sql);
        }
        if ( !$this->existTableField("dq_index_menu", "agent_id") ) {
            $sql = "ALTER TABLE `dq_index_menu` 
ADD COLUMN `agent_id` varchar(255)  NOT NULL DEFAULT '' COMMENT '代理线ID',
ADD COLUMN `pid` int NOT NULL DEFAULT 0 COMMENT '父菜单ID',
ADD COLUMN `extra` varchar(255) NULL DEFAULT '' COMMENT '菜单扩展信息',
ADD COLUMN `is_img` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为图片菜单';";

            $this->execute($sql);
        }

        if ( !$this->existTableField("dq_currency", "symbol") ) {
            $sql = "ALTER TABLE `dq_currency` 
                ADD COLUMN `symbol` varchar(20) NULL DEFAULT '' COMMENT '货币标识',
                ADD COLUMN `symbol_img` varchar(255) NULL DEFAULT '' COMMENT '货币标识图片'";

            $this->execute($sql);
        }

    }
}