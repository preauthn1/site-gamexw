<?php
namespace app\controller;

use app\common\FastGame\Base\BaseController;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\Rlt;
use app\common\FastGame\Base\User;
use app\common\FastGame\Third_test;

class Fgtest extends BaseController
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

    public function addSign(){
        $data=[
            "token"=>'userurltoken123456',
            "uexp"=>1234567,
            'url'=>'https://www.baidu.com/token/userurltoken123456',
            'sign'=>'12343213123'
        ];
        return Rlt::getok(
            [
            (new Third_test())->MakeSign(["cfg"=>["gc_api_prikey"=>'test123465']],$data),$data
            ]);
    }

    public function apitest(){
        return Rlt::getok([
            "gourl"=>"http://twt.51panhuo.com/abc/",
            "phone"=>'2电话',
            "sign"=>"7F3830D8991E19438B87586B850FF98A",
            "uid"=>2
        ]);
    }

    public function getuserurl(){
        return Rlt::getok([
            "token"=>'userurltoken123456',
            "uexp"=>1234567,
            'url'=>'https://www.baidu.com/token/userurltoken123456',
            'sign'=>'8D071AA85015055B5EEAA612F4A31BA5'
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
