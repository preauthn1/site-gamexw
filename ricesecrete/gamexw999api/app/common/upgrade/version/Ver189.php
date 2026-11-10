<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver189 extends VersionBase
{

    public function run()
    {
        if ($this->existTable("dq_red_packet")) {
            if (!$this->existTableField("dq_red_packet", 'model_day')) {
                //增加字段
                $sql = "ALTER TABLE `dq_red_packet` ADD COLUMN `model_day` varchar(200) CHARACTER SET utf8mb4  DEFAULT '' COMMENT '允许天数  1周一， 7周天';";
                $this->execute($sql);
            }
            if (!$this->existTableField("dq_red_packet", 'max_award')) {
                //增加字段
                $sql = "ALTER TABLE `dq_red_packet` ADD COLUMN `max_award` decimal(20,2) DEFAULT '0.00' COMMENT '最高可得奖励';";
                $this->execute($sql);
            }
        }

        if ($this->existTable("dq_red_packet_log")) {
            if (!$this->existTableField("dq_red_packet_log", 'validity_time')) {
                //增加字段
                $sql = "ALTER TABLE `dq_red_packet_log` ADD COLUMN  `validity_time` varchar(30) DEFAULT '' COMMENT '参与的时间区间';";
                $this->execute($sql);
            }
            if (!$this->existTableField("dq_red_packet_log", 'tid')) {
                //增加字段
                $sql = "ALTER TABLE `dq_red_packet_log` ADD COLUMN   `tid` int(11) DEFAULT NULL COMMENT '时间区间id';";
                $this->execute($sql);
            }
        }
        if (!$this->existTable("dq_red_packet_time")) {
            $sql = <<<SQL
CREATE TABLE `dq_red_packet_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rid` int(10) DEFAULT '0' COMMENT '活动id',
  `model` tinyint(1) DEFAULT '1' COMMENT '模式  1每日  2每周',
  `week` int(2) DEFAULT '0' COMMENT '有效日期 0每天  周一、1周二 、2周三 、4周四 、5周五 、6周六、7周天',
  `status` tinyint(1) DEFAULT '1' COMMENT '1启用  2禁用',
  `validity_time` text COMMENT '活动有效参与时间段',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '领取时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `rid` (`rid`),
  KEY `ind_r_a` (`rid`) USING BTREE,
  KEY `inde_r_aw` (`rid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='红包雨领取记录';
SQL;
            $this->execute($sql);
        }
        $zgNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/huodongguanli/redPacket');
            if ($NodeId) {
                $this->addPublicParentNode(
                    '时间区间设置',
                    '/huodongguanli/redPacketDayList',
                    'redPacketDayList',
                    '/huodongguanli/redPacketDayList',
                    $NodeId,
                    1
                );
            }

        }
    }
}