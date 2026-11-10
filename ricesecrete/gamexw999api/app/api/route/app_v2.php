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

//【V2--T38】
Route::group('v2/', function () {
    //首页游戏排行榜
    Route::rule('game/index', 'Game/index');
    Route::rule('game/index_game_list', 'Game/index_game_list');
    //游戏全部列表
    Route::rule('game/game_list', 'Game/game_list');
    //游戏首页分类列表
    Route::rule('game/game_type', 'Game/game_type');
    //PWA 应用
    Route::rule('game/pwaapp', 'Game/pwaapp');
    //系统配置
    Route::rule('system/info', 'System/info');

    //常见问题列表
    Route::rule('news/lists', 'News/lists');
    //常见问题详情
    Route::rule('news/detail', 'News/detail');

    //首页规则列表
    Route::rule('rule_index/lists', 'RuleIndex/lists');
    //首页规则详情
    Route::rule('rule_index/detail', 'RuleIndex/detail');

})->prefix('v2.')->middleware([
    // 跨域请求支持
    'AllowCrossDomain'
]);
Route::group('v2/', function () {
    //首页菜单+规则说明列表
    Route::rule('index/menu_list', 'Index/menu_list');
})->prefix('v2.')->middleware([
    // 跨域请求支持
    'AllowCrossDomain', "ApiCheckLogin"
],  // 可选登录参数
    ['is_optional_login' => 1]
);

Route::group('v2/', function () {
    //控制板接口
    Route::rule('affiliate/summary', 'Affiliate/summary');
    //报告统计接口
    Route::rule('affiliate/report', 'Affiliate/report');
    //常问问题接口
    Route::rule('affiliate/faq', 'Affiliate/faq');
    //教程接口
    Route::rule('affiliate/course', 'Affiliate/course');
    //现金返还详情接口
    Route::rule('action/jyjdetail', 'Action/jyjdetail');
    //现金返还领取接口
    Route::rule('action/apply', 'Action/apply');
    //充值配置数据
    Route::rule('user_offline/recharge_config', 'UserOffline/recharge_config');
    //充值申请
    Route::rule('user_offline/apply', 'UserOffline/apply');

    //提现渠道
    Route::rule('user_withdrawal/withdrawal_channel', 'UserWithdrawal/withdrawal_channel');
    //用户提现配置数据
    Route::rule('user_withdrawal/withdrawal_config', 'UserWithdrawal/withdrawal_config');
    //用户提现申请
    Route::rule('user_withdrawal/apply', 'UserWithdrawal/apply');


    //WG团队中心
    #我的推荐
    Route::rule('team/recommend', 'agent/recommend');
    #我的佣金
    Route::rule('team/commission', 'agent/commission');
    #佣金详情
    Route::rule('team/commissionDetail', 'agent/commissionDetail');
    #我的收入
    Route::rule('team/income', 'agent/income');
    #所有数据
    Route::rule('team/statistics', 'agent/statistics');
    #直接投注
    Route::rule('team/childBet', 'agent/childBet');
    #直属会员
    Route::rule('team/childMember', 'agent/childMember');
    #直属奖金
    Route::rule('team/childBonus', 'agent/childBonus');
    #返佣比例
    Route::rule('team/commissionRate', 'agent/commissionRate');
    #直属开户
    Route::rule('team/childAccount', 'agent/childAccount');


})->prefix('v2.')->middleware([
    // 跨域请求支持
    'AllowCrossDomain',
    // 验证用户登录情况
    'ApiCheckLogin'
]);

