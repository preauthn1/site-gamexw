<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class UserDistributionModel extends Model
{
    protected $name = 'user_distribution';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'price'  =>  'decimal',
        'type' =>  'tinyint',
        'water' =>  'int',
        'give_status' =>  'tinyint',
        'give_time' =>  'int',
        'add_time' =>  'int',
        'admin_id' =>  'int',
        'follow_id' =>  'int',
        'salesman_id' =>  'int',
        'status' =>  'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'price'=>lang('派发金额'),
            'type'=>lang('派发方式'),
            'water'=>lang('流水倍数'),
            'status'=>lang('审核状态'),
        ];
        $set_key = [
            ['type'=>[0=>lang('手动'),1=>lang('自动')]],
            ['status'=>[0=>lang('待审核'),1=>lang('通过'),2=>lang('自动通过'),3=>lang('拒绝')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}