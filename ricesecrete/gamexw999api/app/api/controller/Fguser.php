<?php
namespace app\controller;

use app\common\FastGame\Base\BaseController;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\User;

class Fguser extends BaseController
{
    public function userinfo(){
        return User::getUserInfo();
    }

}
