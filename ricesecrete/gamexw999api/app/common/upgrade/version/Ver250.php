<?php

namespace app\common\upgrade\version;

class Ver250 extends VersionBase
{

    public function run()
    {
        if (!$this->existTableField("dq_user_extra", "click_id")) {
            $sql = "ALTER TABLE `dq_user_extra` ADD COLUMN `click_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'Scaleo cid ';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_extra", "ac_id")) {
            $sql = "ALTER TABLE `dq_user_extra` ADD COLUMN `ac_id` int NOT NULL DEFAULT 0 COMMENT '广告渠道ID';";
            $this->execute($sql);
        }

        if (!$this->existTable("dq_scaleo_report")) {
            $sql = <<<SQL
CREATE TABLE `dq_scaleo_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL DEFAULT '0' COMMENT '用户ID',
  `game_type` int DEFAULT '0' COMMENT '游戏类型',
  `type` varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT '' COMMENT '类型:bet,win,bon',
  `hour` datetime DEFAULT NULL,
  `count` int DEFAULT '0',
  `amount` decimal(20,4) DEFAULT '0.0000',
  `create_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scaleo统计表';
SQL;
            $this->execute($sql);
        }
    }

}
