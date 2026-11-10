<?php

namespace app\common\model;

use think\Model;

class NewsTypeModel extends Model
{
    protected $name = 'news_type';
    protected $schema = [
        'id' => 'int',
        'name' => 'varchar',
        'icon' => 'varchar',
        'sort'=>'int',
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
            'status' => lang('状态')
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}