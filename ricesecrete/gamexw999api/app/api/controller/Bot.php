<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\BotFactory;
use think\Request;
class Bot
{
    public function webhook(Request $request){
        $rsps=(new BotFactory())->hook_run();
        return Respons::run($rsps);
    }
}