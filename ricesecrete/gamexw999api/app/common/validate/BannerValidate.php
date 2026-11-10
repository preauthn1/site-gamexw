<?php
namespace app\common\validate;
use think\Validate;
class BannerValidate extends Validate
{
    protected $rule = [
        'img'  =>  'require',
        'url' =>  'require',
        'start_time' =>  'require',
        'end_time' =>  'require',
        'status' =>  'require|in:1,0',
    ];

    protected $message  =   [
        'img.require' => '轮播图必须',
        'url.require'     => 'url必须',
        'start_time.require'   => '开始时间必须',
        'end_time.require'  => '结束时间必须',
        'status.require'        => '状态必须', 
        'status.in'        => '状态参数错误', 
    ];

}