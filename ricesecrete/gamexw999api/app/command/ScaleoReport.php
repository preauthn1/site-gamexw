<?php

namespace app\command;

use app\common\lib\Uploads;
use app\common\lib\Util;
use app\common\model\TaskModel;
use app\common\redis\CommomRedis;
use app\common\Utils;
use app\model\ThirdConfigs;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;
use app\common\FastGame\Base\Redis;

/**
 * 统计Scaleo需要的报表
 */
class ScaleoReport extends Command
{
    public $hour = 3;
    public $cover_hour = 1;
    protected function configure() {
        $this->setName('scaleo:report')->setDescription('统计Scaleo需要的报表')
            ->addArgument('is_reset', Argument::OPTIONAL, '是否重置统计时间');
    }
    protected function execute(Input $input, Output $output) {
        $isReset = $input->getArgument('is_reset');
        if ($isReset) {
            $start_key = "scaleo:bet:start";
            Redis::set($start_key, 0);
            $start_key = "scaleo:win:start";
            Redis::set($start_key, 0);
            $start_key = "scaleo:bon:start";
            Redis::set($start_key, 0);
            $this->output->writeln("已经重置,请重新统计:" . Carbon::now());
            return;
        }
        $mysql_timezone = env("mysql_timezone");
        if (!$mysql_timezone) {
            $mysql_timezone = Carbon::now()->getTimezone()->toOffsetTimeZone();
        }
        $sql = "set time_zone='{$mysql_timezone}'";
        Db::execute($sql);
        $this->stat('bet');
        $this->stat('win');
        // 优惠
        $this->stat('bon');
    }

    private function stat($type) {
        $hour = $this->hour;
        $cover_hour = $this->cover_hour;
        $now = strtotime(date('Y-m-d H:00:00'));
        $this->output->writeln("开始时间:" . Carbon::now());
        $start_key = "scaleo:{$type}:start";
        $start_time = Redis::get($start_key);
        if (!$start_time) {
            if ($type == 'bon') {
                $tmp = Db::name('user')->fieldRaw("add_time as time,id")->order('add_time')->find();
            } else {
                $tmp = Db::name('game_bets')->fieldRaw("gb_intime as time,gb_id")->order('gb_intime')->find();
            }
            if (empty($tmp)) {
                $this->output->writeln("没有数据,结束:" . Carbon::now());
                return;
            }
            $start_time = strtotime(date("Y-m-d H:00:00", $tmp['time']));
        }

        if ($start_time >= $now) {
            $this->output->writeln("大于当前时间,结束:" . Carbon::now());
            return;
        }
        $end_time = $start_time + $hour * 60 * 60;
        Redis::set($start_key, $start_time  + ($hour - ($hour - $cover_hour)) * 60 * 60);
        $start_date = date('Y-m-d H:i:s', $start_time);
        $end_date = date('Y-m-d H:i:s', $end_time);
        $this->output->warning("{$type}==统计开始时间:{$start_date},结束时间:{$end_date}");
        try {
            if ($type == 'bon') {
                $data = $this->bon($start_time, $end_time);
            } else {
                $price_field = $type == 'win' ? 'gb_winprice' : 'gb_price';
                $sql = <<<SQL
SELECT 
`gb_uid` as uid, '{$type}' as type, (select `g_type` FROM `dq_games` WHERE `g_id` = `gb_gid`) as game_type,
FROM_UNIXTIME(gb_overtime,'%Y-%m-%d %H:00:00') as `hour`, 
SUM({$price_field}) as amount, COUNT(`gb_id`) as count 
FROM `dq_game_bets` 
WHERE `gb_winlose` > 1 AND {$price_field} != 0 AND `gb_overtime` >= {$start_time} and `gb_overtime` < {$end_time}
GROUP BY `game_type`,`hour`,`gb_uid` ORDER BY `game_type`,`hour`,`gb_uid`;
SQL;

                $this->output->writeln($sql);
                $data = Db::query($sql);
            }
            $this->handleData($data);
        } catch (\Exception $e) {
            $this->output->error($e->getMessage());
        }
        $this->output->writeln("结束时间:" . Carbon::now());
    }

    private function bon($start_time, $end_time) {
        $sqls = $this->getBonSql($start_time, $end_time);
        $sql = implode(' UNION ALL ', $sqls);
        $sql = "SELECT `uid`, `type`, `game_type`, `hour`, SUM(`amount`) as amount, COUNT(id) as count FROM ($sql) as tb where uid > 0 GROUP BY `game_type`,`hour`,`uid` ORDER BY `game_type`,`hour`,`uid`";
        $this->output->writeln($sql);
        $data = Db::query($sql);
        return $data;
    }

