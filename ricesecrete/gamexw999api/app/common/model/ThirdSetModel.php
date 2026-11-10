<?php
namespace app\common\model;
use think\Model;
class ThirdSetModel extends Model
{
	protected $name = 'third_set';
    protected $schema = [
        'id'          => 'int',
        'third_sys_sign' =>  'text',
        'name' =>  'text',
        'status' =>  'int',
        'sort' =>  'int',
        'appid' =>  'text',
        'secretkey' =>  'text',
        'icon' =>  'text',
        'callback' =>  'text',
    ];
}