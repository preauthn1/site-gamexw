<?php

namespace app\api\controller;

use app\api\factory\UrlFactory;
use app\common\lib\Respons;

class Url{

    //获取菜单节点
    public function getMenu(){
        $result = (new UrlFactory())->getMenu();
        return Respons::run($result);
    }

}