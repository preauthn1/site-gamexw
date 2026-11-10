<?php
namespace app\common\model;
use think\Model;
class ArtificialBalanceModel extends Model
{
	protected $name = 'artificial_balance';
	protected $schema = [
        'id'          => 'int',
        'uid'  =>  'int',
        'a_type'          => 'int',
        'mx_id'  =>  'int',
        'admin_name' =>  'varchar',
        'price' =>  'decimal',
        'water'  =>  'int',
        'add_time'  =>  'int',
        'lebal'  =>  'tinyint',
        'msg' =>  'varchar',
        'rebate_id' =>  'int',
        'bank_sk_id' =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'a_type'=>lang('调整类型'),
            'mx_msg'=>lang('明细备注'),
            'price'=>lang('调整金额'),
            'water'=>lang('流水倍数'),
        ];
        $set_key = [
            ['a_type'=>[1=>lang('人工增加'),2=>lang('人工减少'),3=>lang('赠送彩金'),4=>lang('等级彩金'),5=>lang('充值返利'),8=>lang('流水增加'),9=>lang('流水减少')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public static function get_count($wh,$wh1){
        return self::alias('a')
            ->where($wh)
            ->where($wh1)
            ->sum('a.price');
    }
}