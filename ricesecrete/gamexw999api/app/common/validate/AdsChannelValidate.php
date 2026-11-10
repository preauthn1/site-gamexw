<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;

class AdsChannelValidate extends Validate
{
    protected $rule = [
        'ac_name'  =>  'require',
        'ac_adtype' =>  'require',
        'ac_code' =>  'require',
        'ac_share_code' =>  'require',
        'ac_url' =>  'require',
        'ac_state' =>  'require',
    ];

    protected $message  =   [
        'ac_name.require' => '名称必填',
        'ac_adtype.require'     => '广告商必选',
        'ac_code.require'     => '渠道代码必填',
        'ac_share_code.require'     => '绑定代理邀请码必填',
        'ac_url.require'     => '渠道绑定域名必填',
        'ac_state.require'     => '状态必选',
    ];

}
