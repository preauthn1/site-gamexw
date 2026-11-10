<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver246 extends VersionBase
{

    public function run()
    {
        $nodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('/newstype/list');
            if (!$_node) {
                //公告类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '问答类型',
                    '/newstype/list',
                    'newsType',
                    '/newstype/list',
                    $nodeId,
                    2,
                    false
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }

        //公告类型
        if (!$this->existTableField("dq_news", "type_id")) {
            $sql = "ALTER TABLE `dq_news` ADD COLUMN `type_id` int(11) NOT NULL DEFAULT 0 COMMENT '类型id';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_news_type")) {
            $sql = <<<SQL
                CREATE TABLE `dq_news_type` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(50) DEFAULT '' COMMENT '名称',
                  `icon` varchar(255) DEFAULT '' COMMENT '图标',
                  `sort` int(10) DEFAULT '0' COMMENT '排序',
                  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1启用 2禁用',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='问答类型';
                ;
SQL;
            $this->execute($sql);
        }

        $nodeId = $this->getNodeIdByNodeUrl('/user/pages/list');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('2fa_reset');
            if (!$_node) {
                //重置2FA
                $this->addPublicParentNode(
                    '重置2FA',
                    '2fa_reset',
                    '2fa_reset',
                    '2fa_reset',
                    $nodeId,
                    2,
                    false
                );
            }
        }

        $nodeId = $this->getNodeIdByNodeUrl('/user/pages/list');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('with_pass_reset');
            if (!$_node) {
                //重置2FA
                $this->addPublicParentNode(
                    '重置提现密码',
                    'with_pass_reset',
                    'with_pass_reset',
                    'with_pass_reset',
                    $nodeId,
                    8,
                );
            }
        }


        if (!$this->existTableField("dq_bank_sk", "open_start_time")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `open_start_time` int(11) NOT NULL DEFAULT 0 COMMENT '渠道开放时间';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk", "open_end_time")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `open_end_time` int(11) NOT NULL DEFAULT 0 COMMENT '渠道开放时间';";
            $this->execute($sql);
        }


        $nodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('/teachtype/list');
            if (!$_node) {
                //教学类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '教学类型',
                    '/teachtype/list',
                    'teachType',
                    '/teachtype/list',
                    $nodeId,
                    2,
                    false
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }

        $nodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('/teach/list');
            if (!$_node) {
                //教学类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '教学列表',
                    '/teach/list',
                    'teach',
                    '/teach/list',
                    $nodeId,
                    2,
                    false
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }


        if (!$this->existTable("dq_teach_type")) {
            $sql = <<<SQL
                CREATE TABLE `dq_teach_type` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(50) DEFAULT '' COMMENT '名称',
                  `icon` varchar(255) DEFAULT '' COMMENT '图标',
                  `sort` int(10) DEFAULT '0' COMMENT '排序',
                  `status` tinyint(1) DEFAULT '1' COMMENT '状态 1启用 2禁用',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='教学类型';
                ;
SQL;
            $this->execute($sql);
        }

        $nodeId = $this->getNodeIdByNodeUrl('/zhanwuguanli');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrlName('/news/list', 'acticleList');
            if (!$_node) {
                $node = [
                    'node_name' => '文章管理',
                    'node_url' => '/news/list',
                    'path' => '/news/list',
                    'name' => 'acticleList',
                    'pid' => $nodeId,
                    'is_menu' => 1,
                    'sort' => 2,
                    'status' => 1,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                $nodeId = Db::name('admin_node')->insertGetId($node);
            }
        }

        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '文章管理' AND `node_url` = '/news/list'  AND `name` = 'acticleList'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])){
            $pid = $selectData[0]['id'];
            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '基础问答' AND `node_url` = '/news/list'";
            $selectData2 = $this->getTableData($select_sql2);

            if(isset($selectData2[0])){
                $select_sql3 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '基础问答' AND `node_url` = '/news/list' AND `pid` = $pid  ";
                $selectData3 = $this->getTableData($select_sql3);
                if(!isset($selectData3[0])){
                    //pid不一致的情况下 存在路径 修改pid
                    $id = $selectData2[0]['id'];
                    $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid, `is_menu` = 0 WHERE `id` = '{$id}';";
                    $this->getTableData($sql);
                }
            }else{

                $_node = $this->getNodeIdByNodeUrlName('/news/list', 'newsList');
                if (!$_node) {
                    //教学类型
                    $paymenttypeCNodeId = $this->addPublicParentNode(
                        '基础问答',
                        '/news/list',
                        'newsList',
                        '/news/list',
                        $pid,
                        10,
                        false
                    );
                    $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
                }
            }

            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '问答类型' AND `node_url` = '/newstype/list'";
            $selectData2 = $this->getTableData($select_sql2);
            if(isset($selectData2[0])){
                $select_sql3 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '问答类型' AND `node_url` = '/newstype/list' AND `pid` = $pid  ";
                $selectData3 = $this->getTableData($select_sql3);
                if(!isset($selectData3[0])){
                    //pid不一致的情况下 存在路径 修改pid
                    $id = $selectData2[0]['id'];
                    $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid , `is_menu` = 0 WHERE `id` = '{$id}'";
                    $this->getTableData($sql);
                }
            }


            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '教学列表' AND `node_url` = '/teach/list'";
            $selectData2 = $this->getTableData($select_sql2);
            if(isset($selectData2[0])){
                $select_sql3 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '教学列表' AND `node_url` = '/teach/list' AND `pid` = $pid  ";
                $selectData3 = $this->getTableData($select_sql3);
                if(!isset($selectData3[0])){
                    //pid不一致的情况下 存在路径 修改pid
                    $id = $selectData2[0]['id'];
                    $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid , `is_menu` = 0 WHERE `id` = '{$id}'";
                    $this->getTableData($sql);
                }
            }


            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '教学类型' AND `node_url` = '/teachtype/list'";
            $selectData2 = $this->getTableData($select_sql2);
            if(isset($selectData2[0])){
                $select_sql3 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '教学类型' AND `node_url` = '/teachtype/list' AND `pid` = $pid  ";
                $selectData3 = $this->getTableData($select_sql3);
                if(!isset($selectData3[0])){
                    //pid不一致的情况下 存在路径 修改pid
                    $id = $selectData2[0]['id'];
                    $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid , `is_menu` = 0 WHERE `id` = '{$id}'";
                    $this->getTableData($sql);
                }
            }



        }

        $nodeId = $this->getNodeIdByNodeUrl('/user');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('/user_address/list');
            if (!$_node) {
                //教学类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '三方收款地址',
                    '/user/pages/useraddress',
                    'user_address',
                    '/user/pages/useraddress',
                    $nodeId,
                    15,
                    false
                );
