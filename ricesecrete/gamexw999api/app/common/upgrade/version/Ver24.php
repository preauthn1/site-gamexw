<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver24 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_user_level", "type")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '计算方式 0累计充值金额 1单次充值金额';";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '用户登录日志' AND `permission_url` = '/admin/user_login_log/list' AND `pid` = '46' ";
        if(!$this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('用户登录日志', '/admin/user_login_log/list', '46');";
            $this->execute($sql);
        }
    }

}
