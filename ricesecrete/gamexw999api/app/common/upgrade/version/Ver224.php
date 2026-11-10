<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver224 extends VersionBase
{
    public function run()
    {
        //邮箱增加后台配置
        if (!$this->existTable("dq_email_configs")) {
            $sql = "CREATE TABLE `dq_email_configs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `sms_name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
                  `sms_iden` varchar(50) NOT NULL DEFAULT '' COMMENT '标识',
                  `sms_content` text NULL COMMENT '自定义参数',
                  `sms_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0关 1开',
                  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除 0否 1是',
                PRIMARY KEY (`id`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
            $this->execute($sql);
        }

        //增加邮箱菜单配置
        $nodeId = $this->getNodeIdByNodeUrl('/system');
        if( $nodeId ){
            $_node_id = $this->getNodeIdByNodeUrlPid('/email/configs', $nodeId);
            if( !$_node_id ){
                $this->addPublicParentNode(
                    '三方邮箱管理', '/email/configs',
                    'emailConfigs', '/email/configs',
                    $nodeId, 0, true, 0
                );
            }
        }
    }
}