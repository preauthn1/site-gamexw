<?php

namespace app\common\model;

use think\Model;

class PayTypeModel extends Model
{
    protected $name = 'pay_type';
    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'icon' => 'varchar',
        'sort'=>'int',
        'is_hot'=>'int',
        'status' => 'tinyint',
    ];


    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'name' => lang('名称'),
            'icon' => lang('图标'),
            'sort' => lang('排序'),
            'is_hot' => lang('热门'),
            'status' => lang('状态')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}