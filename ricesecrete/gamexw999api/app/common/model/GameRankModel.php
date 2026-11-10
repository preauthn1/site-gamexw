<?php

namespace app\common\model;

use think\Model;

class GameRankModel extends Model
{
    protected $name = 'game_rank';

    protected $schema = [
        'id' => 'int',
        'key_val' => 'varchar',
        'name' => 'varchar',
        'icon' => 'varchar',
        'icon_url' => 'varchar',
        'type' => 'tinyint',
        'sort' => 'tinyint',
        'status' => 'tinyint',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'key_val' => lang('排行数据键值'),
            'name' => lang('排行名称'),
            'icon' => lang('排行图标'),
            'icon_url' => lang('排行图标地址'),
            'type' => lang('排行类型'),
            'sort' => lang('排序'),
            'status' => lang('状态'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }


}