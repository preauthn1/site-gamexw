<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver107 extends VersionBase {

    public function run() {
        // 代理站点配置
        if (!$this->existTable("dq_user_agent_website_config")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_agent_website_config`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `website_name` varchar(255) NOT NULL COMMENT '网站名称',
  `website_web_url` varchar(500) NOT NULL COMMENT '域名，多个英文逗号隔开，例：test.com,test1.com',
  `website_favicon` varchar(500) NULL DEFAULT NULL COMMENT '浏览器icon（32*32）',
  `website_pc_logo` varchar(500) NULL DEFAULT NULL COMMENT 'PC LOGO（284*70）',
  `website_desktop_logo` varchar(500) NULL DEFAULT NULL COMMENT '加载logo（1024*1024）',
  `website_mobile_logo` varchar(500) NULL DEFAULT NULL COMMENT '移动端 LOGO（100*100）',
  `website_uid` int(11) NOT NULL COMMENT '代理对应用户id',
  `website_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1启用）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '用户代理网站配置';
SQL;
            $this->execute($sql);
        }
        // 代理站点配置
        if (!$this->existTable("dq_user_agent_website_lang_item")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_agent_website_lang_item`  (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `lang_id` int(10) NULL DEFAULT 0,
  `item_id` int(10) NULL DEFAULT 0,
  `text` text NULL,
  `uid` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_lang`(`lang_id`) USING BTREE,
  INDEX `idx_item`(`item_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = '代理语言翻译';
SQL;
            $this->execute($sql);
        }

        // 语言是否公共
        if (!$this->existTableField("dq_lang_items", "is_public")){
            $sql = "ALTER TABLE `dq_lang_items` ADD COLUMN `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是公共（1是，0否）';";
            $this->execute($sql);
        }

        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'website_name') ){
            //添加网站对应参数
            $this->addSysSets(
                'website_name', '网站名称', '', 'string', '站点配置'
            );
            $this->addSysSets(
                'website_web_url', '网站域名', '', 'string', '站点配置', '域名，多个英文逗号隔开，例：test.com,test1.com'
            );
            $this->addSysSets(
                'website_favicon', '浏览器icon', '', 'string', '站点配置', '浏览器icon（32*32）'
            );
            $this->addSysSets(
                'website_pc_logo', 'PC端 LOGO', '', 'string', '站点配置', 'PC LOGO（284*70）'
            );
            $this->addSysSets(
                'website_desktop_logo', '桌面 LOGO', '', 'string', '站点配置', '移动端保存到桌面的LOGO（1024*1024）  '
            );
            $this->addSysSets(
                'website_mobile_logo', '移动端 LOGO', '', 'string', '站点配置', '移动端 LOGO（100*100）'
            );
        }

        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'whatsapp_public_url') ){
            //添加网站对应参数
            $this->addSysSets(
                'whatsapp_public_url', 'WhatsApp链接', '', 'string', '站点配置', '多个链接用逗号隔开，前端将随机获取一个链接'
            );
        }

        // 语言是否公共
        if (!$this->existTableField("dq_user_agent", "website_auth")){
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `website_auth` tinyint(1) NOT NULL DEFAULT 0 COMMENT '站点权限（1开启，0关闭）';";
            $this->execute($sql);
        }

        //安卓下载链接
        if (!$this->existTableField("dq_user_agent_website_config", "android_download_url")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `android_download_url` varchar(500) COMMENT '安卓下载链接';";
            $this->execute($sql);
        }

        //ios下载链接
        if (!$this->existTableField("dq_user_agent_website_config", "ios_download_url")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `ios_download_url` varchar(500) COMMENT 'iOS下载链接';";
            $this->execute($sql);
        }

        //pc端客服链接
        if (!$this->existTableField("dq_user_agent_website_config", "pc_service_url")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `pc_service_url` varchar(500) COMMENT 'pc端客服链接';";
            $this->execute($sql);
        }

        //移动端客服链接
        if (!$this->existTableField("dq_user_agent_website_config", "mobile_service_url")){
            $sql = "ALTER TABLE `dq_user_agent_website_config` ADD COLUMN `mobile_service_url` varchar(500) COMMENT '移动端客服链接';";
            $this->execute($sql);
        }

        //修改系统设置中的安卓下载链接、ios下载链接、pc端客服链接、移动端客服链接为站点目录下
        Db::name('sys_sets')->where('set_code', 'in', [
            'android_download_url', 'ios_download_url', 'pc_service_url', 'mobile_service_url'
        ])->where('set_label', '=', '基础设置')->update([
            'set_label' => '站点配置'
        ]);
    }
}