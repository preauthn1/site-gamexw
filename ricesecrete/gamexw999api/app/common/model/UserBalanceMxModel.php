<?php
namespace app\common\model;
use think\facade\Session;
use app\common\model\UserModel;
use think\Model;
class UserBalanceMxModel extends Model
{
    protected $name = 'user_balance_mx';
    protected $schema = [
        'id'          => 'int',
        'add_time'          => 'bigint',
        'uid'  =>  'int',
        'old_price' =>  'decimal',
        'new_price' =>  'decimal',
        'price' =>  'decimal',
        'type' =>  'int',
        'msg' =>  'varchar',
        'source_id' => 'int',
    ];


    /**
     * 操作明细
     * @param $uid
     * @param $mx_msg 明细名称
    **/
    public static function cz_mx($uid, $mx_msg, $balance, $price, $cz, $source_id=0){
        $data = [];
        $data['uid'] = $uid;
        $data['old_price'] = $balance;
        $data['price'] = $price;
        if ($cz=="+") {
            $data['new_price'] = $balance + $price;
            $data['type'] = 1;
        }elseif($cz=="-"){
            $data['new_price'] = $balance - $price;
            $data['type'] = 2;
        }
        $data['msg'] = $mx_msg;
        $data['source_id'] = $source_id;
        $data['add_time'] = time();
        $id = self::insertGetId($data);
        $mx['id'] = $id;
        return $mx;
    }
}