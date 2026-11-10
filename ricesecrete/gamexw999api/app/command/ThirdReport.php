<?php

namespace app\command;

use app\common\lib\Uploads;
use app\common\lib\Util;
use app\common\Utils;
use app\model\ThirdConfigs;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;

/**
 * 统计三方对账报表
 */
class ThirdReport extends Command
{
    protected function configure() {
        $this->setName('third:report')->setDescription('重新统计dq_third_report表数据')
            ->addArgument("day", Argument::OPTIONAL, '统计/导出日期:默认为统计昨天，导出时传月份')
            ->addArgument("type", Argument::OPTIONAL, '类型:stat,export', 'stat');
    }
    protected function execute(Input $input, Output $output) {
        $day = $input->getArgument('day');
        $type = $input->getArgument('type');
        if ($type == 'export') {
            $month = Carbon::parse($day)->startOfMonth()->format('Y年m月');
            $start_date = Carbon::parse($day)->startOfMonth()->format('Ymd');
            $end_date = Carbon::parse($day)->endOfMonth()->format('Ymd');
            $thirds = ThirdConfigs::select();
            foreach ($thirds as $third) {
                $data = Db::name('third_report')
                    ->where('tr_tcid', $third['tc_id'])
                    ->where('tr_timezone', $third['tc_timezone'])
                    ->whereBetween('tr_dt', [$start_date, $end_date])
                    ->field(Db::raw("tr_dt,SUM(tr_bet) as bet, SUM(tr_valid_bet) as valid_bet, SUM(tr_betnum) as betnum, SUM(tr_win) as win, SUM(tr_syswin) as syswin"))
                    ->group('tr_dt')
                    ->order('tr_dt ASC')
                    ->select();
                $header = [
                    'tr_dt' => '日期',
                    'bet' => '总投注',
                    'valid_bet' => '有效投注',
                    'betnum' => '投注笔数',
                    'win' => '派彩金额',
                    'syswin' => '平台盈亏',
                ];
                $file_name = genCsv($header, $data,$third['tc_code'] . "-{$month}-时区{$third['tc_timezone']}-对账报表" . date('YmdHis',time()), false, "storage/report");
//                $s3_url = Uploads::uploadS3(public_path() . $file_name);
                $output->writeln(getWebUrl(false) . '/' . $file_name);
            }
//            dump($thirds);
        } else {
            if (empty($day)) {
                $day = Carbon::yesterday()->format("Y-m-d");
            }
            $third_timezones = ThirdConfigs::distinct(true)->column('tc_timezone');
            $tzs = array_unique(array_merge([-5, -4, -3, 0, 1, 4, 8], $third_timezones));
            foreach ($tzs as $tz) {
                $this->stat($day, $tz, $output);
            }
        }
    }

    private function stat($day, $tz, Output $output) {
        $output->writeln("显示目标时区时间UTC({$tz}):" . date("Y-m-d 00:00:00", strtotime($day)));
        // 转换时区后在本系统时间
        $s_time = Util::getTimeZoneConversion(Utils::getQueryDateTime($day), $tz);
        $local_date = Carbon::createFromTimestamp($s_time);
        $offset = $local_date->getTimezone()->getOffset(Carbon::now('UTC'));
        $output->writeln("转换到本系统时间UTC(" . ($offset/3600). "):" . $local_date->format("Y-m-d H:i:s"));
        $date = Carbon::parse($day)->format("Ymd");
        $ds = $s_time;
        $de = $ds + 86399;
        if ($de >= time()) {
            $output->writeln("时间大于当前时间，无法统计");
            return;
        }
        list('max' => $max_id, 'min' => $min_id) = $this->getBetsIdRange($ds, $de);
        $output->writeln("开始时间:" . Carbon::now());
        $sql = <<<SQL
select {$date} as tr_dt,{$tz} as tr_timezone,gb_tcid as tr_tcid,gb_gid as tr_gid,count(DISTINCT gb_uid) as tr_betuser,count(*) as tr_betnum,sum(gb_price) as tr_bet,
    sum(gb_validwater) as tr_valid_bet,
    sum(if(gb_winprice>0,1,0)) as tr_winnum,sum(gb_winprice) as tr_win,
    sum(IF(gb_validwater<gb_price,1,0)) as tr_drawnum,sum(IF(gb_validwater<gb_price,gb_price-gb_validwater,0)) as tr_draw,
    sum(gb_otherwin*-1) as tr_syswin
    from dq_game_bets
    where gb_id between $min_id and $max_id and gb_bettime between $ds and $de and gb_winlose>0
    group by gb_gid
SQL;

        $output->writeln($sql);
//        return;
        $data = Db::query($sql);
        foreach ($data as $row) {
            $stat = Db::name('third_report')
                ->where('tr_dt', $row['tr_dt'])
                ->where('tr_timezone', $row['tr_timezone'])
                ->where('tr_gid', $row['tr_gid'])
                ->find();
            if (empty($stat)) {
                Db::name('third_report')->insert($row);
                $output->warning("新插入数据:" . json_encode($row));
                continue;
            }
            if ($stat['tr_valid_bet'] != $row['tr_valid_bet']
                || $stat['tr_bet'] != $row['tr_bet']
                || $stat['tr_betnum'] != $row['tr_betnum']
                || $stat['tr_winnum'] != $row['tr_winnum']
                || $stat['tr_win'] != $row['tr_win']
                || $stat['tr_syswin'] != $row['tr_syswin']
            ) {

                Db::name('third_report')->where('tr_dt', $row['tr_dt'])
                    ->where('tr_timezone', $row['tr_timezone'])
                    ->where('tr_gid', $row['tr_gid'])->update([
                        'tr_valid_bet' => $row['tr_valid_bet'],
                        'tr_bet' => $row['tr_bet'],
                        'tr_betnum' => $row['tr_betnum'],
                        'tr_winnum' => $row['tr_winnum'],
                        'tr_win' => $row['tr_win'],
                        'tr_drawnum' => $row['tr_drawnum'],
                        'tr_draw' => $row['tr_draw'],
                        'tr_syswin' => $row['tr_syswin'],
                    ]);
                $output->error(
                    "{$row['tr_dt']}, {$row['tr_tcid']}, {$row['tr_gid']},有效投注额:{$stat['tr_valid_bet']}->{$row['tr_valid_bet']},".
                    "总投注额:{$stat['tr_bet']}->{$row['tr_bet']},".
                    "总投注笔数:{$stat['tr_betnum']}->{$row['tr_betnum']},".
                    "派彩笔数:{$stat['tr_winnum']}->{$row['tr_winnum']},".
                    "派彩金额:{$stat['tr_win']}->{$row['tr_win']},".
                    "平台盈亏:{$stat['tr_syswin']}->{$row['tr_syswin']},"
                );
            }
        }
        $output->writeln("结束时间:" . Carbon::now());
    }

    public function getBetsIdRange($ds,$de){
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