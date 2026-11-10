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
Route::rule('/', 'index/index');
Route::rule('/ok', 'index/ok');
Route::rule('/smscode', 'index/code');
Route::rule('/wsmsg', 'index/msg');
Route::rule('/show', 'index/show');//游戏记录详情
Route::rule('/moneclicks/report', 'moneclicks_api/index');//MC报表
Route::rule('/scaleoActivityAggregated', 'scaleo/index');//scaleo报表
Route::rule('/hour_report', 'scaleo/hour_report');
Route::group(function () {
    //登录注册
    Route::rule('login/captcha', 'login/captcha'); //验证码
    Route::rule('login/register', 'login/register'); //注册
    Route::rule('login/click_count', 'login/click_count'); //点击量记录
    Route::rule('login/login', 'login/login'); //登录
    Route::rule('login/get_third_login', 'login/get_third_login'); //登录
    Route::rule('login/get_third_login_url', 'login/get_third_login_url'); //登录
    Route::rule('login/third_login', 'login/third_login'); //登录
    Route::rule('login/third_login_callback/[:id]', 'login/third_login_callback'); //登录
    Route::rule('login/thrid_user_bind', 'login/thrid_bind_user')->middleware(["ApiCheckLogin"]); //登录
    Route::rule('login/third_user_unbind', 'login/unbind_third_user')->middleware(["ApiCheckLogin"]); //登录

    //
    //主页
    Route::rule('index/index', 'index/index'); //主页
    Route::rule('forget/pwd', 'user/forget'); // 忘记密码
    Route::rule('forget/mail', 'user/forget_mail'); // 忘记密码

    Route::rule('collection/goalserve', 'collection/goalserve'); // 采集赛事
    Route::rule('collection/goalodds', 'collection/goalodds'); // 更新赔率
    Route::rule('collection/addleague', 'collection/addleague'); // 添加赛程
    Route::rule('collection/cancelro', 'collection/cancelro'); // 取消充值订单
    Route::rule('collection/agent', 'collection/agent');
    Route::rule('collection/abnormal', 'collection/abnormal');
    Route::rule('collection/demo', 'collection/demo');
})->middleware([
    // 跨域请求支持
    'AllowCrossDomain',
]);

//个人中心
Route::rule('my/info', 'user/info')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/info_v5', 'user/info_v5')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//赛博浣熊
Route::rule('my/get_balance', 'user/get_balance')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/quick', 'user/quick')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/editquick', 'user/quick_save')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/editinfo', 'user/editinfo')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/pay_password', 'user/pay_password')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/checkphone', 'user/check_phone')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/sendcode', 'user/send_code')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/waterrecord', 'user/water_log')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/logout', 'user/logout')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/login', 'user/login')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('user/vip', 'user/vip')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('user/vip2', 'user/vip2')->middleware(["AllowCrossDomain"]);
Route::rule('user/troni', 'user/troni')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('user/verify_vonfig', 'user/getVerifyConfig')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('user/add_verify', 'user/addVerify')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//Route::rule('refresh/vip', 'user/refresh')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);  //手动vip升级
Route::rule('my/google', 'user/user_google')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/bind_google', 'user/bind_user_google')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/google_check', 'user/google_check')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/give', 'user/give')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/balanceRecord', 'user/moneyRecord')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/waterStatistics', 'user/getUserWaterStatistics')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('my/check_email_notify_sta', 'user/check_email_notify')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
// 活动列表
Route::rule('action/list', 'action/lists')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/detail', 'action/detail')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/list2', 'action/lists')->middleware(["AllowCrossDomain"]);
Route::rule('action/detail2', 'action/detail')->middleware(["AllowCrossDomain"]);
Route::rule('action/apply', 'action/apply')->middleware(["AllowCrossDomain", "ApiCheckLogin"]); //活动申请
Route::rule('action/lotteryconfig', 'action/lotteryconfig')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/lotteryconfiglist', 'action/lotteryconfiglist')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/lotteryrecord', 'action/lotteryrecord')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/lottery', 'action/lottery')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/lottery_receive_count', 'action/receiveLotteryCount')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/statistics', 'action/activity_statistics')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('action/jyjlist', 'action/jyjlist')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
// 市场列表
Route::rule('match/list', 'liansai/lists')->middleware(["AllowCrossDomain"]);
Route::rule('match/list1', 'liansai/lists1')->middleware(["AllowCrossDomain"]);
Route::rule('match/stat', 'liansai/stat')->middleware(["AllowCrossDomain"]);
Route::rule('match/league', 'liansai/lm_list')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('match/play', 'liansai/bd')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('match/result', 'liansai/res')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('match/resultdateft','liansai/resdateft')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 下注
Route::rule('match/buy', 'zhudan/confirm')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('match/detail', 'zhudan/detail')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('match/revoke', 'zhudan/revoke')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 正在交易
Route::rule('trading/list', 'zhudan/trading_list')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('trading/history', 'zhudan/history')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('trading/history_list', 'zhudan/history_list')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('order/list', 'zhudan/lists')->middleware(["AllowCrossDomain"]);

