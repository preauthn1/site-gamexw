<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver194 extends VersionBase
{
    public function run()
    {
        //实名认证是否开启
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'user_verify_status') ){
            //实名认证是否开启
            $this->addSysSets(
                'user_verify_status', '实名认证', 0, 'int', '实名认证'
            );
        }
        //提现实名认证验证是否开启
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'withdraw_user_verify') ){
            //提现实名认证验证是否开启
            $this->addSysSets(
                'withdraw_user_verify', '提现实名认证验证', 0, 'int', '提现实名认证验证'
            );
        }
        //实名认证自动审核是否开启
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'auto_user_verify') ){
            //实名认证自动审核是否开启
            $this->addSysSets(
                'auto_user_verify', '实名认证自动审核', 0, 'int', '实名认证自动审核'
            );
        }
        if (!$this->existTable("dq_user_verify")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_verify` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT '0' COMMENT '用户ID',
  `content` text CHARACTER SET utf8mb4 COMMENT '用户认证提交的数据',
  `status` tinyint(2) DEFAULT '0' COMMENT '认证状态  0未认证 1认证中  2认证通过 3认证失败',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户实名认证数据';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_user_verify_config")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_verify_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 DEFAULT '' COMMENT '认证数据名称',
  `keyv` varchar(20) CHARACTER SET utf8mb4 DEFAULT '' COMMENT '认证数据键值',
  `type` varchar(30) DEFAULT '' COMMENT '认证数据类型  img 图片（图片地址），text 文本类型 ',
  `status` tinyint(2) DEFAULT '0' COMMENT '认证状态  0未启用 1启用',
  `sort` int(6) DEFAULT '0' COMMENT '排序',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证数据配置';
SQL;
            $this->execute($sql);
        }
        $zgNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/admin/userVerifyConfig');
            if (empty($NodeId)) {
                $pCNodeId = $this->addPublicParentNode(
                    '实名认证数据配置',
                    '/admin/userVerifyConfig',
                    'adminUserVerifyConfig',
                    '/admin/userVerifyConfig',
                    $zgNodeId,
                    11
                );
                $this->addPublicChildrenMenuNode($pCNodeId);
            }
        }
        $zgNodeId = $this->getNodeIdByNodeUrl('/user');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/user/userVerify');
            if (empty($NodeId)) {
                $pCNodeId = $this->addPublicParentNode(
                    '实名认证审核',
                    '/user/userVerify',
                    'userUserVerify',
                    '/user/userVerify',
                    $zgNodeId,
                    11
                );
                $this->addPublicChildrenMenuNode($pCNodeId);
            }

        }
    }
}