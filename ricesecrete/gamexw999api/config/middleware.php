<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'Language' => app\manage\middleware\Language::class,//后台语言选择
        'agentCheckLogin' => app\agent\middleware\CheckLogin::class,//代理后台登录验证
    	'ApiCheckLogin' => app\api\middleware\CheckLogin::class,//api登录验证
        'Agent' => app\api\middleware\Agent::class,//代理
        'ShareCheckReg' => app\api\middleware\CheckReg::class,//分享注册
        'AllowCrossDomain' => app\api\middleware\Cores::class,
        'ManageAllowCrossDomain' => app\manage\middleware\Cores::class,
        'SaBa' => app\api\middleware\SaBa::class,
        'ThreeGame' => app\api\middleware\ThreeGame::class,
        'ThirdGame' => app\api\middleware\ThirdGame::class,
        'ManageCheckLogin' => app\manage\middleware\CheckLogin::class,//总后台api登录验证
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
];
