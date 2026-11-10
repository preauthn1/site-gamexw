<?php

namespace app\api\controller;

use app\api\factory\WuFuFactory;
use app\common\lib\Respons;
use think\Request;

class Wufu{

    //获取五福数据
    public function getData(){
        $result = (new WuFuFactory())->getData();
        return Respons::run($result);
    }

    //刮卡
    public function open(){
        $result = (new WuFuFactory())->open();
        return Respons::run($result);
    }

    //刮卡
    public function handleOpen(){
        $result = (new WuFuFactory())->handleOpen();
        return Respons::run($result);
    }

    //领取卡片
    public function receiveCard(Request $request){
        $result = (new WuFuFactory())->receiveCard();
        return Respons::run($result);
    }

}