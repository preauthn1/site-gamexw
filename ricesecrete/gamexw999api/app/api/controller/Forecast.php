<?php

namespace app\api\controller;

use app\api\factory\ForecastFactory;
use app\common\lib\Respons;
use app\common\model\ForecastGameModel;
use app\common\model\ForecastModel;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use think\Request;

class Forecast{

    public function index(){
        $result = (new ForecastFactory())->index();
        return Respons::run($result);
    }

}