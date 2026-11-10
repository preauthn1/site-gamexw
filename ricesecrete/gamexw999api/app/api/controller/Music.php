<?php

namespace app\api\controller;

use app\api\factory\MusicFactory;
use app\common\lib\Respons;

class Music{

    //获取菜单节点
    public function index(){
        $result = (new MusicFactory())->getMusicList();
        return Respons::run($result);
    }

}