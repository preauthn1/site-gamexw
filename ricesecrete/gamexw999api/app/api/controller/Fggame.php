<?php
namespace app\controller;

use app\common\FastGame\Base\BaseController;
use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\ThirdTask;
use app\common\FastGame\Base\User;

class Fggame extends BaseController
{
    public function gameinfo(){
        return Game::getGameInfo();
    }

    public function gamelist(){
        return Game::getGameList(1);
    }

    public function gameurl(){
        return Game::getGameUrl();
    }

    public function getbetlist(){
        ThirdTask::run();
    }

}
