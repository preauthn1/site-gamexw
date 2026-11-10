<?php

namespace app\common\model;

use think\Model;

class UserVerifyConfigModel extends Model
{
    protected $name = 'user_verify_config';

    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'keyv' => 'varchar',
        'type' => 'varchar',
        'sort' => 'int',
        'introduce' => 'varchar',
        'status' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'name' => lang('认证数据名称'),
            'keyv' => lang('认证数据键值'),
            'type' => lang('认证类型'),
            'introduce' => lang('简介'),
            'sort' => lang('排序'),
            'status' => lang('状态'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }


}