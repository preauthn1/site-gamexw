<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver28 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表是否已存在
        if(!$this->existTable("dq_service")){
            $sql = "CREATE TABLE `dq_service`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '客服名称',
                  `img` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '图标',
                  `introduce` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL COMMENT '客服介绍',
                  `link` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT '' COMMENT '客服链接',
                  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理id',
                  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0关闭 1启用',
                  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
                  PRIMARY KEY (`id`) USING BTREE
                ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci  COMMENT = '客服管理列表' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '站务管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服管理' AND `permission_url` = '/admin/service/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服管理', '/admin/service/list', '{$pid}');";
                $this->execute($sql);
            }
        }

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服管理' AND `permission_url` = '/admin/service/list'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];

            $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服删除' AND `permission_url` = '/admin/service/delete' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服删除', '/admin/service/delete', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服添加' AND `permission_url` = '/admin/service/add' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服添加', '/admin/service/add', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服修改' AND `permission_url` = '/admin/service/edit' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服修改', '/admin/service/edit', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服状态修改' AND `permission_url` = '/admin/service/status_edit' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服状态修改', '/admin/service/status_edit', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '客服选择代理线' AND `permission_url` = '/admin/agent_report/index' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('客服选择代理线', '/admin/agent_report/index', '{$pid}');";
                $this->execute($sql);
            }

        }

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '赛事资讯' AND `permission_url` = '/admin/news/lists'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $id = $selectData[0]['id'];
            $sql = "UPDATE `{$full_table_name}` SET `permission_name` = '基础问答' WHERE `id` = '{$id}';";
            $this->execute($sql);

        }

        $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '资讯添加' AND `permission_url` = '/admin/news/add'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $id = $selectData[0]['id'];
            $sql = "UPDATE `{$full_table_name}` SET `permission_name` = '问答添加' WHERE `id` = '{$id}';";
            $this->execute($sql);
        }

        $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '资讯修改' AND `permission_url` = '/admin/news/edit'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $id = $selectData[0]['id'];
            $sql = "UPDATE `{$full_table_name}` SET `permission_name` = '问答修改' WHERE `id` = '{$id}';";
            $this->execute($sql);
        }

        $select_sql= "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '资讯删除' AND `permission_url` = '/admin/news/delete'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $id = $selectData[0]['id'];
            $sql = "UPDATE `{$full_table_name}` SET `permission_name` = '问答删除' WHERE `id` = '{$id}';";
            $this->execute($sql);
        }


        if (!$this->existTableIndex("dq_agent_rake_back", "idx_create_time")) {
            $sql = "ALTER TABLE `dq_agent_rake_back` ADD INDEX `idx_create_time` (`create_time`);";
            $this->execute($sql);
        }
    }

}
