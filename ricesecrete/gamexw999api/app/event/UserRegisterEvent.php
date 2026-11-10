<?php

namespace app\event;

class UserRegisterEvent
{
    public $user;
    public function __construct($user)
    {
        $this->user = $user;
    }
}