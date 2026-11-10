<?php

namespace app\command;

use app\common\service\BetService;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class ReStatUser extends Command
{
    protected function configure() {
        $this->setName('re:stat:user')->setDescription('重新统计third_stats_users表数据')
            ->addArgument("day", Argument::OPTIONAL, '统计日期:默认为统计昨天');
        $this->addOption('reset', 'r', Option::VALUE_OPTIONAL, '删除当天统计数据，并重新统计');
    }
    protected function execute(Input $input, Output $output) {
        $day = $input->getArgument('day');
        if (empty($day)) {
            $day = Carbon::yesterday()->format("Y-m-d");
        }
        $date = Carbon::parse($day)->format("Ymd");
        if ($input->hasOption("reset")) {
            $ret = Db::name('third_stats_users')->where("su_dt", $date)->delete();
            $output->writeln("删除[{$date}]数据:{$ret}");
        }
        $ds = Carbon::parse($day)->timestamp;
        $de = $ds + 86399;
        list('max' => $max_id, 'min' => $min_id) = $this->getBetsIdRange($ds, $de);
        $output->writeln("统计日期:{$date}");
        $output->writeln("开始时间:" . Carbon::now());
        //优化使用查询慢
//        $betWhere = BetService::getGbidByTime($ds, $de)['sql'];
        $sql = <<<SQL
select {$date} as su_dt,FROM_UNIXTIME(gb_bettime,'%Y%m%d') as su_dt2,rec_id3,rec_id2,rec_id,tree,id as su_uid,username,count(*) as su_betnum,sum(gb_price) as su_bet,
    sum(gb_validwater) as su_validwater,
    sum(if(gb_winprice>0,1,0)) as su_winnum,
    sum(gb_winprice) as su_win,
    sum(IF(gb_validwater<gb_price,1,0)) as su_drawnum,
    sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as su_draw,
    sum(gb_otherwin) as su_otherwin
    from
    dq_user,dq_game_bets
    where gb_uid=id and gb_id between $min_id and $max_id and gb_bettime between $ds and $de and gb_winlose>0 and gb_cflagt > 0 and gb_cflag > 0
    group by gb_uid
SQL;
        $output->writeln($sql);
        $data = Db::query($sql);
        foreach ($data as $row) {
            $stat = Db::name('third_stats_users')
                ->where('su_dt', $row['su_dt'])
                ->where('su_uid', $row['su_uid'])
                ->find();
            if (empty($stat)) {
                $output->warning("新插入数据:" . json_encode($row));
                unset($row['su_dt2']);
                Db::name('third_stats_users')->insert($row);
                continue;
            }
            if ($stat['su_validwater'] != $row['su_validwater']
                || $stat['su_bet'] != $row['su_bet']
                || $stat['su_betnum'] != $row['su_betnum']
                || $stat['su_winnum'] != $row['su_winnum']
                || $stat['su_win'] != $row['su_win']
                || $stat['su_otherwin'] != $row['su_otherwin']
            ) {

                Db::name('third_stats_users')->where('su_dt', $row['su_dt'])
                    ->where('su_uid', $row['su_uid'])->update([
                        'su_validwater' => $row['su_validwater'],
                        'su_bet' => $row['su_bet'],
                        'su_betnum' => $row['su_betnum'],
                        'su_winnum' => $row['su_winnum'],
                        'su_win' => $row['su_win'],
                        'su_drawnum' => $row['su_drawnum'],
                        'su_draw' => $row['su_draw'],
                        'su_otherwin' => $row['su_otherwin'],
                    ]);
                $output->error(
                    "{用户ID}: {$row['su_uid']}, {$row['su_dt']}, 有效投注额:{$stat['su_validwater']}->{$row['su_validwater']},".
                    "总投注额:{$stat['su_bet']}->{$row['su_bet']},".
                    "总投注笔数:{$stat['su_betnum']}->{$row['su_betnum']},".
                    "派彩笔数:{$stat['su_winnum']}->{$row['su_winnum']},".
                    "派彩金额:{$stat['su_win']}->{$row['su_win']},".
                    "平台盈亏:{$stat['su_otherwin']}->{$row['su_otherwin']},"
                );
            }
        }
        $output->writeln("结束时间:" . Carbon::now());
    }

    //获取用户投注记录表主键的最大值与最小值
    public function getBetsIdRange($ds,$de){
        //优化使用查询慢
//        $betWhere = BetService::getGbidByTime($ds, $de)['sql'];
//        $sql="select min(gb_id) as min_id,max(gb_id) as max_id  from dq_game_bets where {$betWhere}";
        $sql="select min(gb_id) as min_id,max(gb_id) as max_id  from dq_game_bets where gb_bettime BETWEEN {$ds} and {$de}";
        $rlt=Db::query($sql);
        $min_id=0;
        $max_id=0;
        if ($rlt && count($rlt)==1){
            $min_id=$rlt[0]['min_id']??$min_id;
            $max_id=$rlt[0]['max_id']??$max_id;
        }
        return ["max"=>$max_id,"min"=>$min_id];
    }
}