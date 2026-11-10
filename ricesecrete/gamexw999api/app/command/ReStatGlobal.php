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

class ReStatGlobal extends Command
{
    protected function configure() {
        $this->setName('re:stat:global')->setDescription('重新统计dq_third_stats_global表数据')
            ->addArgument("day", Argument::OPTIONAL, '统计日期:默认为统计昨天');
        $this->addOption('reset', 'r', Option::VALUE_OPTIONAL, '删除当天统计数据，并重新统计');
    }
    protected function execute(Input $input, Output $output) {
        ini_set('memory_limit', '10240M');#
        $day = $input->getArgument('day');
        if (empty($day)) {
            $day = Carbon::yesterday()->format("Y-m-d");
        }
        $date = Carbon::parse($day)->format("Ymd");
        if ($input->hasOption("reset")) {
            $ret = Db::name('third_stats_global')->where("su_dt", $date)->delete();
            $output->writeln("删除[{$date}]数据:{$ret}");
        }
        $ds = Carbon::parse($day)->timestamp;
        $de = $ds + 86399;
        list('max' => $max_id, 'min' => $min_id) = $this->getBetsIdRange($ds, $de);
        $output->writeln("统计日期:{$date}");
        $output->writeln("开始时间:" . Carbon::now());
        //重新计算gb_otherwin字段
        //优化使用查询慢
//        $betWhere = BetService::getGbidByTime($ds, $de)['sql'];
        $_sql = <<<SQL
UPDATE dq_game_bets SET gb_otherwin = (`gb_winprice` - `gb_price`) WHERE `gb_otherwin` <> (`gb_winprice` - `gb_price`) and `gb_id` between $min_id and $max_id and `gb_bettime` between $ds and $de and `gb_winlose`>0
SQL;
        Db::query($_sql);

        $sql = <<<SQL
select {$date} as su_dt,g_tcid as su_tcid,g_id as su_gid,g_type as su_gtype,rec_id3,rec_id2,rec_id,tree,id as su_uid,lebal as su_label,count(*) as su_betnum,sum(gb_price) as su_bet,
    sum(gb_validwater) as su_valid_bet,
    sum(if(gb_winprice>0,1,0)) as su_winnum,sum(gb_winprice) as su_win,
    sum(IF(gb_validwater<gb_price,1,0)) as su_drawnum,sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as su_draw,
    sum(gb_otherwin*-1) as su_syswin
    from
    dq_user,dq_game_bets
    left join dq_games on gb_gid=g_id
    where gb_uid=id and gb_id between $min_id and $max_id and gb_bettime between $ds and $de and gb_winlose>0 and gb_gcflag > 0 and gb_gcflagt > 0
    group by id,gb_gid
SQL;
        $data = Db::query($sql);
        foreach ($data as $row) {
            $stat = Db::name('third_stats_global')
                ->where('su_dt', $row['su_dt'])
                ->where('su_uid', $row['su_uid'])
                ->where('su_gid', $row['su_gid'])
                ->find();
            if (empty($stat)) {
                Db::name('third_stats_global')->insert($row);
                $output->warning("新插入数据:" . json_encode($row));
                continue;
            }
            if ($stat['su_valid_bet'] != $row['su_valid_bet']
                || $stat['su_bet'] != $row['su_bet']
                || $stat['su_betnum'] != $row['su_betnum']
                || $stat['su_winnum'] != $row['su_winnum']
                || $stat['su_win'] != $row['su_win']
                || $stat['su_syswin'] != $row['su_syswin']
            ) {

                Db::name('third_stats_global')->where('su_dt', $row['su_dt'])
                    ->where('su_uid', $row['su_uid'])
                    ->where('su_gid', $row['su_gid'])->update([
                        'su_valid_bet' => $row['su_valid_bet'],
                        'su_bet' => $row['su_bet'],
                        'su_betnum' => $row['su_betnum'],
                        'su_winnum' => $row['su_winnum'],
                        'su_win' => $row['su_win'],
                        'su_drawnum' => $row['su_drawnum'],
                        'su_draw' => $row['su_draw'],
                        'su_syswin' => $row['su_syswin'],
                    ]);
                $output->error(
                    "{用户ID}: {$row['su_uid']}, {$row['su_dt']}, {$row['su_gid']},有效投注额:{$stat['su_valid_bet']}->{$row['su_valid_bet']},".
                    "总投注额:{$stat['su_bet']}->{$row['su_bet']},".
                    "总投注笔数:{$stat['su_betnum']}->{$row['su_betnum']},".
                    "派彩笔数:{$stat['su_winnum']}->{$row['su_winnum']},".
                    "派彩金额:{$stat['su_win']}->{$row['su_win']},".
                    "平台盈亏:{$stat['su_syswin']}->{$row['su_syswin']},"
                );
            }
        }
        $output->writeln("结束时间:" . Carbon::now());
    }

    //获取用户投注记录表主键的最大值与最小值
    public function getBetsIdRange($ds,$de){
        //优化使用gb_bettime查询慢
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