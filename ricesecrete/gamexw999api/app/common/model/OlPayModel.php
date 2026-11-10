<?php
namespace app\common\model;
use think\Model;
class OlPayModel extends Model
{
	protected $name = 'ol_pay';
	protected $schema = [
        'id'          => 'int',
        'add_time' =>  'bigint',
        'uid' =>  'int',
        'pay_number' =>  'varchar',
        'pay_price' =>  'decimal',
        'pay_type' =>  'varchar',
        'pay_status' =>  'int',
        'othr_number' =>  'varchar',
        'lebal'  =>  'tinyint',
    ];
    

    //查看是否有线上充值
    public static function is_pay($uid){
        return self::where("uid",$uid)->where("pay_status",1)->find();
    }

}