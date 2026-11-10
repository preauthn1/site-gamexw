<?php

namespace app\common\model;

use think\Model;

class IndexMenuModel extends Model
{
    protected $name = 'index_menu';

    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'area' => 'varchar',
        'name' => 'varchar',
        'href' => 'varchar',
        'icon' => 'varchar',
        'icon_url' => 'varchar',
        'select_icon_url' => 'varchar',
        'show_img' => 'varchar',
        'show_bg1' => 'varchar',
        'show_bg2' => 'varchar',
        'logo' => 'varchar',
        'sort' => 'tinyint',
        'status' => 'tinyint',
        'agent_id' => 'varchar',
        'pid' => 'int',
        'is_img' => 'tinyint',
        'extra' => 'varchar'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'lang' => lang('语种类型'),
            'area' => lang('显示区域'),
            'name' => lang('名称'),
            'icon' => lang('图标'),
            'icon_url' => lang('图标地址'),
            'logo' => lang('logo'),
            'sort' => lang('排序'),
            'status' => lang('状态'),
            'agent_id' => lang('代理线'),
            'pid' => lang('父菜单ID'),
            'is_img' => lang('是否为图片菜单'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }

}