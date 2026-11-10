<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver110 extends VersionBase {

    public function run() {
        // pdd转盘
        if (!$this->existTable("dq_pdd_turntable")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_turntable`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lang` varchar(50)  NOT NULL COMMENT '语言',
  `init_amount` varchar(255)  NOT NULL COMMENT '初始赠送金额，范围用-隔开',
  `amount` decimal(10, 2) NOT NULL COMMENT '赠送金额',
  `savings` tinyint(5) NOT NULL DEFAULT 1 COMMENT '流水倍数',
  `min_invite_people` tinyint(5) NOT NULL COMMENT '最低邀请人数',
  `title` varchar(100)  NOT NULL COMMENT '标题',
  `introduce` text  NOT NULL COMMENT '规则',
  `time` int(11) NOT NULL COMMENT '时间限制（单位：小时）',
  `odds_of_winning` tinyint(3) NOT NULL COMMENT '中将几率（单位：%）',
  `minimum_amount` int(10) NOT NULL DEFAULT 0 COMMENT '最低金额次数',
  `people_num` int(11) NOT NULL COMMENT '每人参与次数',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '状态（1开启，0关闭）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
)ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = 'PDD转盘' ;
SQL;
            $this->execute($sql);
        }
        // pdd转盘邀请记录
        if (!$this->existTable("dq_pdd_invite_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_invite_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT ' ID',
  `uid` int(11) NOT NULL COMMENT 'UID',
  `username` varchar(100)  NOT NULL COMMENT '用户username',
  `invite_uid` int(11) NOT NULL COMMENT '邀请用户id',
  `invite_username` varchar(100)  NOT NULL COMMENT '邀请用户username',
  `turntable_id` int(11) NOT NULL COMMENT '对应转盘id',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '抽奖金额',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态（1已抽奖，0未抽奖）',
  `withdrawal_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '可取款金额',
  `is_withdrawal` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否取款（1已取款，0待取款）',
  `expire_time` int(11) NOT NULL COMMENT '过期时间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '转盘邀请记录';
SQL;
            $this->execute($sql);
        }
        // pdd转盘取款记录
        if (!$this->existTable("dq_pdd_withdrawal_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_withdrawal_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT 'UID',
  `username` varchar(100)  NOT NULL COMMENT '用户username',
  `turntable_id` int(11) NOT NULL COMMENT '转盘id',
  `amount` decimal(10, 2) NOT NULL COMMENT '取款金额',
  `savings` int(10) NOT NULL COMMENT '流水倍数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '转盘取款记录';
SQL;
            $this->execute($sql);
        }
        //
        $actionNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if($actionNodeId){
            $pddTurntableNodeId = $this->getNodeIdByNodeUrl('/pdd/pddList');
            if( !$pddTurntableNodeId ){
                //pdd转盘
                $this->addPublicParentNode(
                    'pdd转盘',
                    '/pdd/pddList',
                    'pddList',
                    '/pdd/pddList',
                    $actionNodeId               ,
                    6,
                    true
                );
            }
            $pddInviteRecordNodeId = $this->getNodeIdByNodeUrl('pdd邀请记录');
            if( !$pddInviteRecordNodeId ){
                //pdd邀请记录
                $this->addPublicParentNode(
                    'pdd邀请记录',
                    '/pdd/pddInviteRecord',
                    'pddInviteRecord',
                    '/pdd/pddInviteRecord',
                    $actionNodeId               ,
                    6
                );
            }
            $pddWithdrawalRecordNodeId = $this->getNodeIdByNodeUrl('/pdd/pddWithdrawalRecord');
            if( !$pddWithdrawalRecordNodeId ){
                //pdd提款记录
                $this->addPublicParentNode(
                    'pdd取款记录',
                    '/pdd/pddWithdrawalRecord',
                    'pddWithdrawalRecord',
                    '/pdd/pddWithdrawalRecord',
                    $actionNodeId               ,
                    6
                );
            }
            //PDD号码
            $pddPhoneNodeId = $this->getNodeIdByNodeUrl('/pdd/pddPhone');
            if( !$pddPhoneNodeId ){
                //PDD号码
                $pddPhonePid = $this->addPublicParentNode(
                    'pdd号码',
                    '/pdd/pddPhone',
                    'pddPhone',
                    '/pdd/pddPhone',
                    $actionNodeId,
                    6,
                    true
                );
                $saveAll = [];
                $saveAll[] = [
                    'node_name' => '导入',
                    'node_url' => 'import',
                    'path' => 'import',
                    'name' => 'import',
                    'pid' => $pddPhonePid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                $saveAll[] = [
                    'node_name' => '导出',
                    'node_url' => 'export',
                    'path' => 'export',
                    'name' => 'export',
                    'pid' => $pddPhonePid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertAll($saveAll);
            }
        }

        // pdd号码
        if (!$this->existTable("dq_pdd_phone")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_phone`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT 'PHONE',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = 'PDD号码';
SQL;
            $this->execute($sql);
        }
    }
}