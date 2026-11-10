<?php

use think\facade\Route;

Route::group(function () {
//    Route::post('login', 'user/login');
//    Route::post('logout', 'user/logout');
//    Route::get('test', 'user/test');
    Route::rule('i18n/config', 'web_config/getI18n');
    //登录注册
    Route::rule('login/captcha', 'login/captcha'); //验证码
    Route::post('login/login', 'login/login'); //登录
})->middleware([
    // 跨域请求支持
    \think\middleware\AllowCrossDomain::class,
]);

Route::group(function () {
    Route::get('/getAsyncRoutes', 'user/getNav'); //获取菜单

    //微商管理
    Route::group('agent_report', function () {
        Route::get('index', 'index'); //微商报表
        Route::get('user', 'user'); //微商下级用户报表
    })->prefix('agent_report/');

    //会员管理
    Route::group('user', function () {
        Route::rule('list', 'list'); //用户列表
        Route::get('my', 'my'); //用户信息
        Route::rule('complete_info', 'complete_info'); //完善用户信息
        Route::rule('excel', 'excel');//用户导出
        Route::rule('add', 'add');//添加用户
        Route::rule('detail', 'detail');//用户详情
        Route::rule('ipstatistics', 'ipstatistics');//IP统计
        Route::rule('running_water', 'running_water');//流水统计
        Route::rule('water', 'water');//用户流水
    })->prefix('user/');
    Route::rule('user_login_log/list', 'user_login_log/list');//用户登录日志列表
    Route::rule('user_admin_log/list', 'user_admin_log/list');//用户操作记录
    Route::rule('user_balance_mx/list', 'user_balance_mx/list');// 用户明细
    Route::rule('user_bank/list', 'user_bank/list');//用户提现账号列表
    Route::rule('user_bank/edit_detail', 'user_bank/edit_detail');//用户提现账号详情
    Route::rule('user_bank/edit', 'user_bank/edit');//用户提现账号修改
    Route::rule('user_bank/delete', 'user_bank/delete');//删除用户提现账号

    Route::rule('user_agent/list', 'user_agent/list');//代理列表
    Route::rule('user_agent/add', 'user_agent/add');//代理列表修改接口
    Route::rule('user_agent/edit', 'user_agent/edit');//代理列表修改接口
    Route::rule('user_agent/edit_detail', 'user_agent/edit_detail');//代理列表修改详情接口

    // 出入金管理
    Route::rule('user_offline/list', 'user_offline/list');// 用户充值列表
    Route::rule('user_offline/get_count', 'user_offline/get_count');// 用户充值统计
    Route::rule('user_offline/pay_channel', 'user_offline/pay_channel');// 充值渠道列表
    Route::rule('user_offline/edit_detail', 'user_offline/edit_detail');// 用户充值详情
    Route::rule('user_withdrawal/list', 'user_withdrawal/get_user_data');// 会员提现列表
    Route::rule('user_withdrawal/get_count', 'user_withdrawal/get_count');// 会员提现统计
    Route::rule('user_offline/leaderboard', 'user_offline/leaderboard');//充值排行榜
    Route::rule('user_withdrawal/leaderboard', 'user_withdrawal/leaderboard');//提现排行榜

    Route::rule('game_bets/lists', 'game_bets/lists');//游戏记录
    Route::rule('game/report', 'game/report');//游戏报表
    Route::rule('game/user_report', 'game/user_report');//用户日报表
    Route::rule('agent_report/index', 'agent_report/index');//微商报表
    Route::rule('agent_report/user', 'agent_report/user');//微商下级用户报表
    Route::rule('agent_report/userzhudan', 'agent_report/userzhudan');
    Route::rule('agent_report/usergiving', 'agent_report/usergiving');

    //报表管理
    Route::rule('index/getMatch', 'index/getMatch');
    Route::rule('index/overtime_match', 'index/overtime_match');
    Route::rule('index/cancelgame', 'index/cancelgame');
    Route::rule('index/index', 'index/index');
    Route::rule('index/bind_google', 'index/bind_google');
    Route::rule('index/work', 'index/work');
    Route::rule('index/set', 'index/set');
    Route::rule('index/getData', 'index/getData');
    Route::rule('report/user', 'report/user');//用户输赢
    Route::rule('report/getData', 'report/getData');
    Route::rule('report/queryData', 'report/queryData');
    Route::rule('report/clear_cache', 'report/clear_cache');
    Route::rule('report/clear_cache1', 'report/clear_cache1');
    Route::rule('report/get_line', 'report/get_line');
    Route::rule('report/get_bar', 'report/get_bar');
    Route::rule('report/iprank', 'report/iprank');
    Route::rule('remain/index', 'remain/index');//留存率
    Route::rule('remain/user', 'remain/user');
    Route::rule('remain/update', 'remain/createData'); //生成

    //运营报表
    Route::rule('reportAgent/getData', 'reportAgent/getData');
    Route::rule('reportAgent/clear_cache', 'reportAgent/clear_cache');//生成

    Route::rule('game_agent/report', 'AgentReport/game_report'); //代理返佣
    
    Route::rule('upload/upload_img', 'upload/upload_img'); //上传
    Route::rule('upload/upload_file', 'upload/upload_file'); //上传

    //站点配置
    Route::rule('system/edit', 'system/edit'); //编辑
    Route::rule('language/items', 'language/items');//语言类目
    Route::rule('language/translation', 'language/translation');//动态修改
    Route::rule('language/excel', 'language/excel');
    Route::rule('language/import', 'language/import');
    Route::rule('language/update', 'language/update');


})->middleware([
    // 跨域请求支持
    \think\middleware\AllowCrossDomain::class,
    // Auth
    \app\promote\middleware\Auth::class,
]);