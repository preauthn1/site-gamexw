<?php

namespace app\common\model;

use think\Model;

class AffiliatePlatformModel extends Model
{
    protected $name = 'affiliate_platform';
    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'icon' => 'varchar',
        'url' => 'varchar'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'name' => lang('平台名称'),
            'icon' => lang('平台图标'),
            'url' => lang('平台链接')
        ];
        $set_key = [

        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }

}