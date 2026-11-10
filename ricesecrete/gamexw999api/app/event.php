<?php
// 事件定义文件
return [
    'bind'      => [
        'UserLogin' => 'app\event\UserLogin',
        'UserRegister' => 'app\event\UserRegisterEvent',
        'LotteryOpenTime' => 'app\event\LotteryOpenTimeEvent',
        'ActivityTask' => 'app\event\ActivityTaskEvent',
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'UserLogin' => [
            'app\listener\UserLogin',
        ],
        'LotteryOpenTime' => [
            'app\listener\LotteryOpenTimeListener',
        ],
        'ActivityTask' => [
            'app\listener\ActivityTaskListener',
        ],
        'UserRegister' => [
            'app\listener\UserRegisterListener',
        ],
    ],

    'subscribe' => [
    ],
];
