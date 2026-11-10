<?php

namespace app\manage\factory;
use app\common\lib\Respons;

class IndexFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

}