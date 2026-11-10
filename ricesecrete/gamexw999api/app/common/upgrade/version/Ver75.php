<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver75 extends VersionBase {

    public function run() {
        //会员管理
        $userNode = Db::name('admin_node')->where('node_url', '/user')->find();
        if($userNode) {
            $userNodeId = $userNode['id'];
            //等级领取记录
            $userLevelNode = Db::name('admin_node')->where('node_url', '/user/pages/levelRecord')->find();
            if(empty($userLevelNode)){
                //列表
                $node = [
                    'node_name' => '会员等级奖励',
                    'node_url' => '/user/pages/levelRecord',
                    'path' => '/user/pages/levelRecord',
                    'name' => 'levelRecord',
                    'pid' => $userNodeId,
                    'sort' => 3,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insert($node);
            }
        }

        //奖励金额
        if (!$this->existTableField("dq_user_level", "week_reward_amount")) {
            $sql = "ALTER TABLE `dq_user_level` ADD `week_reward_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '周奖金金额';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_level", "month_reward_amount")) {
            $sql = "ALTER TABLE `dq_user_level` ADD `month_reward_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '月奖金金额';";
            $this->execute($sql);
        }
        //流水倍数
        if (!$this->existTableField("dq_user_level", "savings")) {
            $sql = "ALTER TABLE `dq_user_level` ADD `savings` int(5) NOT NULL DEFAULT 1 COMMENT '奖励流水倍数';";
            $this->execute($sql);
        }

        // 用户等级奖励
        if (!$this->existTable("dq_user_level_reward")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_level_reward`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `level_id` int(11) NOT NULL COMMENT '等级id',
  `level_data` text NOT NULL COMMENT '等级数据快照',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `reward_type` tinyint(11) NOT NULL DEFAULT 1 COMMENT '奖励类型（1按周奖励，2按月奖励）',
  `reward_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '奖励金额',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1正常，0停止）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT = '用户等级奖励';
SQL;
            $this->execute($sql);
        }
    }
}