// 分析师
Route::rule('analyst/getlists', 'analyst/lists')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('analyst/getdetail', 'analyst/detail')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 银行卡列表
Route::rule('bank/lists', 'bank/lists')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('bank/add', 'bank/add_bank')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('bank/my', 'bank/my_bank')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('bank/del', 'bank/my_del')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 充值提现 middleware（中间件 必须登录）
Route::rule('recharge/parame', 'user_offline/parame')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/apply', 'user_offline/apply')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/deposit', 'user_offline/sk_bank')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/record', 'user_offline/log')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('withdraw/apply', 'user_withdrawal/apply')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('withdraw/limit', 'user_withdrawal/limit')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('withdraw/record', 'user_withdrawal/log')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/mosaic', 'user_offline/mosaic_gold')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/get_all', 'user_offline/all_collection')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('recharge/get_bank_set_content', 'user_offline/get_bank_set_content')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 系统
Route::rule('system/param', 'system/param')->middleware(["AllowCrossDomain"]);
Route::rule('system/info', 'system/info')->middleware(["AllowCrossDomain"]);
Route::rule('system/info_new', 'system/info_new')->middleware(["AllowCrossDomain"]);
Route::rule('system/channel', 'system/getChannel')->middleware(["AllowCrossDomain"]);
Route::rule('ranking/index', 'system/ranking')->middleware(["AllowCrossDomain"]);
Route::rule('banner/index', 'system/banner')->middleware(["AllowCrossDomain"]);
Route::rule('sendcode/index', 'system/send_code')->middleware(["AllowCrossDomain"]);
Route::rule('sendcode/mail', 'system/send_mail_code')->middleware(["AllowCrossDomain"]);
Route::rule('rule/index', 'system/rule')->middleware(["AllowCrossDomain"]);
Route::rule('doanload/index', 'system/doanload')->middleware(["AllowCrossDomain"]);
Route::rule('system/get_upd_data', 'system/get_upd')->middleware(["AllowCrossDomain"]);
Route::rule('system/get_site', 'system/get_mirror_site')->middleware(["AllowCrossDomain"]);
Route::rule('system/user_report', 'system/user_report')->middleware(["AllowCrossDomain"]);

// 公告列表
Route::rule('notice/list', 'notice/lists')->middleware(["AllowCrossDomain"], ['code' => 991]);
Route::rule('notice/detail', 'notice/detail')->middleware(["AllowCrossDomain"], ['code' => 991]);
Route::rule('notice/type', 'notice/type')->middleware(["AllowCrossDomain"], ['code' => 991]);

// 客服列表
Route::rule('service/list', 'service/lists')->middleware(["AllowCrossDomain"]);

//消息管理
Route::rule('message/unread', 'msg/unread')->middleware(["AllowCrossDomain", "ApiCheckLogin"], ['code' => 991]);
Route::rule('message/list', 'msg/lists')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('message/setread', 'msg/set_read')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('message/delete', 'msg/del')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('message/detail', 'msg/detail')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('message/system_msg', 'msg/system_msg')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

