<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;
require __DIR__ . '/../vendor/autoload.php';
// 执行HTTP应用并响应
$origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: *');//表示只允许POST请求 
header('Access-Control-Allow-Headers: x-requested-with,Content-Type,Authorization,x-xsrf-token,usertokenkey,usertoken,lang,origin,noToken');

$http = (new App())->http;

$response = $http->name('manage')->run();

$response->send();

$http->end($response);
