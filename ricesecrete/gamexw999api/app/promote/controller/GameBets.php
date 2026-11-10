<?php
namespace app\promote\controller;

use app\common\lib\Respons;
use app\manage\factory\GameBetsFactory;
use app\common\model\{GameBetsModel, UserModel, GameInfosModel};
use think\facade\{Session, View, Request, Config, Db};

class GameBets extends Base
{
    //列表
    public function lists(){
        //$user = $this->user;
        return (new GameBetsFactory($this->user_id))->lists('bytree');
    }

    public function listsexport(){
        return (new GameBetsFactory($this->user_id))->exportList('bytree');
    }

}