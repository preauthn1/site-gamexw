<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class DistributionConfigModel extends Model
{
    protected $name = 'distribution_config';
    protected $schema = [
        'id'          => 'int',
        'type' => 'tinyint',
        'max_price' => 'decimal',
        'water' => 'int',
        'timeout' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('赠金审核类型'),
            'max_price'=>lang('派发最大金额'),
            'water'=>lang('默认流水倍数'),
            'timeout'=>lang('待电访超时时间'),
        ];
        $set_key = [
            ['type'=>[0=>lang('手动'),1=>lang('自动')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}