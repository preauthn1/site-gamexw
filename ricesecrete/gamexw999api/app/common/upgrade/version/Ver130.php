<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;
use think\facade\Db;

class Ver130 extends VersionBase
{

    public function run(){
        //五福
        if (!$this->existTable("dq_wufu")) {
            $sql = <<<SQL
CREATE TABLE `dq_wufu` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(50) NOT NULL COMMENT '语言',
  `name` varchar(100) NOT NULL COMMENT '名称',
  `image` varchar(500) NOT NULL COMMENT '封面',
  `intro` varchar(255) NOT NULL COMMENT '简介',
  `agent_ids` varchar(255) NOT NULL DEFAULT '0' COMMENT '代理id串',
  `start_time` int(11) NOT NULL COMMENT '开始时间',
  `end_time` int(11) NOT NULL COMMENT '结束时间',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `savings` tinyint(5) NOT NULL COMMENT '流水倍数',
  `introduce` text NOT NULL COMMENT '介绍',
  `status` tinyint(1) NOT NULL default 1 COMMENT '状态（1启用）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `start_time`(`start_time`, `end_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='五福';
SQL;
            $this->execute($sql);
        }
        //五福卡片
        if (!$this->existTable("dq_wufu_cards")) {
            $sql = <<<SQL
CREATE TABLE `dq_wufu_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `w_id` int(11) NOT NULL COMMENT '五福对应id',
  `name` varchar(100) NOT NULL COMMENT '名称',
  `image` varchar(500) NOT NULL COMMENT '图片',
  `amount` decimal(10, 2) NOT NULL COMMENT '金额',
  `savings` tinyint(5) NOT NULL COMMENT '流水',
  `is_register_give` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否注册赠送',
  `odds` tinyint(3) NOT NULL COMMENT '中奖几率',
  `sort` tinyint(5) NOT NULL default 0 COMMENT '排序',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `w_id`(`w_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='五福卡片';
SQL;
            $this->execute($sql);
        }
        //卡片记录
        if (!$this->existTable("dq_wufu_count")) {
            $sql = <<<SQL
CREATE TABLE `dq_wufu_count` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT 'UID',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0邀请获得，1首次充值获得，2玩游戏获得，3注册获得）',
  `w_id` int(11) NOT NULL COMMENT '五福ID',
  `card_id` int(11) NOT NULL COMMENT '卡片id（-1未中奖，0未刮奖，大于0为刮种的卡片）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `type`(`type`) USING BTREE,
  INDEX `w_id`(`w_id`) USING BTREE,
  INDEX `card_id`(`card_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡片记录';
SQL;
            $this->execute($sql);
        }
        //卡片记录
        if (!$this->existTable("dq_wufu_withdrawal")) {
            $sql = <<<SQL
CREATE TABLE `dq_wufu_withdrawal` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) NOT NULL COMMENT 'UID',
  `username` varchar(255) NOT NULL COMMENT '用户名',
  `w_id` int(11) NOT NULL COMMENT '五福id',
  `card_id` int(11) NOT NULL COMMENT '卡片id（0为总卡片）',
  `amount` decimal(10, 2) NOT NULL COMMENT '金额',
  `savings` tinyint(5) NOT NULL COMMENT '流水倍数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `w_id`(`w_id`) USING BTREE,
  INDEX `card_id`(`card_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现数据';
SQL;
            $this->execute($sql);
        }

        //五福菜单节点
        if( !$this->getNodeIdByNodeUrl('/wufu') ){
            //添加五福顶级菜单
            $data = [
                'node_name' => '五福管理',
                'node_url' => '/wufu',
                'path' => '/wufu',
                'name' => 'wufu',
                'pid' => 0,
                'icon' => 'IF-icon-huodong',
                'sort' => 5,
                'create_time' => time(),
                'update_time' => time()
            ];
            $wufuParentNodeId = Db::name('admin_node')->insertGetId($data);
            //添加五福管理
            $wufuNodeId = $this->addPublicParentNode(
                '五福列表',
                '/wufu/wufuList',
                'wufuList',
                '/wufu/wufuList',
                $wufuParentNodeId,
                1,
                true
            );
            //卡片管理
            $this->addPublicParentNode(
                '卡片管理',
                'wufuCards',
                'wufuCards',
                'wufuCards',
                $wufuNodeId
            );
            //卡片记录
            $this->addPublicParentNode(
                '卡片记录',
                '/wufu/cardRecord',
                'cardRecord',
                '/wufu/cardRecord',
                $wufuParentNodeId,
                2
            );
            //提现记录
            $this->addPublicParentNode(
                '兑换记录',
                '/wufu/withdrawalRecord',
                'withdrawalRecord',
                '/wufu/withdrawalRecord',
                $wufuParentNodeId,
                3
            );
        }
        //邀请用户的uid
        if (!$this->existTableField("dq_wufu_count", "invite_uid")) {
            $sql = "ALTER TABLE `dq_wufu_count` ADD COLUMN `invite_uid` int(11) NOT NULL DEFAULT 0 COMMENT '邀请用户的UID';";
            $this->execute($sql);
        }
        //使用类型
        if (!$this->existTableField("dq_wufu_count", "use_type")) {
            $sql = "ALTER TABLE `dq_wufu_count` ADD COLUMN `use_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '使用类型（0未使用，1单张使用，2集体使用）';";
            $this->execute($sql);
        }
        //是否刮奖
        if (!$this->existTableField("dq_wufu_count", "use_type")) {
            $sql = "ALTER TABLE `dq_wufu_count` ADD COLUMN `use_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '使用类型（0未使用，1单张使用，2集体使用）';";
            $this->execute($sql);
        }
        //邀请人数条件
        if (!$this->existTableField("dq_wufu_cards", "people_condition")) {
            $sql = "ALTER TABLE `dq_wufu_cards` ADD COLUMN `people_condition` int(11) NOT NULL DEFAULT 0 COMMENT '邀请人数条件（0为无效，达到后必送）';";
            $this->execute($sql);
        }
        //五福卡片关键卡
        if (!$this->existTableField("dq_wufu_cards", "type")) {
            $sql = "ALTER TABLE `dq_wufu_cards` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '卡片类型，（0普通卡片，1关键卡片）';";
            $this->execute($sql);
        }
        //未中奖图片
        if (!$this->existTableField("dq_wufu", "losing_lottery_img")) {
            $sql = "ALTER TABLE `dq_wufu` ADD COLUMN `losing_lottery_img` varchar(255) NOT NULL DEFAULT '' COMMENT '未中奖图片';";
            $this->execute($sql);
        }
    }
}