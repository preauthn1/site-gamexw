<?php

namespace app\command;

use app\common\model\ServiceUrlLogModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class HandleServiceUrl extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('handle:url')
            ->setDescription('Handle service url');//处理客服链接
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        //处理链接
        $this->handleUrl();

    }

    //处理链接
    public function handleUrl(){
        $urlCacheUrlKey = 'handle_url';
        $stopNum = 500;
        $saveAll = [];
        $successNum = 0;
        while (count(Cache::store('redis')->lrange($urlCacheUrlKey, 0, -1)) > 0 && $successNum < $stopNum){
            //取出链接数据
            $data = json_decode(Cache::store('redis')->lpop($urlCacheUrlKey), true);
            $saveAll[] = $data;
            $successNum++;
        }
        if( $saveAll ){
            (new ServiceUrlLogModel())->saveAll($saveAll);
        }
        $this->log('success num :'.$successNum);
    }



    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}