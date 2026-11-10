<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver29 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表是否已存在
        if(!$this->existTable("dq_integral_mx")){
            $sql = "CREATE TABLE `dq_integral_mx`  (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
              `old_integral` int(11) NOT NULL DEFAULT 0 COMMENT '旧积分',
              `new_integral` int(11) NOT NULL DEFAULT 0 COMMENT '新积分',
              `integral` int(11) NOT NULL DEFAULT 0 COMMENT '改动积分',
              `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1增2减',
              `msg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '明细备注',
              `add_time` int(11) NOT NULL DEFAULT 0 COMMENT '添加时间',
              PRIMARY KEY (`id`) USING BTREE
            ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci  ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        // 判断表是否已存在
        if(!$this->existTable("dq_artificial_integral")){
            $sql = "CREATE TABLE `dq_artificial_integral`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
                  `lebal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户标签1一般用户2测试用户',
                  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型 1增2减',
                  `admin_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '管理员',
                  `old_integral` int(11) NOT NULL DEFAULT 0 COMMENT '操作前积分',
                  `new_integral` int(11) NOT NULL DEFAULT 0 COMMENT '操作后积分',
                  `integral` int(11) NOT NULL DEFAULT 0 COMMENT '积分',
                  `msg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '明细备注',
                  `add_time` int(11) NOT NULL DEFAULT 0 COMMENT '添加时间',
                  PRIMARY KEY (`id`) USING BTREE
                ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci  ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '活动管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '积分记录' AND `permission_url` = '/admin/action/integral_record' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('积分记录', '/admin/action/integral_record', '{$pid}');";
                $this->execute($sql);
            }
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '积分调整' AND `permission_url` = '/admin/action/integral_save' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('积分调整', '/admin/action/integral_save', '{$pid}');";
                $this->execute($sql);
            }
        }

    }

}
