<?php

namespace app\manage\factory;

class SystemFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }
}