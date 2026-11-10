<?php

namespace app\common\upgrade\version;

use think\facade\Db;
use function Symfony\Component\Translation\t;

class Ver258 extends VersionBase
{

    public function run()
    {
        //渠道返利 流水倍数 次数限制
        if (!$this->existTableField("dq_rebate_set", "num_limit")) {
            $sql = "ALTER TABLE `dq_rebate_set` ADD COLUMN `num_limit` tinyint(3) NOT NULL  DEFAULT 0 COMMENT '返利次数限制';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_rebate_set", "day")) {
            $sql = "ALTER TABLE `dq_rebate_set` ADD COLUMN `day` tinyint(3) NOT NULL  DEFAULT 0 COMMENT 'x天内只能领取x次';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_rebate_set", "water")) {
            $sql = "ALTER TABLE `dq_rebate_set` ADD COLUMN `water` tinyint(3) NOT NULL  DEFAULT 0 COMMENT '返利流水倍数';";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_pay_set",'rebate_agent_id')) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `rebate_agent_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '充值返利-代理线';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "is_consist")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD `is_consist` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否在内（1是，2否）针对 充值返利-代理线和充值返利-会员标签';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "tag_id")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `tag_id` int NULL DEFAULT 0 COMMENT '充值返利-用户标签ID';";
            $this->execute($sql);
        }





        if (!$this->existTableField("dq_artificial_balance", "rebate_id")) {
            $sql = "ALTER TABLE `dq_artificial_balance` ADD COLUMN `rebate_id` int NULL DEFAULT 0 COMMENT '渠道返利的id 用于判断次数限制';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_artificial_balance", "bank_sk_id")) {
            $sql = "ALTER TABLE `dq_artificial_balance` ADD COLUMN `bank_sk_id` int NULL DEFAULT 0 COMMENT '线下渠道返利的id 用于判断次数限制';";
            $this->execute($sql);
        }




        //渠道返利 流水倍数 次数限制
        if (!$this->existTableField("dq_bank_sk_rebate_set", "num_limit")) {
            $sql = "ALTER TABLE `dq_bank_sk_rebate_set` ADD COLUMN `num_limit` tinyint(3) NOT NULL  DEFAULT 0 COMMENT '返利次数限制';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk_rebate_set", "day")) {
            $sql = "ALTER TABLE `dq_bank_sk_rebate_set` ADD COLUMN `day` tinyint(3) NOT NULL  DEFAULT 0 COMMENT 'x天内只能领取x次';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk_rebate_set", "water")) {
            $sql = "ALTER TABLE `dq_bank_sk_rebate_set` ADD COLUMN `water` tinyint(3) NOT NULL  DEFAULT 0 COMMENT '返利流水倍数';";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_bank_sk",'rebate_agent_id')) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `rebate_agent_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '充值返利-代理线';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk", "is_consist")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD `is_consist` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否在内（1是，2否）针对 充值返利-代理线和充值返利-会员标签';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk", "tag_id")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `tag_id` int NULL DEFAULT 0 COMMENT '充值返利-用户标签ID';";
            $this->execute($sql);
        }

        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '文章管理' AND `node_url` = '/news/list'  AND `name` = 'acticleList'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])){
            $pid = $selectData[0]['id'];
            $_node = $this->getNodeIdByNodeUrlName('/news/agent_list', 'newsAgentList');
            if (!$_node) {
                //教学类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '代理问答',
                    '/news/agent_list',
                    'newsAgentList',
                    '/news/agent_list',
                    $pid,
                    0
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }

            $_node = $this->getNodeIdByNodeUrlName('/news/agent_daan_list', 'newsAgentDaAnList');
            if (!$_node) {
                //教学类型
                $paymenttypeCNodeId = $this->addPublicParentNode(
                    '代理问答-答案',
                    '/news/agent_daan_list',
                    'newsAgentDaAnList',
                    '/news/agent_daan_list',
                    $pid,
                    0
                );
                $this->addPublicChildrenMenuNode($paymenttypeCNodeId);
            }
        }


        if (!$this->existTable("dq_agent_news")) {
            $sql = <<<SQL
                CREATE TABLE `dq_agent_news` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `lang` varchar(30) DEFAULT '' NOT NULL COMMENT '语言种类',
                  `pid` int(11) DEFAULT 0 NOT NULL COMMENT '0的时候是问题，大于0是答案',
--                   `title` text COMMENT '标题',
                  `content` text COMMENT '内容',
                  `icon` varchar(255) DEFAULT '' COMMENT '图标',
                  `image` varchar(255) DEFAULT '' COMMENT '图标',
                  `add_time` int(11) DEFAULT 0 NOT NULL COMMENT '添加时间',
                  `type_id` int(11) DEFAULT 0  NOT NULL COMMENT '类型id',
                  `rec_code` int(11) DEFAULT 0  NOT NULL COMMENT '代理邀请码',
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='教学类型';
                ;
SQL;
            $this->execute($sql);
        }





        $full_table_name = 'dq_admin_node';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '三方收款地址' AND `node_url` = '/user/pages/useraddress'  AND `name` = 'user_address'";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])){
            $pid = $selectData[0]['id'];
            $_node = $this->getNodeIdByNodeUrlPid('delete', $pid);
            if (!$_node) {
                //教学类型
                 $this->addPublicParentNode(
                    '三方收款地址删除',
                    'delete',
                    'delete',
                    'delete',
                    $pid,
                    0
                );
            }
        }


    }
}