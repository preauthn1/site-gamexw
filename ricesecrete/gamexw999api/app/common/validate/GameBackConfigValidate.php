<?php
namespace app\common\validate;
use think\Validate;
class GameBackConfigValidate extends Validate
{
    protected $rule = [
        'gbc_viplvl'  =>  'require',
        'gbc_rate'  =>  'require|elt:1',
    ];

    protected $message  =   [
        'gbc_viplvl.require' => '等级必须',
        'gbc_rate.require' => '返水比例必须',
        'gbc_rate.elt' => '返水比例不能大于100%',
    ];
}