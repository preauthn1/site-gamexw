<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver9 extends VersionBase {    
    public function run() {
        // 版本升级自动执行代码
        
        // 判断表是否已存在
        if(!$this->existTable("dq_third_set")){
         $sql = "CREATE TABLE `dq_third_set` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `third_sys_sign` varchar(255) DEFAULT '',
                  `name` text COMMENT '游戏平台名称',
                  `status` int(1) DEFAULT '0' COMMENT '0不启用1启用',
                  `sort` int(11) DEFAULT '0' COMMENT '排序',
                  `appid` varchar(255) DEFAULT '',
                  `secretkey` varchar(255) DEFAULT '',
                  `callback` varchar(255) DEFAULT '',
                  `icon` varchar(255) DEFAULT '',
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
         $this->execute($sql);
        }

        if(!$this->existTable("dq_user_third")){
            $sql = "CREATE TABLE `dq_user_third` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` int(11) DEFAULT NULL,
                      `third_uid` varchar(255) DEFAULT '',
                      `third_sys_sign` varchar(255) DEFAULT '',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }

    }

}
