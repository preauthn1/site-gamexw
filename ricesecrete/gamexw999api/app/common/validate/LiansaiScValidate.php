<?php
namespace app\common\validate;
use think\Validate;
class LiansaiScValidate extends Validate
{
    protected $rule = [
        'ls_id'  =>  'notIn:0',
        'ls_team_zid' =>  'different:ls_team_kid|notIn:0',
        'ls_team_kid'  =>  'notIn:0',
    ];

    protected $message  =   [
        'ls_id.notIn' => '联赛必须',
        'ls_team_zid.notIn'     => '主队必须',
        'ls_team_kid.notIn'   => '客队必须',
        'ls_team_zid.different' => '球队不能相同',
    ];


}