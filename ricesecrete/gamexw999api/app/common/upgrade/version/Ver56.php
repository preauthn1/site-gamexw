<?php

namespace app\common\upgrade\version;
use think\facade\Db;

class Ver56 extends VersionBase {

    public function run() {

        // 彩票相关 start
        if (!$this->existTable("dq_s_lottery")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT '0' COMMENT 'games表ID',
  `name` varchar(255) DEFAULT '' COMMENT '彩种名称',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '彩种图标',
  `lottery_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '彩种类型',
  `lottery_th` int DEFAULT '0' COMMENT '每天的彩票期数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态1启用,0禁用',
  `sort` int DEFAULT '0' COMMENT '排序',
  `quick_amount` varchar(255) DEFAULT '' COMMENT '快捷金额',
  `setting` text COMMENT '彩票配置',
  `draw_type` tinyint(1) DEFAULT '0' COMMENT '开奖规则 0 随机 1:庄家赢_最多 2:庄家赢_最少 3:庄家赢_随机 4:庄家输_最多 5:庄家输_最少 6:庄家输_随机',
  `draw_rate` decimal(10,2) DEFAULT '1.00' COMMENT '开奖概率',
  `create_time` int DEFAULT NULL COMMENT '创建时间',
  `update_time` int DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='彩票-彩种表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_s_lottery_odds")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery_odds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lottery_id` int NOT NULL,
  `bet_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '玩法分组',
  `bet_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '玩法',
  `bet_odd` decimal(10,4) NOT NULL COMMENT '赔率',
  `bet_tag` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1启用,0停用',
  `sort` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_lottery_id` (`lottery_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_s_lottery_opentime")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery_opentime` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lottery_id` int NOT NULL DEFAULT '0' COMMENT '彩种ID',
  `qishu` int NOT NULL DEFAULT '0' COMMENT '期数',
  `open_time` time NOT NULL COMMENT '开盘时间',
  `close_time` time NOT NULL COMMENT '封盘时间',
  `draw_time` time NOT NULL COMMENT '开奖时间',
  `stauts` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_s_lottery_order")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_sn` varchar(50) NOT NULL DEFAULT '' COMMENT '订单ID',
  `uid` int NOT NULL COMMENT '用户ID',
  `lottery_id` int NOT NULL COMMENT '彩种ID',
  `qishu` bigint NOT NULL COMMENT '期数',
  `odd_id` int NOT NULL COMMENT '玩法ID',
  `odd_group` varchar(100) NOT NULL COMMENT '玩法组别',
  `odd_name` varchar(100) NOT NULL COMMENT '玩法名称',
  `bet_tag` varchar(50) NOT NULL COMMENT '玩法标识',
  `odds` decimal(10,2) NOT NULL COMMENT '赔率',
  `amount` decimal(20,4) NOT NULL COMMENT '下注金额',
  `currency_id` int NOT NULL DEFAULT '0' COMMENT '币种ID',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未结算，1已结算， -1无效注单',
  `result` varchar(100) DEFAULT '' COMMENT '开奖结果',
  `odd_result` varchar(100) DEFAULT '' COMMENT '玩法结果',
  `is_win` tinyint(1) DEFAULT '0' COMMENT '1 赢 2输',
  `win_amount` decimal(20,4) DEFAULT '0.0000' COMMENT '赢金额',
  `pay_time` int DEFAULT NULL COMMENT '结算时间',
  `create_time` int DEFAULT NULL COMMENT '下注时间',
  `update_time` int DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_lottery_id` (`lottery_id`),
  KEY `idx_qishu` (`qishu`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='彩票-注单表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_s_lottery_preset")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery_preset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lottery_id` int NOT NULL DEFAULT '0' COMMENT '彩种ID',
  `qishu` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '期号',
  `numbers` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '预设开奖结果',
  `create_time` int DEFAULT NULL,
  `update_time` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `qishu` (`qishu`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='预设开奖';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_s_lottery_result")) {
            $sql = <<<SQL
CREATE TABLE `dq_s_lottery_result` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lottery_id` int NOT NULL COMMENT '彩种ID',
  `qishu` bigint NOT NULL COMMENT '开奖期数',
  `open_time` int NOT NULL COMMENT '开奖时间',
  `status` int NOT NULL DEFAULT '0' COMMENT '0=未开奖;1=已开奖',
  `type` int DEFAULT '1' COMMENT '彩种类型',
  `ball_1` int DEFAULT NULL COMMENT '号码1',
  `ball_2` int DEFAULT NULL COMMENT '号码2',
  `ball_3` int DEFAULT NULL COMMENT '号码3',
  `ball_4` int DEFAULT NULL COMMENT '号码4',
  `ball_5` int DEFAULT NULL COMMENT '号码5',
  `ball_6` int DEFAULT NULL COMMENT '号码6',
  `ball_7` int DEFAULT NULL COMMENT '号码7',
  `ball_8` int DEFAULT NULL COMMENT '号码8',
  `ball_9` int DEFAULT NULL COMMENT '号码9',
  `ball_10` int DEFAULT NULL COMMENT '号码10',
  `create_time` int DEFAULT NULL COMMENT '创建时间',
  `update_time` int DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_lottery_id` (`lottery_id`),
  KEY `idx_qishu` (`qishu`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='彩票-开奖数据表';
SQL;
            $this->execute($sql);
        }
        $lottery_node = Db::name('admin_node')->where('node_url', '/lottery')->find();
        if(empty($lottery_node)) {
            $node = [
                'node_name' => '彩票管理',
                'node_url' => '/lottery',
                'path' => '/lottery',
                'name' => 'lottery',
                'pid' => 0,
                'status' => 1,
                'sort' => 14,
                'is_admin' => 0,
                'not' => 0,
                'special_menu' => 'normal',
                'create_time' => time(),
                'update_time' => time()
            ];
            $pid = Db::name('admin_node')->insertGetId($node);
            if ($pid) {
                $node = [
                    'node_name' => '彩种列表',
                    'node_url' => '/lottery/list',
                    'path' => '/lottery/list',
                    'name' => 'lottery_list',
                    'pid' => $pid,
                    'status' => 1,
                    'sort' => 0,
                    'is_admin' => 0,
                    'not' => 0,
                    'special_menu' => 'normal',
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
                $node['node_name'] = '注单列表';
                $node['node_url'] = '/lottery/order';
                $node['path'] = '/lottery/order';
                $node['name'] = 'lottery_order';
                Db::name('admin_node')->insertGetId($node);
                $node['node_name'] = '开奖设置';
                $node['node_url'] = '/lottery/result';
                $node['path'] = '/lottery/result';
                $node['name'] = 'lottery_result';
                Db::name('admin_node')->insertGetId($node);
            }
        }

        if (!$this->existTableField("dq_rule", "game_id")) {
            $sql = "ALTER TABLE `dq_rule` ADD COLUMN `game_id` int NULL DEFAULT 0;";
            $this->execute($sql);
        }
        // 彩票相关 end
    }
}