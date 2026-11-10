<?php

namespace app\api\controller;

use app\api\factory\ServiceUrlFactory;
use app\api\redis\ApiRedis;
use app\common\model\ServiceUrlModel;
use app\common\service\ServiceUrlService;
use app\common\service\SystemService;

class ServiceUrl{

    //获取telegramPc链接
    public function telegramPc(){
        (new ServiceUrlFactory())->telegramPc();
    }

    //获取telegramPhone链接
    public function telegramPhone(){
        (new ServiceUrlFactory())->telegramPhone();
    }

    //获取WhatsApp
    public function whatsapp(){
        (new ServiceUrlFactory())->whatsapp();
    }

}