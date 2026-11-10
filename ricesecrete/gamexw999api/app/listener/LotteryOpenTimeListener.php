<?php

namespace app\listener;

use Carbon\Carbon;
use think\facade\Db;

class LotteryOpenTimeListener {

    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle(\app\event\LotteryOpenTimeEvent $event) {
        $lottery = $event->lottery;
        $total = Db::name('s_lottery_opentime')->where('lottery_id', $lottery->id)->count();
        if ($total == $lottery->lottery_th) {
            return;
        }
        Db::name('s_lottery_opentime')->where('lottery_id', $lottery->id)->delete();
        $step = 1440 / $lottery->lottery_th;
        $opentimes = [];
        for($i = 1; $i <= $lottery->lottery_th; $i++) {
            $minute = ($i - 1) * $step;
            $open_time = Carbon::today()->addMinute($minute)->format('H:i:s');
            $close_time = Carbon::today()->addMinute($minute + $step)->subSecond(5)->format('H:i:s');
            $draw_time = Carbon::today()->addMinute($minute + $step)->format('H:i:s');
            $row = [
                'lottery_id' => $lottery->id,
                'qishu' => $i,
                'open_time' => $open_time,
                'close_time' => $close_time,
                'draw_time' => $draw_time,
            ];
            $opentimes[] = $row;
        }
        Db::name('s_lottery_opentime')->insertAll($opentimes);
    }
}