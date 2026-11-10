<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class UserCkModel extends Model
{
	protected $name = 'user_ck';
	protected $schema = [
        'id'          => 'int',
        'add_time'          => 'bigint',
        'uid'  =>  'int',
        'ck_number' =>  'varchar',
        'ck_amount' =>  'decimal',
        'ck_bl' =>  'decimal',
        'ck_hl_amount' =>  'decimal',
        'ck_status' =>  'int',
        'ck_integral_rate' => 'decimal',
        'ck_integral' => 'decimal',
        'action_id' =>  'int',
        'ck_r_id' =>  'int',
    ];
}