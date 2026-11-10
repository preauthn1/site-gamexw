<?php
namespace app\common\model;
use think\Model;
class FalseAccoutModel extends Model
{
	protected $name = 'false_accout';
	protected $schema = [
        'id'          => 'int',
        'name'  =>  'varchar',
        'b_price'  =>  'decimal',
        'hll'  =>  'float',
        'zhl'  =>  'float',
        'status'  =>  'int',
        'remarks'  =>  'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'b_price'=>lang('本金'),
            'hll'=>lang('获利率'),
            'zhl'=>lang('总获利'),
            'status'=>lang('状态'),
            'remarks'=>lang('备注'),
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}