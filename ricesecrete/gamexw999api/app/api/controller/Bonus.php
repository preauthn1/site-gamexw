<?php

namespace app\api\controller;

use app\api\factory\BonusFactory;
use app\common\lib\Respons;
use think\Request;

class Bonus{

    //获取所有奖金列表
    public function getBonusList(Request $request){
        $result = (new BonusFactory($request->token->uid))->BonusList();
        return Respons::run($result);
    }


}