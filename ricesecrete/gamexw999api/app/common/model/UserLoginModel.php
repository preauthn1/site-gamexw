<?php
namespace app\common\model;
use think\Model;
class UserLoginModel extends Model
{
	protected $name = 'user_login';
	protected $schema = [
        'id'          => 'int',
        'uid' =>  'int',
        'login_ip' =>  'varchar',
        'login_time' =>  'int',
        'device' => 'text',
        'device_type' => 'varchar',
        'url' =>  'varchar',
    ];

    public static function getDeviceTypeArr(){
        return [
            'IPHONE',
            'ANDROID',
            'WINDOWS',
            'MAC',
            'IPHONE_WEB',
            'ANDROID_WEB',
            'OTHER',
        ];
    }
    
}