    private function getBonSql($start_time, $end_time){
        $tbs = [
            // 人工赠送彩金 + 会员等级彩金
            [
                'tb' => 'artificial_balance',
                'time' => 'add_time',
                'amount' => 'price',
                'where' => [
                    'a_type' => 3
                ]
            ],
            [
                'tb' => 'artificial_balance',
                'time' => 'add_time',
                'amount' => 'price',
                'where' => [
                    'a_type' => 4
                ]
            ],
            // 免费转盘奖励
            [
                'tb' => 'lottery_record',
                'time' => 'time',
                'amount' => 'price',
                'where' => [
                    'is_free' => 1,
                    'prize_type' => 1,
                ]
            ],
            // 优惠活动奖金
            [
                'tb' => 'action_apply',
                'time' => 'add_time',
                'amount' => '`gd_amount`+`ck_hl_amount`',
                'where' => [
                    'ps_status' => 1
                ]
            ],
            // 推广奖励
            [
                'tb' => 'agent_sell_log',
                'time' => 'create_time',
                'amount' => 'money',
                'where' => [
                    'status' => 3
                ]
            ],
            //任务奖励
            [
                'tb' => 'task_record',
                'time' => 'create_time',
                'amount' => 'reward_amount_or_integral',
                'where' => [
                    'reward_type' => TaskModel::BALANCE_REWARD_TYPE,
                    'receive_status' => 1
                ]
            ],
            //VIP等级周月奖励
            [
                'tb' => 'user_level_reward',
                'time' => 'create_time',
                'amount' => 'reward_amount',
                'where' => [
                ]
            ],
            //内部代理返佣
            [
                'tb' => 'third_stats_fyfy',
                'time' => 'sff_paytime',
                'amount' => 'sff_back',
                'uid' => 'sff_uid',
                'pk' => 'sff_id',
                'where' => [
                    'sff_payed' => 2
                ]
            ],
            //领取码
            [
                'tb' => 'receive_code_record',
                'time' => 'create_time',
                'amount' => 'reward_amount',
                'where' => [
                ]
            ],
            //签到
            [
                'tb' => 'sign_record',
                'time' => 'create_time',
                'amount' => 'reward_amount',
                'where' => [
                ]
            ],
            //PDD转盘
            [
                'tb' => 'pdd_withdrawal_record',
                'time' => 'create_time',
                'amount' => 'amount',
                'where' => [
                ]
            ],
            //五福
            [
                'tb' => 'wufu_withdrawal',
                'time' => 'create_time',
                'amount' => 'amount',
                'where' => [
                ]
            ]
        ];
        $sqls = [];
//        dump($tbs);
        $game_type = 90000;
        foreach ($tbs as $tb) {
            $uid = $tb['uid'] ?? 'uid';
            $pk = $tb['pk'] ?? 'id';
            $time = $tb['time'];
            $amount = $tb['amount'];
            $table = $tb['tb'];
            $sql = "SELECT {$pk} as id,{$uid} as uid, 'bon' as type, {$game_type} as game_type, FROM_UNIXTIME(`{$time}`,'%Y-%m-%d %H:00:00') as `hour`, {$amount} as amount FROM `dq_{$table}` WHERE `{$time}` >= {$start_time} and `{$time}` < {$end_time}";

//            dump($tb);
            $where = [];
            if (isset($tb['where']) && !empty($tb['where'])) {
                foreach ($tb['where'] as $key => $val) {
                    $where[] = "{$key} = {$val}";
                }
            }
            if (!empty($where)) {
                $sql .= " AND " . implode(" AND ", $where);
            }

            $sqls[] = $sql;
//            $game_type += 1000;
        }

        return $sqls;
    }

    private function handleData($data) {
        foreach ($data as $row) {
            // 过滤0的数据
            if (empty($row['amount'])) {
                continue;
            }
            $row['create_time'] = time();
            $stat = Db::name('scaleo_report')
                ->where('uid', $row['uid'])
                ->where('hour', $row['hour'])
                ->where('type', $row['type'])
                ->where('game_type', $row['game_type'])
                ->find();
            if (empty($stat)) {
                Db::name('scaleo_report')->insert($row);
                $this->output->warning("新插入数据:" . json_encode($row));
                continue;
            }

            if ($stat['count'] != $row['count'] || $stat['amount'] != $row['amount']) {
                Db::name('scaleo_report')
                    ->where('uid', $row['uid'])
                    ->where('hour', $row['hour'])
                    ->where('type', $row['type'])
                    ->where('game_type', $row['game_type'])
                    ->update([
                        'count' => $row['count'],
                        'amount' => $row['amount'],
                    ]);
                $this->output->error(
                    "{$row['uid']}, {$row['hour']}, {$row['game_type']}, {$row['type']},".
                    "总投注额:{$stat['amount']}->{$row['amount']},".
                    "总投注笔数:{$stat['count']}->{$row['count']},"
                );
            }
        }
    }
}