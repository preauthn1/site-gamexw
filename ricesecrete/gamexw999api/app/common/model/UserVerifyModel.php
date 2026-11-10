<?php

namespace app\common\model;

use think\Model;

class UserVerifyModel extends Model
{
    protected $name = 'user_verify';

    protected $schema = [
        'id' => 'int',
        'uid' => 'int',
        'content' => 'text',
        'status' => 'tinyint',
        'remarks' => 'varchar',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'uid' => lang('用户id'),
            'content' => lang('认证数据'),
            'status' => lang('状态'),
            'remarks' => lang('审核备注'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }


}