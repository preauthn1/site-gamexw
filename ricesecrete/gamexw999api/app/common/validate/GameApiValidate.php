<?php

namespace app\common\validate;

use think\Validate;

class GameApiValidate extends Validate
{

    protected $rule = [
        'ga_tcid'  =>  'require|gt:0',
        'ga_func'  =>  'require|unique:game_apis,ga_func^ga_tcid',
//        'ga_route'  =>  'require',//部分游戏不需要路由地址
        'ga_rtype' => 'require|in:post,get',
        'ga_type' => 'require|in:getauthtoken,reg,first,other,task,getusertoken',
    ];

    protected $message  =   [
        'ga_tcid.require' => '平台ID必须',
        'ga_tcid.gt' => '平台ID必须',
        'ga_func.require' => '功能模块必须',
        'ga_func.unique' => '功能模块不能重复',
//        'ga_route.require' => '路由地址必须',
        'ga_rtype.require' => '请求方式必须',
        'ga_rtype.in' => '请求方式参数有误，必须是:post,get',
        'ga_type.require' => '流程类型必须',
        'ga_type.in' => '流程类型参数有误，必须是:getauthtoken,reg,first,other,task,getusertoken',
    ];

}