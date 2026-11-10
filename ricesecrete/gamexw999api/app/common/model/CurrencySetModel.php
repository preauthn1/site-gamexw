<?php
namespace app\common\model;

use think\Model;

class CurrencySetModel extends Model
{
    protected $name = 'currency_set';
    protected $schema = [
        'id'            => 'int',
        'auto_rate'     =>  'tinyint',
        'auto_time'     =>  'int',
    ];

    public function getField(): array
    {
        $field = [
            'id'=>'id',
            'auto_rate' => lang('实时汇率'),
            'auto_time' => lang('获取实时汇率间隔'),
        ];
        $set_key = [
            ['auto_rate' => [ 0 =>lang('手动'), 1 => lang('自动')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}