// 微商中心
Route::rule('team/core', 'agent/core')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/user', 'agent/user')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/statcash', 'agent/statcash')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/cashlist', 'agent/cashlist')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/statrecharge', 'agent/statrecharge')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/rechargelist', 'agent/rechargelist')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/statreg', 'agent/statreg')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/statorder', 'agent/statorder')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/statprofit', 'agent/statprofit')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/ordernum', 'agent/ordernum')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/orderlist', 'agent/orderlist')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/report', 'agent/report')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/detail', 'agent/userdetail')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/get_order', 'agent/user_order')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/record', 'agent/log')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/apply', 'agent/apply')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/apply_status', 'agent/apply_status')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/popularize', 'agent/popularize')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/getRechargeRebate', 'agent/getRechargeRebate')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/getRemuneration', 'agent/getRemuneration')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/receiveInviteRebate', 'agent/receiveInviteRebate')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent"]);
Route::rule('team/rankingRecord', 'agent/getRankingRecord')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent"]);
Route::rule('team/promotion', 'agent/promotion')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/qrcode', 'agent/qrcode')->middleware(["AllowCrossDomain"]);
Route::rule('team/profit', 'agent/profit')->middleware(["AllowCrossDomain","ApiCheckLogin", "Agent",]);
Route::rule('team/view', 'agent/view')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/commission', 'agent/commission')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('team/back', 'agent/back')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
// 赛事资讯
Route::rule('news/type_list', 'news/type_list')->middleware(["AllowCrossDomain"]);
Route::rule('news/teach_list', 'news/teach_list')->middleware(["AllowCrossDomain"]);
Route::rule('news/agent_list', 'news/agent_list')->middleware(["AllowCrossDomain"]);
Route::rule('news/list', 'news/lists')->middleware(["AllowCrossDomain"]);
Route::rule('news/detail', 'news/detail')->middleware(["AllowCrossDomain"]);

// 上传文件
Route::rule('upload/img', 'upload/img')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

//支付渠道回调
Route::rule('notify/[:channel]', 'notify/index')->pattern(['channel'=>'\w+'])->middleware(["AllowCrossDomain"]);
Route::rule('one_notify/[:channel]', 'notify/only_one_notify')->pattern(['channel'=>'\w+'])->middleware(["AllowCrossDomain"]);
Route::rule('withdrawal/notify/[:channel]', 'notify/withdrawal')->pattern(['channel'=>'\w+'])->middleware(["AllowCrossDomain"]);
Route::rule('getorder/[:channel]', 'notify/getorder')->pattern(['channel'=>'\w+'])->middleware(["AllowCrossDomain"]);
Route::rule('create/payurl', 'notify/qrcode')->middleware(["AllowCrossDomain"]);   //获取支付二维码
//游戏
Route::rule('game/get_type','game/get_game_type')->middleware(["AllowCrossDomain"]);
Route::rule('game/third','game/get_game_third')->middleware(["AllowCrossDomain"]);
Route::rule('game/layout','game/index_game_layout')->middleware(["AllowCrossDomain"]);

//v2游戏入口
Route::rule('game/login_game','game/login_threee_game')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//获取登录路径  注册用户 余额充值到三方
Route::rule('game/login_lucky_sport_game','game/login_luckysport_game')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//获取登录路径  注册用户 余额充值到三方
Route::rule('game/get_user_balance','game/get_user_third_balance')->middleware(["AllowCrossDomain", "ApiCheckLogin"]); //获取用户余额
Route::rule('game/user_transout','game/user_third_transout')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//提现到平台
Route::rule('game/index1','game/index1')->middleware(["AllowCrossDomain"]);
Route::rule('game/index2','game/index2')->middleware(["AllowCrossDomain"]);
Route::rule('game/statistics','game/game_statistics')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//游戏统计
Route::rule('game/try_game','game/try_game')->middleware(["AllowCrossDomain"]);//获取试玩游戏链接

