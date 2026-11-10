<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver61 extends VersionBase {

    public function run() {
        // 彩票相关 start
        if (!$this->existTable("dq_user_collect")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_collect` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` tinyint(2) NOT NULL DEFAULT 0 COMMENT '类型（0游戏，其他待定）',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `other_id` int(11) NOT NULL COMMENT '类型对应id（0对应游戏id）',
  `other_data` text NOT NULL COMMENT '对应数据快照',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏';
SQL;
            $this->execute($sql);
        }

        $out_agent_node = Db::name('admin_node')->where('node_url', '/threeGames/gameRebateOut')->find();
        if(empty($out_agent_node)) {
            $pid = Db::name('admin_node')->where('node_name', '第三方游戏')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '外部代理返佣',
                    'node_url' => '/threeGames/gameRebateOut',
                    'path' => '/threeGames/gameRebateOut',
                    'name' => 'gameRebateOut',
                    'pid' => $pid,
                    'sort' => 7,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

    }
}