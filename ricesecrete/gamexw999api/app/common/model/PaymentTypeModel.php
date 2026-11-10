<?php

namespace app\common\model;

use think\Model;

class PaymentTypeModel extends Model
{
    protected $name = 'payment_type';
    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'pay_type' => 'tinyint',
        'icon' => 'varchar',
        'status' => 'tinyint',
    ];


    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'name' => lang('名称'),
            'pay_type' => lang('支付类型'),
            'icon' => lang('图标'),
            'status' => lang('状态')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}