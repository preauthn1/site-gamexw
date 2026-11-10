<?php

namespace app\command;

use app\model\Games;
use app\model\ThirdConfigs;
use Carbon\Carbon;
use GuzzleHttp\Client;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\common\FastGame\Base\Redis;

class RsLottery extends Command
{
    protected function configure() {
        // 分钟任务
        $this->setName('rs:lottery')
            ->addArgument("game_type", Argument::REQUIRED, '游戏分类')
            ->setDescription('获取飞彩API最新彩种信息');
    }
    protected function execute(Input $input, Output $output) {
        $game_type = $input->getArgument('game_type');
        $output->writeln("开始时间:" . Carbon::now());
        $tc = ThirdConfigs::where('tc_code', 'IN', ['JSLGAMESTRANS', 'JSLGAMES'])->find();
        $games = Games::where('g_tcid', $tc['tc_id'])->where('g_type', $game_type)->field('g_id,g_code')->select()->toArray();
        $domain = $tc['tc_api_urlpre'];
        $key = "rs:lottery_curr";
        $client = new Client();
        foreach ($games as $game) {
            list(, $id) = explode("|", "{$game['g_code']}||||||");
            $data = json_decode(Redis::hget($key, $id), true);
            if ($data && $data['end_time'] > time()) {
                $data = json_encode($data);
                Redis::hset($key, $game['g_id'], $data);
                Redis::hset($key, $game['g_code'], $data);
                continue;
            }
            try {
                $response = $client->get($domain . "/lottery/curr", ['query' => ['code' => $game['g_code']]]);
                $result = json_decode($response->getBody()->getContents(), true);
                if ($result['code'] == 1) {
                    $data = json_encode($result['data']);
                    Redis::hset($key, $id, $data);
                    Redis::hset($key, $game['g_id'], $data);
                    Redis::hset($key, $game['g_code'], $data);
                }
            } catch(\Exception $e) {
                $output->error($e->getMessage());
            }
        }
        $output->writeln("结束时间:" . Carbon::now());
    }

}