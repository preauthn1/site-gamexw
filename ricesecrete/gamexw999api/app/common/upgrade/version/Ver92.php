<?php

namespace app\common\upgrade\version;

class Ver92 extends VersionBase
{
    public function run() {
        if (!$this->existTableField("dq_user", "ac_id")) {
            $sql = <<<SQL
ALTER TABLE `dq_user` 
ADD COLUMN `ac_id` int NULL DEFAULT 0 COMMENT '广告渠道ID', 
ADD COLUMN `reg_url` varchar(100) NULL DEFAULT '' COMMENT '注册网址';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_login", "url")) {
            $sql = "ALTER TABLE `dq_user_login` ADD COLUMN `url` varchar(250) NULL DEFAULT '' COMMENT '登陆URL';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_ad_channel")) {
            $sql = <<<SQL
CREATE TABLE `dq_ad_channel` (
  `ac_id` int(11) NOT NULL AUTO_INCREMENT,
  `ac_adtype` varchar(20) DEFAULT '' COMMENT '广告商类型',
  `ac_name` varchar(255) DEFAULT NULL COMMENT '渠道名称',
  `ac_code` varchar(100) DEFAULT '' COMMENT '渠道代码',
  `ac_share_code` varchar(10) DEFAULT '' COMMENT '绑定代理邀请码',
  `ac_url` varchar(255) DEFAULT '' COMMENT '渠道绑定域名',
  `ac_state` tinyint(1) DEFAULT '1' COMMENT '启用',
  `ac_user` int(11) DEFAULT '0' COMMENT '注册用户数',
  `ac_reg_time` int(11) DEFAULT NULL COMMENT '最后注册用户时间',
  `ac_remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`ac_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告商渠道表';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTable("dq_ads")) {
            $sql = <<<SQL
CREATE TABLE `dq_ads` (
  `ad_id` int(11) NOT NULL AUTO_INCREMENT,
  `ad_type` varchar(50) DEFAULT '' COMMENT '广告商类型',
  `ad_name` varchar(100) DEFAULT '' COMMENT '广告商名称',
  `ad_code` varchar(100) DEFAULT '' COMMENT '广告通用代码',
  PRIMARY KEY (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告商表';
SQL;
            $this->execute($sql);
        }

        $pid = $this->getNodeIdByName("系统管理");
        //广告商管理
        $this->addPublicParentNode(
            '广告商管理',
            '/ads/list',
            'adsList',
            '/ads/list',
            $pid,
            20,
            true
        );
        //广告渠道管理
        $this->addPublicParentNode(
            '广告渠道管理',
            '/ads/channel',
            'adsChannel',
            '/ads/channel',
            $pid,
            30,
            true
        );
    }
}