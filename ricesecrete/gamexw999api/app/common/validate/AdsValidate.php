<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;

class AdsValidate extends Validate
{
    protected $rule = [
        'ad_name'  =>  'require',
        'ad_type' =>  'require|unique:ads',
    ];

    protected $message  =   [
        'ad_name.require' => '名称必填',
        'ad_type.require'     => '广告商必选',
        'ad_type.unique'   => '广告商不能重复添加',
    ];

}
