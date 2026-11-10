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
Route::group(function () {
    //登录注册
    Route::rule('login/captcha', 'login/captcha'); //验证码
    Route::rule('login/register', 'login/register'); //注册
    Route::rule('login/login', 'login/login'); //登录

    Route::rule('upload/upload_img', 'upload/upload_img'); //上传
    Route::rule('upload/upload_file', 'upload/upload_file'); //上传

    Route::rule('collection/goalserve', 'collection/goalserve'); // 采集赛事
    Route::rule('collection/goalodds', 'collection/goalodds'); // 更新赔率
    Route::rule('collection/addleague', 'collection/addleague'); // 添加赛程
    Route::rule('collection/cancelro', 'collection/cancelro'); // 取消充值订单
    Route::rule('collection/agent', 'collection/agent');
    Route::rule('collection/abnormal', 'collection/abnormal');
    Route::rule('collection/demo', 'collection/demo');

    //会员管理
    Route::rule('user/list', 'user/list');//用户列表
    Route::rule('user/reports', 'user/reports');//用户报表
    Route::rule('user/cs_user', 'user/cs_user');//测试用户列表
    Route::rule('user/excel', 'user/excel');//用户导出
    Route::rule('user/import', 'user/import');//用户导入
    Route::rule('user/add', 'user/add');//添加用户
    Route::rule('user/edit', 'user/edit');//编辑用户
    Route::rule('user/status', 'user/status');//批量修改用户状态
    Route::rule('user/delete', 'user/delete');//删除用户
    Route::rule('user/set_limit', 'user/set_limit');//批量设置下注限额
    Route::rule('user/rec_edit', 'user/rec_edit');//修改用户上级
    Route::rule('user/edit_detail', 'user/edit_detail');//编辑用户页详情
    Route::rule('user/detail', 'user/detail');//用户详情
    Route::rule('user/ipstatistics', 'user/ipstatistics');//IP统计
    Route::rule('user/running_water', 'user/running_water');//流水统计
    Route::rule('user/appointWater', 'user/appointWater');//流水统计
    Route::rule('user/water', 'user/water');//用户流水
    Route::rule('user/remove_water', 'user/remove_water');//批量清零流水
    Route::rule('user/remove_cjwater', 'user/remove_cjwater');//批量清零彩金流水
    Route::rule('user/cs_user_delete', 'user/cs_user_delete');//删除测试用户
    Route::rule('user/line', 'user/line');//在线用户列表
    Route::rule('user/get_top_user', 'user/get_top_user');//在线用户列表
    Route::rule('user/vip_edit', 'user/vip_edit');//修改用户上级
    Route::rule('user/vip_reset', 'user/vip_reset');//还原VIP等级
    Route::rule('user/reset_2fa', 'user/reset_2fa');//重置2FA
    Route::rule('user/reset_with_pass', 'user/reset_with_pass');//重置提现密码
    Route::rule('user/plays_mail', 'user/plays_mail');//重置提现密码
    Route::rule('user/user_address', 'user/user_address');//用户三方收款地址
    Route::rule('user/user_address_del', 'user/user_address_del');//用户三方收款地址

    Route::rule('user_level/list', 'user_level/list');// 用户等级列表
    Route::rule('user_level_record/list', 'user_level_record/list');//等级奖励记录
    Route::rule('user_level/add', 'user_level/add');// 用户等级添加
    Route::rule('user_level/edit', 'user_level/edit');// 用户等级修改
    Route::rule('user_level/edit_detail', 'user_level/edit_detail');// 用户等级详情
    Route::rule('user_level/delete', 'user_level/delete');// 用户等级删除

    Route::rule('user_level_down/list', 'user_level_down/list');// 用户保级列表
    Route::rule('user_level_down_record/list', 'user_level_down_record/list');//等级降级记录
    Route::rule('user_level_down/add', 'user_level_down/add');// 用户保级添加
    Route::rule('user_level_down/edit', 'user_level_down/edit');// 用户保级修改
    Route::rule('user_level_down/edit_detail', 'user_level_down/edit_detail');// 用户保级详情
    Route::rule('user_level_down/delete', 'user_level_down/delete');// 用户保级删除
    
    Route::rule('user_agent/list', 'user_agent/list');//代理列表
    Route::rule('user_agent/edit', 'user_agent/edit');//代理列表修改接口
    Route::rule('user_agent/edit_detail', 'user_agent/edit_detail');//代理列表修改详情接口
    Route::rule('user_bank/list', 'user_bank/list');//用户提现账号列表
    Route::rule('user_bank/edit_detail', 'user_bank/edit_detail');//用户提现账号详情
    Route::rule('user_bank/edit', 'user_bank/edit');//用户提现账号修改
    Route::rule('user_bank/delete', 'user_bank/delete');//删除用户提现账号
    Route::rule('user_login_log/list', 'user_login_log/list');//用户登录日志列表
    Route::rule('user_login_log/excel', 'user_login_log/excel');//用户登录日志导出
    Route::rule('user/online', 'user/online');//用户在线列表

    // 出入金管理
    Route::rule('user_offline/list', 'user_offline/list');// 用户充值列表
    Route::rule('user_offline/verify', 'user_offline/verify');// 
    Route::rule('user_offline/edit_detail', 'user_offline/edit_detail');// 用户充值详情
    Route::rule('user_offline/add', 'user_offline/add');// 用户充值手动入单
    Route::rule('user_offline/edit', 'user_offline/edit');// 用户充值修改
    Route::rule('user_offline/success', 'user_offline/success2');// 用户充值修改
    Route::rule('user_offline/get_count', 'user_offline/get_count');// 用户充值统计
    Route::rule('user_offline/excel', 'user_offline/excel');// 用户充值导出
    Route::rule('user_offline/pay_channel', 'user_offline/pay_channel');// 充值渠道列表
    Route::rule('user_offline/delete', 'user_offline/delete');// 充值记录删除
    Route::rule('user_offline/set_remarks', 'user_offline/set_remarks');//设置备注
    Route::rule('user_withdrawal/list', 'user_withdrawal/get_user_data');// 会员提现列表
    Route::rule('user_withdrawal/get_count', 'user_withdrawal/get_count');// 会员提现统计
    Route::rule('user_withdrawal/verify', 'user_withdrawal/verify');// 
    Route::rule('user_withdrawal/add', 'user_withdrawal/add');// 会员提现手动入单
    Route::rule('user_withdrawal/edit_detail', 'user_withdrawal/edit_detail');// 会员提现详情
    Route::rule('user_withdrawal/edit', 'user_withdrawal/edit');// 会员提现修改
    Route::rule('user_withdrawal/delete', 'user_withdrawal/delete');// 会员提现删除
    Route::rule('user_withdrawal/excel', 'user_withdrawal/excel');// 会员提现导出
    Route::rule('user_withdrawal/set_remarks', 'user_withdrawal/set_remarks');// 设置订单备注
    Route::rule('user_withdrawal/lock', 'user_withdrawal/lockOrUnlock');// 订单锁定或解锁
    Route::rule('user_balance_mx/list', 'user_balance_mx/list');// 用户明细
    Route::rule('user_cj_balance_mx/list', 'user_cj_balance_mx/list');// 彩金明细
    Route::rule('artificial_balance/list', 'artificial_balance/list');// 人工调整
    Route::rule('artificial_balance/add', 'artificial_balance/add');// 人工调整添加
    Route::rule('artificial_balance/excel', 'artificial_balance/excel');// 人工调整导出
    Route::rule('artificial_balance/import', 'artificial_balance/import');// 人工调整导入
    Route::rule('artificial_balance/set_water', 'artificial_balance/set_water');// 人工调整设置流水倍数
    Route::rule('artificial_balance/import', 'artificial_balance/import');// 人工调整导入

    //游戏管理
    Route::rule('liansai/list', 'liansai/list');//联赛管理
    Route::rule('liansai/add', 'liansai/add');//联赛管理添加
    Route::rule('liansai/edit_detail', 'liansai/edit_detail');//联赛管理详情
    Route::rule('liansai/edit', 'liansai/edit');//联赛管理修改
    Route::rule('liansai/delete', 'liansai/delete');//联赛管理删除
    Route::rule('liansai_sc/list', 'liansai_sc/list');//赛程管理
    Route::rule('liansai_sc/add', 'liansai_sc/add');//赛程管理添加
    Route::rule('liansai_sc/edit_detail', 'liansai_sc/edit_detail');//赛程管理详情
    Route::rule('liansai_sc/edit', 'liansai_sc/edit');//赛程管理修改
    Route::rule('liansai_sc/bd_detail', 'liansai_sc/bd_detail');//赛程管理波胆详情
    Route::rule('liansai_sc/bd_set', 'liansai_sc/bd_set');//赛程管理波胆修改
    Route::rule('liansai_sc/export', 'liansai_sc/export');//赛程管理导出
    Route::rule('liansai_sc/bd_update', 'liansai_sc/bd_update');//赛程管理更新波胆
    Route::rule('liansai_zhudan/get_order_data', 'liansai_zhudan/get_order_data');//注单管理
    Route::rule('liansai_zhudan/status_edit', 'liansai_zhudan/status_edit');//注单管理修改状态
    Route::rule('liansai_zhudan/excel', 'liansai_zhudan/excel');//注单管理导出
    Route::rule('liansai_zhudan/edit_detail', 'liansai_zhudan/edit_detail');//注单管理详情
    Route::rule('liansai_zhudan/edit', 'liansai_zhudan/edit');//注单管理修改
    Route::rule('liansai_sc_res/lists', 'liansai_sc_res/lists');//赛果管理
    Route::rule('liansai_sc_res/jiesuan', 'liansai_sc_res/jiesuan');
    Route::rule('liansai_sc_res/indemnity', 'liansai_sc_res/indemnity');
    Route::rule('liansai_sc_res/settlement', 'liansai_sc_res/settlement');
    Route::rule('liansai_sc_res/order_back', 'liansai_sc_res/order_back');
    Route::rule('liansai_team/list', 'liansai_team/list');
    Route::rule('liansai_team/add', 'liansai_team/add');
    Route::rule('liansai_team/edit', 'liansai_team/edit');
    Route::rule('liansai_team/status_edit', 'liansai_team/status_edit');

    //第三方游戏
    Route::rule('game_set/type', 'game_set/type');//游戏分类
    Route::rule('game_set/list', 'game_set/list');
    Route::rule('game_set/add', 'game_set/add');
    Route::rule('game_set/edit', 'game_set/edit');
    Route::rule('game_set/type_add', 'game_set/type_add');
    Route::rule('game_set/type_edit', 'game_set/type_edit');
    Route::rule('game_set/type_delete', 'game_set/type_delete');
    Route::rule('game_set/backconfig_list', 'game_set/backconfig_list');
    Route::rule('game_set/backconfig_add', 'game_set/backconfig_add');
    Route::rule('game_set/backconfig_delete', 'game_set/backconfig_delete');
    Route::rule('game_set/backconfig_edit', 'game_set/backconfig_edit');
    Route::rule('game_set/get_game_water_rates', 'game_set/get_game_water_rates');
    Route::rule('game_set/set_game_water_rate', 'game_set/set_game_water_rate');
    Route::rule('game_set/game_water_rates_list', 'game_set/game_water_rates_list');
    Route::rule('game_set/game_water_rates_edit', 'game_set/game_water_rates_edit');

    //免费游戏
    Route::rule('freebet/lists', 'GiveFreeGameBet/lists');
    Route::rule('freebet/template', 'GiveFreeGameBet/getFreeBetFormTemplate');
    Route::rule('freebet/add', 'GiveFreeGameBet/give');

    //中将手续费
    Route::rule('gameBetFee/lists', 'GameBets/fee_list');



    Route::group(function () {
        Route::rule('game/third', 'game/third');//游戏平台
        Route::rule('game/lists', 'game/lists');//游戏管理
        Route::rule('game/add', 'game/add');//游戏管理添加
        Route::rule('game/edit', 'game/edit');//游戏管理修改
        Route::rule('game/delete', 'game/delete');

        Route::rule('game/third_web', 'game/third_web');//前端游戏平台
        Route::rule('game/third_add_web', 'game/third_add_web');//前端游戏平台添加
        Route::rule('game/third_edit_web', 'game/third_edit_web');;//前端游戏平台添加

        Route::rule('game/batch_status', 'game/batch_status');
        Route::rule('game/batch_select_status', 'game/batch_select_status');
        Route::rule('game/third_add', 'game/third_add');
        Route::rule('game/third_edit', 'game/third_edit');
        Route::rule('game/third_export', 'game/third_export');
        Route::rule('game/third_import', 'game/third_import');
        Route::rule('game/refused', 'game/refused');
        Route::rule('game/send', 'game/send');
        Route::rule('game/all_send', 'game/all_send');#一键派发
        Route::rule('game/all_refused', 'game/all_refused');#一键拒绝
        Route::rule('game/clear', 'game/clear');
        Route::rule('game/export', 'game/export');
        Route::rule('game/import', 'game/import');
        Route::rule('game/apis', 'game/apis');//游戏平台API
        Route::rule('game/report', 'game/report');//游戏报表
        Route::rule('game/switch_game', 'game/switch_game');//选中游戏批量操作状态

        Route::rule('game/back', 'game/back');//返水管理
        Route::rule('game/user_report', 'game/user_report');//用户日报表
        Route::rule('game/user_stat', 'game/user_stat');//用户数据总览
        Route::rule('game/game_stat', 'game/game_stat');//游戏数据统计

        Route::rule('game/apis_add', 'game/apis_add');//游戏平台API
        Route::rule('game_bets/lists', 'game_bets/lists');//游戏记录
        Route::rule('game_bets/detail', 'game_bets/detail');//游戏记录详情
        Route::rule('game_bets/user_list', 'game_bets/user_list');//用户游戏记录，投注记录，注单记录

        //三方平台服务费预储值
        Route::rule('thirdcost/upcost', 'third_cost/upcost');//上分
        Route::rule('thirdcost/status', 'third_cost/getstatus');//获取当前可用分值
        Route::rule('thirdcost/list', 'third_cost/getlist');//获取分值变更记录


    })->middleware('ManageCheckLogin');
    Route::rule('game_agent/set', 'game_agent/set');//返佣设置
    Route::rule('game_agent/detail', 'game_agent/detail');
    Route::rule('game_agent/detail_out', 'game_agent/detail_out');
    Route::rule('game_agent/detail_subout', 'game_agent/detail_subout');
    Route::rule('game_agent/detail_subout2', 'game_agent/detail_subout2');
    Route::rule('game_agent/refused', 'game_agent/refused');
    Route::rule('game_agent/send', 'game_agent/send');
    Route::rule('game_agent/batch', 'game_agent/batch');
    Route::rule('game_agent/set_add', 'game_agent/set_add');
    Route::rule('game_agent/set_edit', 'game_agent/set_edit');
    Route::rule('game_agent/report', 'game_agent/report');//代理返佣
    Route::rule('game_report/index', 'game_report/index');
    Route::rule('game_report/get_game_report', 'game_report/get_game_report');

    //活动管理
    Route::rule('action/list', 'action/list');//优惠管理
    Route::rule('action/add', 'action/add');
    Route::rule('action/edit', 'action/edit');
    Route::rule('action/delete', 'action/delete');
    Route::rule('action/lottery_config', 'action/lottery_config');
    Route::rule('action/lottery_prize_delete', 'action/lottery_prize_delete');
    Route::rule('action/lottery_prize_save', 'action/lottery_prize_save');
    Route::rule('action/lottery_fakerecord', 'action/lottery_fakerecord');
    Route::rule('action/integral_record_excel', 'action/integral_record_excel');
    Route::rule('action/integral_save', 'action/integral_save');
    Route::rule('action/lottery_config_find', 'action/lottery_config_find');//抽奖配置
    Route::rule('action/lottery_prize', 'action/lottery_prize');//奖品列表
    Route::rule('action/lottery_record', 'action/lottery_record');//抽奖记录
    Route::rule('lotteryConfig/conditionRecord', 'action/lottery_condition_record');//转盘条件记录
    Route::rule('action/integral_record', 'action/integral_record');//积分记录
    Route::rule('action/lottery_data_record', 'action/lottery_data_record');//抽奖报表
    Route::rule('action_apply/list', 'action_apply/list');//优惠申请
    Route::rule('action_apply/distribute', 'action_apply/distribute');
    Route::rule('action_apply/ps', 'action_apply/ps');
    Route::rule('url/lists', 'url/lists');//链接
    Route::rule('url/add', 'url/add');
    Route::rule('url/edit', 'url/edit');
    Route::rule('url/delete', 'url/delete');
    Route::rule('action_type/lists', 'action_type/lists');//活动类型
    Route::rule('action_type/add', 'action_type/add');
    Route::rule('action_type/edit', 'action_type/edit');
    Route::rule('action_type/delete', 'action_type/delete');
    Route::rule('mirror_site/lists', 'mirror_site/lists');//镜像站点
    Route::rule('mirror_site/add', 'mirror_site/add');
    Route::rule('mirror_site/edit', 'mirror_site/edit');
    Route::rule('mirror_site/delete', 'mirror_site/delete');
    Route::rule('action/import', 'action/import');//优惠活动导入
    Route::rule('action/export', 'action/export');//优惠活动导出

    //任务管理
    Route::rule('task/lists', 'task/lists');//任务列表
    Route::rule('task/add', 'task/add');
    Route::rule('task/edit', 'task/edit');
    Route::rule('task/delete', 'task/delete');
    Route::rule('task_content/lists', 'task_content/lists');//任务内容
    Route::rule('task_content/add', 'task_content/add');
    Route::rule('task_content/edit', 'task_content/edit');
    Route::rule('task_content/delete', 'task_content/delete');
    Route::rule('task_record/lists', 'task_record/lists');#任务领取记录
    Route::rule('task_type/lists', 'task_type/lists');//任务类型
    Route::rule('task_type/add', 'task_type/add');
    Route::rule('task_type/edit', 'task_type/edit');
    Route::rule('task_type/delete', 'task_type/delete');
    Route::rule('task_stage/lists', 'task_stage/lists');//阶段任务列表
    Route::rule('task_stage/add', 'task_stage/add');
    Route::rule('task_stage/edit', 'task_stage/edit');
    Route::rule('task_stage/delete', 'task_stage/delete');
    Route::rule('task_stage/mode', 'task_stage/mode');//阶段任务开启/关闭
    Route::rule('task_stage_record/lists', 'task_stage_record/lists');//阶段任务奖励领取记录
    Route::rule('/task_stage_type/add','/task_stage_type/add');
    Route::rule('/task_stage_type/edit','/task_stage_type/edit');
    Route::rule('/task_stage_type/delete','/task_stage_type/delete');

    //签到
    //签到管理
    Route::rule('sign/lists', 'sign/lists');
    Route::rule('sign/add', 'sign/add');
    Route::rule('sign/edit', 'sign/edit');
    Route::rule('sign/delete', 'sign/delete');
    //天数管理
    Route::rule('sign_day/lists', 'signDay/lists');
    Route::rule('sign_day/add', 'signDay/add');
    Route::rule('sign_day/edit', 'signDay/edit');
    Route::rule('sign_day/delete', 'signDay/delete');
    Route::rule('sign_record/lists', 'sign/recordLists');//签到记录

    //转盘在线任务
    Route::rule('lotteryTask/lists', 'lotteryTask/lists');
    Route::rule('lotteryTask/add', 'lotteryTask/add');
    Route::rule('lotteryTask/edit', 'lotteryTask/edit');
    Route::rule('lotteryTask/delete', 'lotteryTask/delete');
    Route::rule('lotteryTaskrecord/lists', 'lotteryTask/recordLists');//签到记录

    //领取码
    Route::rule('receive_code/lists', 'receive_code/lists');//领取码
    Route::rule('receive_code/add', 'receive_code/add');
    Route::rule('receive_code/edit', 'receive_code/edit');
    Route::rule('receive_code/delete', 'receive_code/delete');
    Route::rule('receive_code_record/lists', 'receive_code_record/lists');//领取记录

    //PDD
    //PDD管理
    Route::rule('pdd/lists', 'pdd/lists');
    Route::rule('pdd/add', 'pdd/add');
    Route::rule('pdd/edit', 'pdd/edit');
    Route::rule('pdd/delete', 'pdd/delete');
    //邀请记录
    Route::rule('pdd/inviteRecord', 'pdd/inviteRecord');
    //转盘记录
    Route::rule('pdd/turntableRecord', 'pdd/turntableRecord');
    //提款记录
    Route::rule('pdd/withdrawalRecord', 'pdd/withdrawalRecord');
    //PDD号码管理
    Route::rule('pddPhone/lists', 'pddPhone/lists');
    Route::rule('pddPhone/add', 'pddPhone/add');
    Route::rule('pddPhone/edit', 'pddPhone/edit');
    Route::rule('pddPhone/delete', 'pddPhone/delete');
    Route::rule('pddPhone/import', 'pddPhone/import');


    Route::rule('serviceUrl/lists', 'serviceUrl/lists');//链接
    Route::rule('serviceUrl/add', 'serviceUrl/add');
    Route::rule('serviceUrl/edit', 'serviceUrl/edit');
    Route::rule('serviceUrl/delete', 'serviceUrl/delete');
    Route::rule('serviceUrl/statistics', 'serviceUrl/statistics');//统计

    //-----附属管理配置 start---------
    Route::rule('affiliate/edit', 'affiliate/edit');
    //-----附属管理配置 end---------

    //-----附属管理通讯平台配置 start---------
    Route::rule('affiliatePlatform/lists', 'affiliatePlatform/lists');//链接
    Route::rule('affiliatePlatform/add', 'affiliatePlatform/add');
    Route::rule('affiliatePlatform/edit', 'affiliatePlatform/edit');
    Route::rule('affiliatePlatform/delete', 'affiliatePlatform/delete');
    //-----附属管理通讯平台配置 end---------

    //-----首页游戏排行榜管理 start---------
    Route::rule('gameRank/lists', 'gameRank/lists');//链接
    Route::rule('gameRank/add', 'gameRank/add');
    Route::rule('gameRank/edit', 'gameRank/edit');
    Route::rule('gameRank/delete', 'gameRank/delete');
    //-----首页游戏排行榜管理 end---------

    //-----首页菜单+规则管理 start---------
    Route::rule('indexMenu/lists', 'indexMenu/lists');//链接
    Route::rule('indexMenu/add', 'indexMenu/add');
    Route::rule('indexMenu/edit', 'indexMenu/edit');
    Route::rule('indexMenu/delete', 'indexMenu/delete');
    //-----首页菜单+规则管理 end---------

    //-----默认头像管理 start---------
    Route::rule('defaultHeadImg/lists', 'defaultHeadImg/lists');//链接
    Route::rule('defaultHeadImg/add', 'defaultHeadImg/add');
    Route::rule('defaultHeadImg/edit', 'defaultHeadImg/edit');
    Route::rule('defaultHeadImg/delete', 'defaultHeadImg/delete');
    //-----默认头像管理 end---------

    //-----实名认证数据配置管理 start---------
    Route::rule('userVerifyConfig/lists', 'userVerifyConfig/lists');
    Route::rule('userVerifyConfig/add', 'userVerifyConfig/add');
    Route::rule('userVerifyConfig/edit', 'userVerifyConfig/edit');
    Route::rule('userVerifyConfig/delete', 'userVerifyConfig/delete');
    //-----实名认证数据配置管理 end---------

    //-----实名认证数据管理 start---------
    Route::rule('userVerify/lists', 'userVerify/lists');
    Route::rule('userVerify/add', 'userVerify/add');
    Route::rule('userVerify/edit', 'userVerify/edit');
    Route::rule('userVerify/delete', 'userVerify/delete');
    //-----实名认证数据管理 end---------


    //预测页
    Route::rule('forecast/lists', 'forecast/lists');
    Route::rule('forecast/add', 'forecast/add');
    Route::rule('forecast/edit', 'forecast/edit');
    Route::rule('forecast/delete', 'forecast/delete');
    //预测游戏
    Route::rule('forecastGame/lists', 'forecastGame/lists');
    Route::rule('forecastGame/add', 'forecastGame/add');
    Route::rule('forecastGame/edit', 'forecastGame/edit');
    Route::rule('forecastGame/delete', 'forecastGame/delete');

    //五 福
    Route::rule('wufu/lists', 'wufu/lists');
    Route::rule('wufu/add', 'wufu/add');
    Route::rule('wufu/edit', 'wufu/edit');
    Route::rule('wufu/delete', 'wufu/delete');
    //五福卡片
    Route::rule('wufuCards/lists', 'wufuCards/lists');
    Route::rule('wufuCards/add', 'wufuCards/add');
    Route::rule('wufuCards/edit', 'wufuCards/edit');
    Route::rule('wufuCards/delete', 'wufuCards/delete');
    Route::rule('wufuCardsRecord/lists', 'wufu/cardRecord');#卡片记录
    Route::rule('wufuWithdrawalRecord/lists', 'wufu/withdrawalRecord');#卡片记录

    //五福内容
    Route::rule('WufuContent/lists', 'WufuContent/lists');
    Route::rule('WufuContent/add', 'WufuContent/add');
    Route::rule('WufuContent/edit', 'WufuContent/edit');
    Route::rule('WufuContent/delete', 'WufuContent/delete');

    //广告管理
    Route::rule('advertising/lists', 'advertising/lists');
    Route::rule('advertising/add', 'advertising/add');
    Route::rule('advertising/edit', 'advertising/edit');
    Route::rule('advertising/delete', 'advertising/delete');


    //火箭活动管理
    Route::rule('rocketActivity/lists', 'rocketActivity/lists');
    Route::rule('rocketActivity/add', 'rocketActivity/add');
    Route::rule('rocketActivity/edit', 'rocketActivity/edit');
    Route::rule('rocketActivity/delete', 'rocketActivity/delete');
    Route::rule('rocketActivityRecord/Lists', 'rocketActivity/recordLists');
    Route::rule('rocketActivityBox/Lists', 'rocketActivity/boxLists');
    Route::rule('rocketActivityFuel/Lists', 'rocketActivity/fuelLists');

    //火箭活动区间配置管理
    Route::rule('rocketActivityLevel/lists', 'rocketActivityLevel/lists');
    Route::rule('rocketActivityLevel/add', 'rocketActivityLevel/add');
    Route::rule('rocketActivityLevel/edit', 'rocketActivityLevel/edit');
    Route::rule('rocketActivityLevel/delete', 'rocketActivityLevel/delete');

    //红包雨活动管理
    Route::rule('redPacket/lists', 'redPacket/lists');
    Route::rule('redPacket/add', 'redPacket/add');
    Route::rule('redPacket/edit', 'redPacket/edit');
    Route::rule('redPacket/delete', 'redPacket/delete');
    Route::rule('redPacketAward/lists', 'redPacket/awardLists');
    Route::rule('redPacketDay/lists', 'redPacket/dayList');
    Route::rule('redPacketDay/edit', 'redPacket/dayEdit');

    //音乐
    Route::rule('music/lists', 'music/lists');
    Route::rule('music/add', 'music/add');
    Route::rule('music/edit', 'music/edit');
    Route::rule('music/delete', 'music/delete');


    //报表管理
    Route::rule('index/getMatch', 'index/getMatch');
    Route::rule('index/overtime_match', 'index/overtime_match');
    Route::rule('index/cancelgame', 'index/cancelgame');
    Route::rule('index/index', 'index/index');
    Route::rule('index/bind_google', 'index/bind_google');
    Route::rule('index/work', 'index/work');
    Route::rule('index/set', 'index/set');
    Route::rule('index/cancel_match', 'index/cancel_match');
    Route::rule('chart_caiwu/list', 'chart_caiwu/list');//财务报表
    Route::rule('report/user', 'report/user');//用户输赢
    Route::rule('report/getData', 'report/getData');
    Route::rule('report/queryData', 'report/queryData');
    Route::rule('report/clear_cache', 'report/clear_cache');
    Route::rule('report/get_line', 'report/get_line');
    Route::rule('report/get_bar', 'report/get_bar');
    Route::rule('report/iprank', 'report/iprank');
    Route::rule('report/balance', 'report/balance');
    Route::rule('user_offline/leaderboard', 'user_offline/leaderboard');//充值排行榜
    Route::rule('user_withdrawal/leaderboard', 'user_withdrawal/leaderboard');//提现排行榜
    Route::rule('report/get_export_reports_list', 'report/getExportReportsList');//获取导出报表下载列表
    Route::rule('report/get_export_reports_clear', 'report/getExportReportsClear');//获取导出报表下载列表
    //代理运营报表
    Route::rule('reportAgent/getData', 'reportAgent/getData');
    Route::rule('reportAgent/clear_cache', 'reportAgent/clear_cache');
    //用户活跃报表
    Route::rule('report/get_active_report_range', 'report/getActiveReportRange');//时间范围
    Route::rule('report/get_active_report_data', 'report/getActiveReportData');//时间范围
    Route::rule('report/get_distribution_reports_class', 'report/getDistributionReportsClass');//分布报表分类
    Route::rule('report/get_distribution_reports_data', 'report/getDistributionReportsData');//分布报表数据


    //微商管理
    Route::rule('agent_rakeback/list', 'agent_rakeback/list');//代理返佣
    Route::rule('agent_rakeback/list_count', 'agent_rakeback/list_count');//代理返佣
    Route::rule('agent_selllog/list', 'agent_selllog/list');//推广奖金
    Route::rule('agent_selllog/sell', 'agent_selllog/sell');//派发推广奖金
    Route::rule('agent_selllog/reject', 'agent_selllog/reject');//拒绝派发推广奖金
    Route::rule('agent_selllog/batch', 'agent_selllog/batch');//批量处理推广奖金
    Route::rule('agent_selllog/excel', 'agent_selllog/excel');//导出
    Route::rule('agent_report/index', 'agent_report/index');//微商报表
    Route::rule('agent_report/user', 'agent_report/user');//微商下级用户报表
    Route::rule('agent_list/lists', 'agent_list/lists');//微商列表
    Route::rule('agent_setting/list', 'agent_setting/list');//微商设置
    Route::rule('agent_setting/add', 'agent_setting/add');//微商设置
    Route::rule('agent_setting/edit', 'agent_setting/edit');//微商设置
    Route::rule('agent_setting/delete', 'agent_setting/delete');//微商删除

    //银行 收款 支付管理
    Route::rule('bank_sk/list', 'bank_sk/list');//线下收款账号
    Route::rule('bank_sk/add', 'bank_sk/add');
    Route::rule('bank_sk/edit', 'bank_sk/edit');
    Route::rule('bank_sk/status_edit', 'bank_sk/status_edit');
    Route::rule('bank_sk/delete', 'bank_sk/delete');
    Route::rule('bank_sk/bank_sk_set', 'bank_sk/bank_sk_set');

    //支付方式图标管理
    Route::rule('payment_type/list', 'PaymentType/list');
    Route::rule('payment_type/add', 'PaymentType/add');
    Route::rule('payment_type/edit', 'PaymentType/edit');
    Route::rule('payment_type/delete', 'PaymentType/delete');

    //支付类型
    Route::rule('pay_type/list', 'PayType/list');
    Route::rule('pay_type/add', 'PayType/add');
    Route::rule('pay_type/edit', 'PayType/edit');
    Route::rule('pay_type/delete', 'PayType/delete');

    //系统类目设置
    Route::rule('system_lang/lists', 'systemLang/list');
    Route::rule('system_lang/add', 'systemLang/add');
    Route::rule('system_lang/edit', 'systemLang/edit');
    Route::rule('system_lang/delete', 'systemLang/delete');
    Route::rule('system_lang/batch', 'systemLang/batch');

    Route::rule('pay_set/lists', 'pay_set/lists');//支付平台
    Route::rule('pay_set/add', 'pay_set/add');
    Route::rule('pay_set/edit', 'pay_set/edit');
    Route::rule('pay_set/delete', 'pay_set/delete');
    Route::rule('pay_set/count', 'pay_set/count');//渠道统计
    Route::rule('pay_set/export', 'pay_set/export');
    Route::rule('pay_set/import', 'pay_set/import');
    Route::rule('pay_set/query_balance', 'pay_set/query_balance');
    Route::rule('pay_agent/lists', 'pay_agent/lists');//银商管理
    Route::rule('pay_agent/add', 'pay_agent/add');
    Route::rule('pay_agent/edit', 'pay_agent/edit');
    Route::rule('pay_agent/delete', 'pay_agent/delete');
    Route::rule('pay_agent/record', 'pay_agent/record');
    Route::rule('pay_agent/recharge', 'pay_agent/recharge');

    //站务管理
    Route::rule('banner/list', 'banner/list');//首页轮播图
    Route::rule('banner/add', 'banner/add');
    Route::rule('banner/edit', 'banner/edit');
    Route::rule('banner/status_edit', 'banner/status_edit');
    Route::rule('banner/delete', 'banner/delete');
    Route::rule('gonggao/list', 'gonggao/list');//公告管理
    Route::rule('gonggao/add', 'gonggao/add');
    Route::rule('gonggao/edit', 'gonggao/edit');
    Route::rule('gonggao/status_edit', 'gonggao/status_edit');
    Route::rule('gonggao/delete', 'gonggao/delete');

    //公告类型
    Route::rule('gonggaoType/list', 'GonggaoType/list');
    Route::rule('gonggaoType/add', 'GonggaoType/add');
    Route::rule('gonggaoType/edit', 'GonggaoType/edit');
    Route::rule('gonggaoType/delete', 'GonggaoType/delete');

    //教学类型
    Route::rule('teachType/list', 'teachType/list');
    Route::rule('teachType/add', 'teachType/add');
    Route::rule('teachType/edit', 'teachType/edit');
    Route::rule('teachType/delete', 'teachType/delete');


    //通知类型
    Route::rule('newsType/list', 'newsType/list');
    Route::rule('newsType/add', 'newsType/add');
    Route::rule('newsType/edit', 'newsType/edit');
    Route::rule('newsType/delete', 'newsType/delete');

    Route::rule('news/lists', 'news/lists');//基础问答
    Route::rule('news/agent_lists', 'news/agent_lists');//基础问答
    Route::rule('news/agent_daan_lists', 'news/agent_daan_lists');//基础问答
    Route::rule('news/add', 'news/add');
    Route::rule('news/edit', 'news/edit');
    Route::rule('news/delete', 'news/delete');
    Route::rule('news/agent_add', 'news/agent_add');
    Route::rule('news/agent_edit', 'news/agent_edit');
    Route::rule('news/agent_delete', 'news/agent_delete');
    Route::rule('Service/list', 'Service/list');//客服管理
    Route::rule('Service/add', 'Service/add');
    Route::rule('Service/edit', 'Service/edit');
    Route::rule('Service/status_edit', 'Service/status_edit');
    Route::rule('Service/delete', 'Service/delete');
    Route::rule('rule/lists', 'rule/lists');//规则管理
    Route::rule('rule/add', 'rule/add');
    Route::rule('rule/edit', 'rule/edit');
    Route::rule('rule/delete', 'rule/delete');
    Route::rule('rule_index/lists', 'ruleIndex/lists');//首页规则管理
    Route::rule('rule_index/add', 'ruleIndex/add');
    Route::rule('rule_index/edit', 'ruleIndex/edit');
    Route::rule('rule_index/delete', 'ruleIndex/delete');
    Route::rule('usermsg/list', 'usermsg/list');//站内信
    Route::rule('usermsg/add', 'usermsg/add');
    Route::rule('usermsg/edit', 'usermsg/edit');
    Route::rule('usermsg/delete', 'usermsg/delete');
    Route::rule('feedback/list', 'feedback/list');//反馈管理
    //pdd转盘内容设置
    Route::rule('pddNews/lists', 'pddNews/lists');
    Route::rule('pddNews/add', 'pddNews/add');
    Route::rule('pddNews/edit', 'pddNews/edit');
    Route::rule('pddNews/delete', 'pddNews/delete');

    //签到规则设置
    Route::rule('signNews/lists', 'signNews/lists');
    Route::rule('signNews/add', 'signNews/add');
    Route::rule('signNews/edit', 'signNews/edit');
    Route::rule('signNews/delete', 'signNews/delete');

    Route::group(function () {
        Route::rule('/getAsyncRoutes', 'admin/getNav');
        Route::rule('/getAsyncMenu', 'admin/getMenu');
        Route::rule('/getWsToken', 'admin/getWsToken');
        //系统管理
        Route::rule('admin/lists', 'admin/lists');//管理员列表
        Route::rule('admin/add', 'admin/add');
        Route::rule('admin/edit', 'admin/edit');
        Route::rule('admin/status_edit', 'admin/status_edit');
        Route::rule('admin/edit_password', 'admin/edit_password');
        Route::rule('admin/delete', 'admin/delete');
        Route::rule('admin/rand_char', 'admin/rand_char');
        Route::rule('index/getData', 'index/getData');
    })->middleware('ManageCheckLogin');

    Route::rule('admin_role/list', 'admin_role/list');//角色管理
    Route::rule('admin_role/edit', 'admin_role/edit');
    Route::rule('admin_role/add', 'admin_role/add');
    Route::rule('admin_role/delete', 'admin_role/delete');
    Route::rule('system/edit', 'system/edit');//系统设定
    Route::rule('system/upd_list', 'system/upd_list');//系统维护
    Route::rule('system/upd_add', 'system/upd_add');//系统维护
    Route::rule('system/upd_edit_status', 'system/upd_edit_status');//系统维护
    Route::rule('system/clear_cache', 'system/clear_cache');//清除缓存
    Route::rule('admin_log/list', 'admin_log/list');//操作记录
    Route::rule('user_admin_log/list', 'user_admin_log/list');//用户操作记录
    Route::rule('third_set/list', 'third_set/list');//三方登陆管理
    Route::rule('third_set/add', 'third_set/add');
    Route::rule('third_set/edit', 'third_set/edit');
    Route::rule('ip_limit/list', 'ip_limit/list');//ip黑名单
    Route::rule('ip_limit/add', 'ip_limit/add');//ip黑名单
    Route::rule('ip_limit/edit', 'ip_limit/edit');
    Route::rule('ip_limit/delete', 'ip_limit/delete');
    Route::rule('admin_node/list', 'admin_node/list');// 获取菜单权限列表
    Route::rule('admin_node/edit', 'admin_node/edit');
    Route::rule('admin_node/add', 'admin_node/add');
    Route::rule('admin_node/delete', 'admin_node/delete');
    Route::rule('admin_node/setSort', 'admin_node/setSort');
    Route::rule('admin_node/get_data', 'admin_node/get_data');
    //三方日志
    Route::rule('third_log/lists', 'third_log/lists');//三方日志列表
    Route::rule('third_log/roll', 'third_log/roll');//回退


    //榜单管理
    Route::rule('bangdan/list', 'bangdan/list');//榜单设定
    Route::rule('bangdan/add', 'bangdan/add');//
    Route::rule('bangdan/edit', 'bangdan/edit');//
    Route::rule('bangdan/delete', 'bangdan/delete');//

    Route::rule('false_accout/list', 'false_accout/list');//假账户管理
    Route::rule('false_accout/add', 'false_accout/add');//
    Route::rule('false_accout/edit', 'false_accout/edit');//
    Route::rule('false_accout/delete', 'false_accout/delete');//

    //语音管理
    Route::rule('nation/index', 'nation/index');//国家列表
    Route::rule('nation/edit', 'nation/edit');
    Route::rule('nation/add', 'nation/add');
    Route::rule('nation/delete', 'nation/delete');
    Route::rule('nation/state_edit', 'nation/state_edit');
    Route::rule('language/index', 'language/index');//国家列表
    Route::rule('language/items', 'language/items');//语言类目
    Route::rule('language/edit', 'language/edit');
    Route::rule('language/add', 'language/add');
    Route::rule('language/delete', 'language/delete');
    Route::rule('language/delitem', 'language/delitem');
    Route::rule('language/edititem', 'language/edititem');
    Route::rule('language/setstatus', 'language/setstatus');
    Route::rule('language/translation', 'language/translation');
    Route::rule('language/update', 'language/update');
    Route::rule('language/excel', 'language/excel');
    Route::rule('language/import', 'language/import');

    Route::rule('journalism/list', 'journalism/list');
    Route::rule('journalism/add', 'journalism/add');
    Route::rule('journalism/edit', 'journalism/edit');
    Route::rule('journalism/delete', 'journalism/delete');
    Route::rule('journalism/status_edit', 'journalism/status_edit');
    Route::rule('journalism/type', 'journalism/type');
    Route::rule('journalism/type_add', 'journalism/type_add');
    Route::rule('journalism/type_edit', 'journalism/type_edit');
    Route::rule('journalism/type_status_edit', 'journalism/type_status_edit');
    Route::rule('journalism/type_delete', 'journalism/type_delete');

    //短信
    Route::rule('sms/list', 'sms/list');
    Route::rule('sms/add', 'sms/add');
    Route::rule('sms/edit', 'sms/edit');
    Route::rule('sms/delete', 'sms/delete');

    //邮箱
    Route::rule('emailConfigs/list', 'emailConfigs/list');
    Route::rule('emailConfigs/add', 'emailConfigs/add');
    Route::rule('emailConfigs/edit', 'emailConfigs/edit');
    Route::rule('emailConfigs/delete', 'emailConfigs/delete');

    Route::rule('emailConfigs/list_template', 'emailConfigs/list_template');
    Route::rule('emailConfigs/add_template', 'emailConfigs/add_template');
    Route::rule('emailConfigs/edit_template', 'emailConfigs/edit_template');
    Route::rule('emailConfigs/delete_template', 'emailConfigs/delete_template');

    Route::rule('fake/game', 'fake/game');
    Route::rule('fake/add', 'fake/add');
    Route::rule('fake/delete', 'fake/delete');

    Route::rule('slottery/list', 's_lottery/list');
    Route::rule('slottery/add', 's_lottery/add');
    Route::rule('slottery/delete', 's_lottery/delete');
    Route::rule('slottery/setodd', 's_lottery/setodd');
    Route::rule('slottery/getodd', 's_lottery/getodd');
    Route::rule('slottery/result/preset', 's_lottery_result/preset');
    Route::rule('slottery/result', 's_lottery_result/list');
    Route::rule('slottery/order', 's_lottery_order/list');

    Route::rule('remain/index', 'remain/index');
    Route::rule('remain/agent', 'remainAgent/index');
    Route::rule('remainAgent/update', 'remainAgent/updateData');
    Route::rule('remain/user', 'remain/user');
    Route::rule('remain/agentuser', 'remain/agentuser');

    Route::rule('lotteryConfig/lists', 'LotteryConfig/lists');
    Route::rule('lotteryConfig/add', 'LotteryConfig/add');
    Route::rule('lotteryConfig/edit', 'LotteryConfig/edit');
    Route::rule('lotteryConfig/delete', 'LotteryConfig/delete');

    Route::rule('lotteryNews/lists', 'LotteryNews/lists');//转盘简介
    Route::rule('lotteryNews/add', 'LotteryNews/add');
    Route::rule('lotteryNews/edit', 'LotteryNews/edit');
    Route::rule('lotteryNews/delete', 'LotteryNews/delete');

    Route::group(function () {
        Route::rule('user_tag/list', 'user_tag/index');
        Route::rule('user_tag/add', 'user_tag/add');
        Route::rule('user_tag/delete', 'user_tag/delete');
        Route::rule('tag/list', 'tag/index');
        Route::rule('tag/add', 'tag/add');
        Route::rule('tag/delete', 'tag/delete');

        Route::rule('currency/list', 'currency/list');//货币管理
        Route::rule('currency/add', 'currency/add');
        Route::rule('currency/edit', 'currency/edit');
        Route::rule('currency/state_edit', 'currency/state_edit');
        Route::rule('currency/set', 'currency/set');


        Route::rule('ads/list', 'ads/index');
        Route::rule('ads/add', 'ads/add');
        Route::rule('ads/delete', 'ads/delete');
        Route::rule('ads/channel', 'ads/channel');
        Route::rule('ads/channel_add', 'ads/channel_add');
        Route::rule('ads/channel_delete', 'ads/channel_delete');
        Route::rule('ads/test', 'ads/channel_test');
        Route::rule('ads/report', 'ads/report');

        //RTP配置
        Route::rule('game/rtpList', 'game/rtpList');//rtp列表
        Route::rule('game/rtpEdit', 'game/rtpEdit');//rtp编辑
        Route::rule('game/rtpAdd', 'game/rtpAdd');//rtp添加

    })->middleware('ManageCheckLogin');

    Route::rule('i18n/config', 'web_config/getI18n');

    Route::rule('follow/userreport', 'follow/userreport');//会员报表
    Route::rule('follow/pendingfollow', 'follow/pendingfollow');//回访列表
    Route::rule('follow/distribute', 'follow/distribute');//赠金列表
    Route::rule('follow/reports', 'follow/reports');//回访报表
    Route::rule('follow/assignsave', 'follow/assignsave');//批量派发管理员
    Route::rule('follow/save', 'follow/save');//回访编辑
    Route::rule('follow/distributesave', 'follow/distributesave');//派发赠金
    Route::rule('follow/distributionconfig', 'follow/distributionconfig');//赠金配置
    Route::rule('follow/followlist', 'follow/followlist');//派发记录

    //钱包配置
    Route::rule('wallet/edit', 'wallet/edit');//钱包配置

    //jackpot排行榜配置
    Route::rule('jackpot/config', 'jackpot/config');//配置列表
    Route::rule('jackpot/configEdit', 'jackpot/configEdit');//配置编辑
    Route::rule('jackpot/configActive', 'jackpot/configActive');//配置活动内容
    Route::rule('jackpot/configActiveSave', 'jackpot/configActiveSave');//配置活动内容添加/修改
    //jackpot排行榜配置内容
    Route::rule('jackpotContent/lists', 'jackpotContent/lists');
    Route::rule('jackpotContent/add', 'jackpotContent/add');
    Route::rule('jackpotContent/edit', 'jackpotContent/edit');
    Route::rule('jackpotContent/delete', 'jackpotContent/delete');
    //jackpot排行榜
    Route::rule('jackpotRankings/lists', 'jackpotRankings/lists');
    Route::rule('jackpotRankings/add', 'jackpotRankings/add');
    Route::rule('jackpotRankings/edit', 'jackpotRankings/edit');
    Route::rule('jackpotRankings/delete', 'jackpotRankings/delete');
    //排行榜发放记录
    Route::rule('jackpotRecord/lists', 'jackpotRecord/lists');

    //TelegramBot消息模版列表
    Route::rule('TelegramBot/index', 'TelegramBot/index');
    //TelegramBot配置/修改
    Route::rule('TelegramBot/config', 'TelegramBot/config');
    //TelegramBot消息模版添加
    Route::rule('TelegramBot/add', 'TelegramBot/add');
    //TelegramBot消息模版修改
    Route::rule('TelegramBot/edit', 'TelegramBot/edit');
    //TelegramBot消息模版删除
    Route::rule('TelegramBot/delete', 'TelegramBot/delete');
    //TelegramBot消息模版启用/禁用
    Route::rule('TelegramBot/status', 'TelegramBot/status');
    //TelegramBot消息模版发送测试
    Route::rule('TelegramBot/messageTest', 'TelegramBot/sendtest');
    
})->middleware([
    // 跨域请求支持
    'AllowCrossDomain',
    'Language'
]);