//                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }


        if (!$this->existTableField("dq_user_withdrawal", "submit_third_price")) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `submit_third_price` decimal(20, 6) NOT NULL DEFAULT 0 COMMENT '特殊数字货币使用-提交到三方的实际货币金额';";
            $this->execute($sql);
        }


        if (!$this->existTable("dq_mail_notify_template")) {
            $sql = <<<SQL
                CREATE TABLE `dq_mail_notify_template`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `state` tinyint(1) NOT NULL DEFAULT 2 COMMENT '当前邮件模板状态',
                  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '邮件模板简介',
                  `sign` varchar(50) NOT NULL DEFAULT '' COMMENT '邮件模板标识、用于自动发送营销邮件的时候识别模板',
                  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '邮件模板标题',
                  `content` text NULL COMMENT '邮件模板内容',
                  PRIMARY KEY (`id`)
                )ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知邮件模板';
                SQL;
            $this->execute($sql);
        }

//        if (!$this->existTableField("dq_system", "mail_notify_is_open")) {
//            $sql = "ALTER TABLE `dq_system` ADD COLUMN `mail_notify_is_open` tinyint(1) NOT NULL DEFAULT 2 COMMENT '通知邮件 1开启 2关闭';";
//            $this->execute($sql);
//        }

//        if (!$this->judgeDataIsExist('sys_sets', 'set_code', 'mail_notify_is_open')) {
//            //
//            $this->addSysSets(
//                'mail_notify_is_open', '通知邮件开关', 2, 'int', '通知邮件开关', '通知邮件开关（1开启， 2关闭）'
//            );
//
//            $this->addSysSets(
//                'verify_sta', '实名认证模板状态', 2, 'int', '实名认证模板状态', '实名认证模板状态  1开启  2关闭 '
//            );
//
//
//            $this->addSysSets(
//                'verify_title', '实名认证模板标题', 2, 'string', '实名认证模板标题', '实名认证模板标题'
//            );
//
//            $this->addSysSets(
//                'verify_content', '实名认证模板内容', 2, 'text', '实名认证模板内容', '实名认证模板内容'
//            );
//
//
//            $this->addSysSets(
//                'off_sta', '充值成功模板状态 ', 2, 'int', '充值成功模板状态', '充值成功模板状态  1开启  2关闭 '
//            );
//
//
//            $this->addSysSets(
//                'off_title', '充值成功模板标题', 2, 'string', '充值成功模板标题', '充值成功模板标题'
//            );
//
//            $this->addSysSets(
//                'off_content', '充值成功模板内容', 2, 'text', '充值成功模板内容', '充值成功模板内容'
//            );
//
//
//            $this->addSysSets(
//                'with_sta', '提现成功模板状态 ', 2, 'int', '提现成功模板状态', '提现成功模板状态  1开启  2关闭 '
//            );
//
//
//            $this->addSysSets(
//                'with_title', '提现成功模板标题', 2, 'string', '提现成功模板标题', '提现成功模板标题'
//            );
//
//            $this->addSysSets(
//                'with_content', '提现成功模板内容', 2, 'text', '提现成功模板内容', '提现成功模板内容'
//            );
//
//            $this->addSysSets(
//                'template1_title', '后台行销邮件标题', 2, 'string', '后台行销邮件标题', '后台行销邮件标题'
//            );
//
//            $this->addSysSets(
//                'template1_content', '后台行销邮件内容', 2, 'text', '后台行销邮件内容', '后台行销邮件内容'
//            );
//
//            $this->addSysSets(
//                'template2_title', '后台行销邮件标题', 2, 'string', '后台行销邮件标题', '后台行销邮件标题'
//            );
//
//            $this->addSysSets(
//                'template2_content', '后台行销邮件内容', 2, 'text', '后台行销邮件内容', '后台行销邮件内容'
//            );
//
//            $this->addSysSets(
//                'template3_title', '后台行销邮件标题', 2, 'string', '后台行销邮件标题', '后台行销邮件标题'
//            );
//
//            $this->addSysSets(
//                'template3_content', '后台行销邮件内容', 2, 'text', '后台行销邮件内容', '后台行销邮件内容'
//            );
//
//
//        }

        if (!$this->existTableField("dq_email_configs", "notify_status")) {
            $sql = "ALTER TABLE `dq_email_configs` ADD COLUMN `notify_status` tinyint(1) NOT NULL DEFAULT 2 COMMENT '邮件通知状态 1开启 2关闭';";
            $this->execute($sql);
        }

        $nodeId = $this->getNodeIdByNodeUrl('/user/pages/list');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('plays_mail');
            if (!$_node) {
                //重置2FA
                $this->addPublicParentNode(
                    '批量发送邮件',
                    'plays_mail',
                    'plays_mail',
                    'plays_mail',
                    $nodeId,
                    2,
                    false
                );
            }
        }

        $nodeId = $this->getNodeIdByNodeUrl('/email/configs');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrl('mail_configs');
            if (!$_node) {
                //重置2FA
                $this->addPublicParentNode(
                    '营销邮件配置',
                    'mail_configs',
                    'mail_configs',
                    'mail_configs',
                    $nodeId,
                    2,
                    false
                );
            }
        }

        $nodeId = $this->getNodeIdByNodeUrl('/system');
        if ($nodeId) {
            $_node = $this->getNodeIdByNodeUrlName('/email/configs', 'emailConfigsList');
            if (!$_node) {
                $node = [
                    'node_name' => '三方邮箱管理',
                    'node_url' => '/email/configs',
                    'path' => '/email/configs',
                    'name' => 'emailConfigsList',
                    'pid' => $nodeId,
                    'is_menu' => 1,
                    'sort' => 2,
                    'status' => 1,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                $nodeId = Db::name('admin_node')->insertGetId($node);
            }
        }

        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '三方邮箱管理' AND `node_url` = '/email/configs'  AND `name` = 'emailConfigsList'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])){

            $pid = $selectData[0]['id'];
            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '三方邮箱管理' AND `node_url` = '/email/configs'";
            $selectData2 = $this->getTableData($select_sql2);
            if(isset($selectData2[0])){
                $id = $selectData2[0]['id'];
                $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid, `is_menu` = 0 WHERE `id` = '{$id}';";
                $this->getTableData($sql);
            }


            $pid = $selectData[0]['id'];
            $select_sql2 = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '邮件模板配置' AND `node_url` = '/email/template'";
            $selectData2 = $this->getTableData($select_sql2);
            if(isset($selectData2[0])){
                $id = $selectData2[0]['id'];
                $sql = "UPDATE `{$full_table_name}` SET `pid` = $pid AND `is_menu` = 0 WHERE `id` = '{$id}';";
                $this->getTableData($sql);
            }else{
                    $nodeId = $this->getNodeIdByNodeUrlName('/email/template', 'emailTemplate');
                    if (!$nodeId) {
                        //教学类型
                        $paymenttypeCNodeId = $this->addPublicParentNode(
                            '营销邮件模板',
                            '/email/template',
                            'emailTemplate',
                            '/email/template',
                            $pid
                        );
                        $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
                    }
            }


            if (!$this->existTableField("dq_user", "auto_mail_notify_sta")) {
                $sql = "ALTER TABLE `dq_user` ADD COLUMN `auto_mail_notify_sta` tinyint(1) NOT NULL DEFAULT 1 COMMENT '自动触发邮件提醒 1开启 2关闭、比如实名、提现、充值';";
                $this->execute($sql);
            }

            if (!$this->existTableField("dq_user", "admin_mail_notify_sta")) {
                $sql = "ALTER TABLE `dq_user` ADD COLUMN `admin_mail_notify_sta` tinyint(1) NOT NULL DEFAULT 1 COMMENT '后台手动发送营销短信提醒、1开启 2关闭';";
                $this->execute($sql);
            }


        }
    }

}
