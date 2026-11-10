<?php
namespace app\common\model;
use think\Model;
class UserThirdModel extends Model
{
	protected $name = 'user_third';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'third_uid'          => 'text',
        'third_sys_sign' =>  'text',
    ];
}