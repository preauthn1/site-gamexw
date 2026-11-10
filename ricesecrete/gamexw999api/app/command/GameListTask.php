<?php
declare (strict_types = 1);

namespace app\command;

use app\api\factory\GameFactory;
use think\console\{Input, Output, Command};
use think\console\input\{Argument, Option};
use app\common\model\GameSetModel;
use think\helper\Str;

//查询某个平台指定时间的所有记录
class GameListTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('GameListTask')
            ->setDescription('get_game_list');
    }

    protected function execute(Input $input, Output $output)
    {

        try{
            $this->operation('OG');
            $output->writeln("=== 游戏列表生成成功===");
        }catch (\Exception $e){
            $output->writeln("=== 游戏列表生成异常===");
        }

    }

    public function  operation($code){
        return (new GameFactory())->get_three_game_list($code);
    }
}
