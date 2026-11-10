<?php

namespace app\command;

use app\common\FastGame\Base\Third;
use app\common\Utils;
use app\model\Games;
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
class ThirdSyncBet extends Command
{
    protected function configure() {
        $this->setName('third:sync:bet')->setDescription('像三方同步对账交易记录')
            ->addArgument("code", Argument::REQUIRED, '三方平台code，dq_third_configs表中的tc_code')
            ->addArgument("day", Argument::OPTIONAL, '日期');
    }
    protected function execute(Input $input, Output $output) {
        $day = $input->getArgument('day');
        $code = $input->getArgument('code');
        if (empty($day)) {
            $day = Carbon::yesterday()->format("Y-m-d");
        }
        $tc = ThirdConfigs::where('tc_code', $code)->find();
        $cfgs = Games::getConfigs(null, $tc['tc_id']);
        $start_time = Utils::getQueryDateTime($day);
        while (true) {
            try {
                $rlt = $this->get($start_time, $cfgs);
                if (!$rlt) {
                    break;
                }
                $list = $rlt['r_list'];
                $start_time = $rlt['next'];

            } catch (\Exception $e) {
                Utils::L('ThirdSyncBetError', [$e->getMessage(), $e->getTraceAsString()]);
                break;
            }
        }
    }

    private function get($start_time, $cfgs) {
        $ohterkvs = [
            'start' => $start_time
        ];
        if (isset($cfgs['apis']['GETBETLIST'])) {
            return Third::run($cfgs,'GETBETLIST',$ohterkvs);
        }

        return false;
    }

    private function handle($list) {
        if (empty($list)) {
            return;
        }
        foreach ($list as $row) {
            $bets = Db::name('game_bets')->where('gb_sn', $row)->find();
        }
    }
}