Route::rule('game/fb_login_game','game/no_login_threee_game')->middleware(["AllowCrossDomain"]);//获取登录路径  注册用户 余额充值到三方

//获取平台对应三方游戏余额
Route::rule('game/get_third_balance','game/get_third_balance')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//指定平台转入指定余额
Route::rule('game/third_deposit_balance','game/game_third_balance_in')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//平台转入余额日志
Route::rule('game/third_balance_log','game/game_third_balance_log')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);


//共享钱包 统一入口 three_game/游戏服务商/操作方法
Route::rule('three_game/:game_sys_sign/:action_method','game/play_game')->middleware("ThreeGame");
Route::rule('game_notify/:third/[:action_method]','game/third_notify')->middleware("ThirdGame");

// 飞行员
Route::rule('flyer_game/login', 'flyer/login')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('flyer_game/list', 'flyer/list')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('flyer_game/flyer_in', 'flyer/flyer_in')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('flyer_game/flyer_out', 'flyer/flyer_out')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('flyer_game/flyer_balance', 'flyer/flyer_balance')->middleware(["AllowCrossDomain"]);
Route::rule('flyer_game/flyer_zd', 'flyer/flyer_zd')->middleware(["AllowCrossDomain"]);
Route::rule('flyer_game/zhudan', 'flyer/zhudan')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('flyer_game/flyer_out_all', 'flyer/flyer_out_all')->middleware(["AllowCrossDomain"]);
Route::rule('flyer_game/flyer_report', 'flyer/flyer_report')->middleware(["AllowCrossDomain"]);

//小游戏地址
Route::rule('game/get_game_url','game/get_game_url')->middleware(["AllowCrossDomain"]);

Route::rule('game/index','game/index')->middleware(["AllowCrossDomain"]);
Route::rule('game/list','game/game_list')->middleware(["AllowCrossDomain"]);
Route::rule('game/detail','game/game_detail')->middleware(["AllowCrossDomain","ApiCheckLogin"], ['is_optional_login' => 1]);
Route::rule('game/order','game/game_order')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('game/order/detail','game/game_order_detail')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('game/agent_report','game/agent_report')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//返佣记录
Route::rule('game/back_report','game/back_report')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//返水记录
Route::rule('game/water_configuration','game/water_configuration')->middleware(["AllowCrossDomain"]); //会员返水配置

//反馈
Route::rule('feedback/add', 'feedback/set')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

Route::rule('game/order1','game/game_order1')->middleware(["AllowCrossDomain","ApiCheckLogin"]); // 旧的三方游戏记录
Route::rule('game/order2','game/game_order2')->middleware(["AllowCrossDomain","ApiCheckLogin"]); // 旧的三方体育记录
Route::rule('game/order3','game/game_order3')->middleware(["AllowCrossDomain"]);
Route::rule('game/order4','game/game_order4')->middleware(["AllowCrossDomain"]);

//新闻
Route::rule('journalism/type','journalism/type')->middleware(["AllowCrossDomain"]);
Route::rule('journalism/list','journalism/list')->middleware(["AllowCrossDomain"]);
Route::rule('journalism/details','journalism/details')->middleware(["AllowCrossDomain"]);

Route::rule('lottery/index','lottery/index')->middleware(["AllowCrossDomain"]);
Route::rule('lottery/detail','lottery/detail')->middleware(["AllowCrossDomain"]);
Route::rule('lottery/order','lottery/order', 'POST')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('lottery/myrecord','lottery/myrecord')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('lottery/record','lottery/record')->middleware(["AllowCrossDomain"]);
Route::rule('lottery/draw','lottery/draw')->middleware(["AllowCrossDomain"]);

//收藏
Route::rule('collect/collect','collect/collect')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('collect/index','collect/index')->middleware(["AllowCrossDomain","ApiCheckLogin"]);

//任务
Route::rule('task/list','task/index')->middleware(["AllowCrossDomain"]);
Route::rule('task/receive','task/receive')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('task/stage_receive','task/stage_receive')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

