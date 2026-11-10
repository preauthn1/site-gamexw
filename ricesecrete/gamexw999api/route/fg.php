<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;


Route::get('index', 'fgindex/index');

Route::get('test', 'fgindex/test');

//TODO any 全部换为POST
Route::any('register','fgindex/register');
Route::any('login','fgindex/login');
Route::get('logout','fgindex/logout');

Route::get('gameinfo','fggame/gameinfo');
Route::get('gamelist','fggame/gamelist');

Route::get('makesigntest','fgtest/addSign');

Route::group('op',function(){

    Route::get('thirdtest','fgtest/apitest');
    Route::get('tgetuserurl','fgtest/getuserurl');

    Route::get('userinfo','fguser/userinfo');
    Route::get('gameurl','fggame/gameurl');

})->middleware(['CheckLogin']);
