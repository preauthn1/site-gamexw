<?php

namespace app\command;

use app\manage\factory\GameFactory;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Cache;

class ReportCache extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('third:reportCache')
            ->setDescription('game report cache');//游戏报表缓存
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;

        $cacheKey = 'report_game_cache';
        while (count(Cache::store('redis')->lrange($cacheKey, 0, -1)) > 0){
            //取出链接数据
            $params = json_decode(Cache::store('redis')->lpop($cacheKey), true);
            if( isset($params['act']) && $params['act'] == 'detail' ){
                (new GameFactory($params['user_id']))->report_detail('', $params);
            }else{
                (new GameFactory($params['user_id']))->report('', $params);
            }
        }
        $this->log();
    }


    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}