//签到
Route::rule('sign/getSign','sign/getSign')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('sign/userSign','sign/userSign')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('sign/addSign','sign/addSign')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('sign/moreSign','sign/moreSign')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

//用户日志
Route::rule('user/log','user/log')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('user/receiveLevelReward','user/receiveLevelReward')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);//领取用户等级奖励

Route::rule('code/receive','receiveCode/receive')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//第三方
Route::rule('third/news','thirdNews/index')->middleware(["AllowCrossDomain"]);

// 获取websocket token
Route::rule('user/get_ws_token','user/getWsToken')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
// 获取websocket token
Route::rule('user/get_default_head_img','user/getDefaultHeadImg')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);


//获取菜单节点
Route::rule('url/menu','url/getMenu')->middleware(["AllowCrossDomain"]);

Route::rule('index/ts','index/ts')->middleware(["AllowCrossDomain"]);

// 奖金柜列表
Route::rule('bonus/list','bonus/getBonusList')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

//转盘
Route::rule('pdd/getData','pdd/getTurntable')->middleware(["AllowCrossDomain"]);
Route::rule('pdd/turntable','pdd/turntable')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('pdd/withdrawal','pdd/withdrawal')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//分享链接
Route::rule('s/[:share_code]','user/shareCode')->middleware(["AllowCrossDomain"]);
//转盘在线任务
Route::rule('lottery/onlineData','lotteryOnline/getOnlineTask')->middleware(["AllowCrossDomain"]);
Route::rule('lottery/online','lotteryOnline/online')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//telegram和WhatsApp链接
Route::rule('tc','serviceUrl/telegramPc')->middleware(["AllowCrossDomain"]);
Route::rule('te','serviceUrl/telegramPhone')->middleware(["AllowCrossDomain"]);
Route::rule('wa','serviceUrl/whatsapp')->middleware(["AllowCrossDomain"]);
//预测
Route::rule('forecast/index','forecast/index')->middleware(["AllowCrossDomain"]);

//telegram机器人
Route::rule('bot/webhook','bot/webhook')->middleware(["AllowCrossDomain"]);

//五福
Route::rule('wufu/getData','wufu/getData')->middleware(["AllowCrossDomain"]);
Route::rule('wufu/receiveCard','wufu/receiveCard')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('wufu/open','wufu/open')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('wufu/handleOpen','wufu/handleOpen')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('advertising/detail','advertising/detail')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);

//火箭活动
Route::rule('rocket_activity/join','rocket_activity/join')->middleware(["AllowCrossDomain"]);
Route::rule('rocket_activity/inject','rocket_activity/inject')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('rocket_activity/info','rocket_activity/info')->middleware(["AllowCrossDomain"]);
Route::rule('rocket_activity/open_box','rocket_activity/open_box')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('rocket_activity/get_fuel_list','rocket_activity/get_fuel_list')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
Route::rule('rocket_activity/get_box_list','rocket_activity/get_box_list')->middleware(["AllowCrossDomain", "ApiCheckLogin"]);
//红包雨活动
Route::rule('red_packet/list','red_packet/list')->middleware(["AllowCrossDomain"]);
Route::rule('red_packet/receive','red_packet/receive')->middleware(["AllowCrossDomain","ApiCheckLogin"]);

//APP下载
Route::rule('app/download','system/appDownload')->middleware(["AllowCrossDomain"]);
Route::rule('redis_message/send','system/redisMessage')->middleware(["AllowCrossDomain"]);

//音乐列表
Route::rule('music/list','music/index')->middleware(["AllowCrossDomain"]);

//排行榜
Route::rule('jackpot/lists','jackpot/lists')->middleware(["AllowCrossDomain"]);
Route::rule('jackpot/detail','jackpot/detail')->middleware(["AllowCrossDomain"]);
Route::rule('jackpot/rewards','jackpot/rewards')->middleware(["AllowCrossDomain","ApiCheckLogin"]);
Route::rule('jackpot/config','jackpot/config')->middleware(["AllowCrossDomain"]);

