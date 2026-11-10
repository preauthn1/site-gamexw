<?php
namespace app\controller;

use app\common\FastGame\Base\BaseController;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\Rlt;
use app\common\FastGame\Base\User;

class Fgindex extends BaseController
{
    public function index()
    {
        return 'fgindex';

    }

    public function test(){
        echo $this->params->gid;
        echo $this->params->pid;
        echo Params::$gid;
        echo getp('pid');


    }

    public function apitest(){
        return Rlt::getok([
            "gourl"=>"http://twt.51panhuo.com/abc/",
            "phone"=>'2电话',
            "sign"=>"7F3830D8991E19438B87586B850FF98A",
            "uid"=>2
        ]);
    }

    public function register(){
        return User::register();
    }

    public function logout(){
        return User::loginOut();
    }

    public function login(){
        return User::login($this->params);
    }



}
