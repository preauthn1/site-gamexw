<?php
namespace app\common\validate;
use think\Validate;

class GameSetValidate extends Validate
{
    protected $rule = [
        'game_sys_sign'  =>  'require',
        'game_name' =>  'require',
        'game_code' =>  'require',
    ];

    protected $message  =   [
//        'game_sys_sign.unique' => '游戏标识是唯一的',
        'game_sys_sign.require' => '游戏标识必须',
        'game_code.require'     => '游戏代号必须',
        'game_name.require'     => '游戏名称必须',
    ];

    public function sceneEdit()
    {
        return $this->only(['game_name']);
    }

}