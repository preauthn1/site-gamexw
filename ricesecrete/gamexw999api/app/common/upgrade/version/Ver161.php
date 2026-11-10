<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver161 extends VersionBase {

    public function run() {
        //系统类目表
        if (!$this->existTable("dq_system_lang")) {
            $sql = <<<SQL
CREATE TABLE `dq_system_lang`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(100) NOT NULL COMMENT '语言',
  `title` varchar(100) NOT NULL COMMENT '标题',
  `agent_ids` varchar(255) NOT NULL DEFAULT '0' COMMENT '能看到的代理id（多个逗号分割）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型（0图片，1文本，2富文本）',
  `key` varchar(255) NOT NULL COMMENT 'key',
  `text` longtext NULL COMMENT '对应值',
  PRIMARY KEY (`id`),
  INDEX `type`(`type`) USING BTREE
) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '前端类目' ROW_FORMAT = Dynamic;
SQL;
            $this->execute($sql);
        }


        $langNodeId = $this->getNodeIdByNodeUrl('/language');
        if ($langNodeId) {
            $langWebNodeId = $this->getNodeIdByNodeUrl('/language/website');
            if (!$langWebNodeId) {
                //前端类目
                $nCNodeId = $this->addPublicParentNode(
                    '前端类目',
                    '/language/webLang',
                    'webLang',
                    '/language/webLang',
                    $langNodeId,
                    0,
                    true
                );
            }
        }
    }
}