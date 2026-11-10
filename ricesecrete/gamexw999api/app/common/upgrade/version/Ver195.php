<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver195 extends VersionBase
{
    public function run()
    {
        if (!$this->existTable("dq_user_extra")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_extra`  (
  `uid` int(11) NOT NULL COMMENT '用户id',
  `whatsapp` varchar(255) NULL DEFAULT NULL COMMENT 'WhatsApp',
  `telegram` varchar(255) NULL DEFAULT NULL COMMENT 'telegram',
  `twitter` varchar(255) NULL DEFAULT NULL COMMENT 'twitter',
  `facebook` varchar(255) NULL DEFAULT NULL COMMENT 'facebook',
  PRIMARY KEY (`uid`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT='用户额外信息表';
SQL;
            $this->execute($sql);
        }
    }
}