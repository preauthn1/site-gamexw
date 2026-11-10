<?php
namespace app\promote\controller;
use app\common\model\UserLoginModel;
use app\common\lib\Respons;
use think\facade\View;
use think\Request;
use UAParser\Parser;
use app\manage\factory\UserloginlogFactory;

class UserLoginLog extends Base
{	
    public function list(Request $request)
    {
        $rsps = (new UserloginlogFactory($this->user_id))->list();
        return $rsps;
    }
}