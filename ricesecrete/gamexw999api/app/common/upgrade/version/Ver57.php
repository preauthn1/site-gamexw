<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver57 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_tag", "color")) {
            $sql = "ALTER TABLE `dq_tag` ADD COLUMN `color` varchar(10) NULL DEFAULT '' COMMENT '颜色';";
            $this->execute($sql);
        }

        $user_stat_node = Db::name('admin_node')->where('node_url', '/threeGames/gameUserStat')->find();
        if(empty($user_stat_node)) {
            $pid = Db::name('admin_node')->where('node_name', '第三方游戏')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => '用户数据总览',
                    'node_url' => '/threeGames/gameUserStat',
                    'path' => '/threeGames/gameUserStat',
                    'name' => 'gameUserStat',
                    'pid' => $pid,
                    'sort' => 90,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

        $ip_node = Db::name('admin_node')->where('node_url', '/report/iprank')->find();
        if(empty($ip_node)) {
            $pid = Db::name('admin_node')->where('node_name', '报表管理')->value('id');
            if ($pid) {
                $node = [
                    'node_name' => 'IP统计',
                    'node_url' => '/report/iprank',
                    'path' => '/report/iprank',
                    'name' => 'report_iprank',
                    'pid' => $pid,
                    'sort' => 50,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertGetId($node);
            }
        }

        if (!$this->existTableField("dq_user_msg", "data")) {
            $sql = "ALTER TABLE `dq_user_msg` ADD COLUMN `data` varchar(355) NULL DEFAULT '' COMMENT '变量数据';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_operating", "bet_draw")) {
            $sql = <<<SQL
ALTER TABLE `dq_operating` 
ADD COLUMN `bet_draw` decimal(20, 2) NOT NULL DEFAULT 0 COMMENT '投注和局金额' AFTER `total_bet`,
ADD COLUMN `bet_valid_amount` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注金额' AFTER `bet_draw`;
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_user_withdrawal", "type")) {
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `type` INT(1) NULL DEFAULT 0 COMMENT '提现类型 0 用户 1代理';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "agent_service_charge")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `agent_service_charge` INT(5) NULL DEFAULT 0 COMMENT '代理提现手续费';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_system", "agent_usdt_charge")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `agent_usdt_charge` INT(5) NULL DEFAULT 0 COMMENT '代理USDT提现手续费';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_bets", "gb_trans_no")) {
            $sql = "ALTER TABLE `dq_game_bets` ADD COLUMN `gb_trans_no` varchar(128) NULL DEFAULT null COMMENT '交易号';";
            $this->execute($sql);
        }
    }
}