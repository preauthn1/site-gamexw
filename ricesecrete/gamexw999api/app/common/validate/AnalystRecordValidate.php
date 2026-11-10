<?php
namespace app\common\validate;
use think\Validate;
class AnalystRecordValidate extends Validate
{
    protected $rule = [
        'aid'  =>  'notIn:0',
        'sc_id' =>  'notIn:0',
        'bd_id' =>  'notIn:0',
    ];

    protected $message  =   [
        'aid.notIn'  => '分析师必须',
        'sc_id.notIn' => '赛事必须',
        'bd_id.notIn' => '波胆必须',
    ];


}