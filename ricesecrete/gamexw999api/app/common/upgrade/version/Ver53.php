<?php

namespace app\common\upgrade\version;
use app\common\model\AdminNodeOldModel;
use app\common\upgrade\version\VersionBase;
use think\facade\Db;

class Ver53 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_admin_node", "name")) {
            $sql = "ALTER TABLE `dq_admin_node` ADD COLUMN `path` varchar(100) NULL DEFAULT '', ADD COLUMN `name` varchar(100) NULL DEFAULT '';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_admin_node", "status")) {
            $sql = "ALTER TABLE `dq_admin_node` ADD COLUMN `status` tinyint(1) NULL DEFAULT 1;";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_sms_configs")) {
            $sql = "CREATE TABLE `dq_sms_configs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `sms_name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
                  `sms_iden` varchar(50) NOT NULL DEFAULT '' COMMENT '标识',
                  `sms_content` text NULL COMMENT '自定义参数',
                  `sms_qh` varchar(255) NOT NULL DEFAULT '' COMMENT '发送区号',
                  `sms_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0关 1开',
                  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除 0否 1是',
                PRIMARY KEY (`id`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_node';
        $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '系统管理' AND `node_url` = '/system' AND `pid` = '0'";
        $selectData2 = $this->getTableData($select_sql2);
        if(isset($selectData2[0])) {
            $pid = $selectData2[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '三方短信管理' AND `node_url` = '/sms/list' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}`(`node_name`, `node_url`, `pid`,`special_menu`, `create_time`, `update_time`, `path`, `name`, `status`) VALUES ('三方短信管理', '/sms/list', '{$pid}', 'normal',   ".time().", ".time().", '/sms/list', 'smslist', 1);";
                $this->execute($sql);
            }
        }

        $full_table_name = 'dq_admin_node_old';
        $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '系统管理'";
        $selectData2 = $this->getTableData($select_sql2);
        if(isset($selectData2[0])) {
            $pid = $selectData2[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '三方短信管理' AND `node_url` = '/sms/list' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}`(`node_name`, `node_url`, `pid`,`special_menu`, `create_time`, `update_time`) VALUES ('三方短信管理', '/sms/list', '{$pid}', 'normal',   ".time().", ".time().");";
                $this->execute($sql);
            }
        }

        $fake_node = Db::name('admin_node_old')->where('node_url', '/fake/game')->find();
        if(empty($fake_node)) {
            $pid = Db::name('admin_node_old')->where('node_name', '榜单管理')->value('id');
            $node = [
                'node_name' => '游戏配置',
                'node_url' => '/fake/game',
                'pid' => $pid,
                'sort' => 0,
                'is_admin' => 0,
                'not' => 0,
                'special_menu' => 'normal',
                'create_time' => time(),
                'update_time' => time()
            ];
            $pid2 = Db::name('admin_node_old')->insertGetId($node);
            $node['pid'] = $pid2;
            $node['node_name'] = '添加/修改';
            $node['node_url'] = '/fake/add';
            Db::name('admin_node_old')->insert($node);
            $node['node_name'] = '删除';
            $node['node_url'] = '/fake/delete';
            Db::name('admin_node_old')->insert($node);
        }

        $fake_node = Db::name('admin_node')->where('node_url', '/fake/game')->find();
        if(empty($fake_node)) {
            $pid = Db::name('admin_node')->where('node_name', '榜单管理')->value('id');
            $node = [
                'node_name' => '游戏配置',
                'node_url' => '/fake/game',
                'path' => '/fake/game',
                'name' => 'fake_game',
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
        }

        if (!$this->existTable("dq_fake_game_set")) {
            $sql = <<<SQL
                CREATE TABLE `dq_fake_game_set` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `gid` int DEFAULT '0' COMMENT '游戏ID',
                  `tcid` int DEFAULT '0' COMMENT '三方平台ID',
                  `game_name` varchar(255) DEFAULT '' COMMENT '游戏名称',
                  `game_icon` varchar(255) DEFAULT '' COMMENT '游戏图标',
                  `min_amount` int DEFAULT '100' COMMENT '最小金额',
                  `max_amount` int DEFAULT '50000' COMMENT '最大金额',
                  `min_rate` int DEFAULT '1' COMMENT '最小倍数',
                  `max_rate` int DEFAULT '10' COMMENT '最大倍数',
                  `status` tinyint(1) DEFAULT '1' COMMENT '状态0 禁用 1启用',
                  `create_time` int DEFAULT NULL,
                  `update_time` int DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='游戏造假数据配置表'
SQL;
            $this->execute($sql);
        }



        if (!$this->existTable("dq_game_third_special")) {
            $sql = "CREATE TABLE `dq_game_third_special`  (
                      `gs_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `gs_tcid` int(11) NOT NULL,
                      `gs_gid` int(11) NOT NULL DEFAULT 0,
                      `gs_uid` int(11) NOT NULL DEFAULT 0,
                      `gs_price` decimal(20, 2) NOT NULL COMMENT '操作金额',
                      `gs_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '单号',
                      `gs_type` varchar(255) NOT NULL COMMENT '1是增加2减少',
                      `gs_type_name` varchar(255) NULL DEFAULT NULL COMMENT '收支类型名称',
                      `gs_time` int(11) NULL DEFAULT NULL COMMENT '操作时间',
                      `gs_info` text NULL COMMENT '原始信息',
                      `gs_create_time` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`gs_id`) USING BTREE,
                      INDEX `gs_tcid`(`gs_tcid`) USING BTREE,
                      INDEX `gs_gid`(`gs_gid`) USING BTREE,
                      INDEX `gs_uid`(`gs_uid`) USING BTREE,
                      UNIQUE INDEX `gs_sn`(`gs_sn`) USING BTREE,
                      INDEX `gs_time`(`gs_time`) USING BTREE
                    ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '游戏平台特殊收支表' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_configs", "tc_special_status")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_special_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有特殊报表 0否 1是';";
            $this->execute($sql);
        }

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_action", "sort")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_action` ADD COLUMN `sort` int(11) DEFAULT 0 COMMENT '排序';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "ip_num_limit")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `ip_num_limit` INT(10) DEFAULT 0 NULL COMMENT '同ip注册数量限制，0不限制';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_remain_report")) {
            $sql = <<<SQL
CREATE TABLE `dq_remain_report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `remain_stat` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT '',
  `remain_rate3` decimal(10,2) DEFAULT '0.00',
  `remain_uids3` text,
  `remain_rate5` decimal(10,2) DEFAULT '0.00',
  `remain_uids5` text,
  `remain_rate7` decimal(10,2) DEFAULT '0.00',
  `remain_uids7` text,
  `remain_rate15` decimal(10,2) DEFAULT '0.00',
  `remain_uids15` text,
  `remain_rate30` decimal(10,2) DEFAULT '0.00',
  `remain_uids30` text,
  `remain_rate60` decimal(10,2) DEFAULT '0.00',
  `remain_uids60` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
SQL;
            $this->execute($sql);
        }


        $remain_node = Db::name('admin_node')->where('node_url', '/report/remain')->find();
        if(empty($remain_node)) {
            $pid = Db::name('admin_node')->where('node_name', '报表管理')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '留存率报表',
                    'node_url' => '/report/remain',
                    'path' => '/report/remain',
                    'name' => 'report_remain',
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
            }
        }

    }
}
