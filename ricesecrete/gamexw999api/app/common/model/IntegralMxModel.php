<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
use app\common\model\UserModel;

class IntegralMxModel extends Model
{
    protected $name = 'integral_mx';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'old_integral'          => 'int',
        'new_integral'          => 'int',
        'integral'          => 'int',
        'type'          => 'tinyint',
        'msg'          => 'varchar',
        'add_time'          => 'int',
    ];


    /*
     * uid 用户id
     * type 1增 2减
     * price 操作的金额
     * msg 操作备注
     * */
    public static function cz_mx($uid,$type,$price,$msg){
        if( $price <= 0 ){
            return true;
        }
        $integral = UserModel::where(['id'=>$uid])->value('integral');
        $arr['old_integral'] = $integral;
        if($type == 1){
            $arr['new_integral'] = $integral + $price;
        }else{
            $new_integral = $integral - $price;
            $arr['new_integral'] = $new_integral > 0 ? $new_integral:0;
        }
        $arr['integral'] = $price;
        $arr['msg'] = $msg;
        $arr['type'] = $type;
        $arr['uid'] = $uid;
        $arr['add_time'] = time();
        UserModel::where(['id'=>$uid])->update(['integral'=>$arr['new_integral']]);
        self::insertGetId($arr);
        return $arr;
    }

}