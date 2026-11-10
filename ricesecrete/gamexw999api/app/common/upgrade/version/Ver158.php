<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver158 extends VersionBase
{

    public function run()
    {
        //箭活动表
        if (!$this->existTable("dq_rocket_activity")) {
            $sql = <<<SQL
CREATE TABLE `dq_rocket_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lang` varchar(50) DEFAULT '' COMMENT '语种',
  `name` varchar(50) DEFAULT '' COMMENT '活动名称',
  `condition` tinyint(2) DEFAULT '1' COMMENT '参与条件 0不限制  1完成首充  2完成复充',
  `all_mileage_min` float(10,2) DEFAULT '0.00' COMMENT '起点',
  `all_mileage_max` float(10,2) DEFAULT '0.00' COMMENT '最大里程',
  `savings` int(10) DEFAULT '0' COMMENT '流水倍数',
  `agent_id` varchar(255) DEFAULT '0' COMMENT '限制代理',
  `tag_id` int(11) NOT NULL DEFAULT '0' COMMENT '标签限制（可参与）',
  `vip_id` int(11) NOT NULL DEFAULT '0' COMMENT 'VIP限制（可参与）',
  `introduce` text COMMENT '活动说明',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（1启用，0禁用）',
  `start_time` int(11) DEFAULT '0' COMMENT '活动开始时间',
  `end_time` int(11) DEFAULT '0' COMMENT '活动结束时间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='⽕箭活动';
SQL;
            $this->execute($sql);
        }

        //参与火箭活动记录表
        if (!$this->existTable("dq_rocket_activity_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_rocket_activity_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rid` int(11) DEFAULT '0' COMMENT '火箭活动id',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `now_mileage` float(10,2) DEFAULT '0.00' COMMENT '当前总里程',
  `now_level` int(10) DEFAULT '1' COMMENT '当前高度区间',
  `now_get_level` int(10) DEFAULT '0' COMMENT '当前领取的宝箱区间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参与火箭活动记录';
SQL;
            $this->execute($sql);
        }

        //箭活动阶梯配置表
        if (!$this->existTable("dq_rocket_activity_level")) {
            $sql = <<<SQL
CREATE TABLE `dq_rocket_activity_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rid` int(11) DEFAULT '0' COMMENT '火箭活动id',
  `level` int(10) DEFAULT '1' COMMENT '阶梯级别 1一级阶梯 ',
  `sec_mileage_min` float(10,2) DEFAULT '0.00' COMMENT '里程区间最小值',
  `sec_mileage_max` float(10,2) DEFAULT '0.00' COMMENT '里程区间最大值',
  `mileage_unit` varchar(10) DEFAULT 'KM' COMMENT '里程单位',
  `award_value` varchar(50) DEFAULT '' COMMENT '到达最大里程奖励数量 区间',
  `award_type` tinyint(3) DEFAULT '1' COMMENT '到达最大里程奖励类型  1余额  2积分',
  `transf_ratio` float(6,2) NOT NULL DEFAULT '1.00' COMMENT '下注金额与燃料转化比例',
  `use_fuel_num` varchar(50)  DEFAULT '' COMMENT '每次消耗的燃料数量区间',
  `use_fuel_mil` varchar(50)  DEFAULT '' COMMENT '消耗一次燃料 前进的随机公里数区间',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='⽕箭活动阶梯配置';
SQL;
            $this->execute($sql);
        }

        //参与燃料消耗记录表
        if (!$this->existTable("dq_rocket_activity_fuel_log")) {
            $sql = <<<SQL
CREATE TABLE `dq_rocket_activity_fuel_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rid` int(11) DEFAULT '0' COMMENT '火箭活动id',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `num` int(5) NOT NULL DEFAULT '0' COMMENT '燃料消耗次数',
  `mileage` float(15,2) DEFAULT '0.00' COMMENT '前进公里数量',
  `all_mileage` float(15,2) DEFAULT '0.00' COMMENT '累计前进里程',
  `use_fuel` float(15,2) DEFAULT '0.00' COMMENT '消耗的燃料',
  `use_money` float(15,2) DEFAULT '0.00' COMMENT '兑换下注前的消耗金额',
  `transf_ratio` float(6,2) DEFAULT '1.00' COMMENT '下注金额与燃料转化比例',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参与燃料消耗记录';
SQL;
            $this->execute($sql);
        }

        //参与宝箱开启记录表
        if (!$this->existTable("dq_rocket_activity_box_log")) {
            $sql = <<<SQL
CREATE TABLE `dq_rocket_activity_box_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `rid` int(11) DEFAULT '0' COMMENT '火箭活动id',
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `type` tinyint(2) DEFAULT '1' COMMENT '类型  1获取宝箱 ',
  `level` int(10) NOT NULL DEFAULT '1' COMMENT '奖励区间',
  `savings` int(10) DEFAULT '0' COMMENT '流水倍数',
  `award_value` float(15,2) DEFAULT '0.00' COMMENT '奖励数量',
  `award_type` tinyint(3) DEFAULT '0' COMMENT '到达最大里程奖励类型  1余额  2积分',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参与宝箱开启记录';
SQL;
            $this->execute($sql);
        }
        $sNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if ($sNodeId) {
            $pNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/rocketActivity');
            if (empty($pNodeId)) {
                //火箭活动
                $nCNodeId = $this->addPublicParentNode(
                    '火箭活动',
                    '/huodongguanli/rocketActivity',
                    'rocketActivity',
                    '/huodongguanli/rocketActivity',
                    $sNodeId,
                    11
                );
                //火箭活动区间级别
                $this->addPublicParentNode(
                    '火箭活动区间级别',
                    'rocketActivityLevel',
                    'rocketActivityLevel',
                    'rocketActivityLevel',
                    $nCNodeId,
                    1
                );
                $this->addPublicChildrenNode($nCNodeId);

            }

            $pNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/rocketActivityRecord');
            if (empty($pNodeId)) {
                //火箭活动记录
                $nCNodeId = $this->addPublicParentNode(
                    '火箭活动记录',
                    '/huodongguanli/rocketActivityRecord',
                    'rocketActivityRecord',
                    '/huodongguanli/rocketActivityRecord',
                    $sNodeId,
                    12
                );
                $this->addPublicParentNode(
                    '宝箱记录',
                    'rocketActivityBox',
                    'rocketActivityBox',
                    'rocketActivityBox',
                    $nCNodeId,
                    1
                );
                $this->addPublicParentNode(
                    '加注燃料记录',
                    'rocketActivityFuel',
                    'rocketActivityFuel',
                    'rocketActivityFuel',
                    $nCNodeId,
                    2
                );
                $this->addPublicChildrenNode($nCNodeId);
            }
        }
    }
}