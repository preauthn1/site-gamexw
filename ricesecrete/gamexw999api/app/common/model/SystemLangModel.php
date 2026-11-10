<?php

namespace app\common\model;

use think\Model;

class SystemLangModel extends Model
{
    protected $name = 'system_lang';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'title' => 'varchar',
        'agent_ids' => 'varchar',
        'type' => 'tinyint',
        'key' => 'varchar',
        'text' => 'longtext',
    ];

    //类型
    const TYPE_IMAGE = 0;#图片
    const TYPE_TEXT = 1;#文本
    const TYPE_CONTENT = 2;#富文本


    //存日志记录字段
    public function getField()
    {
        $field = [
            'lang' => lang('语言'),
            'title' => lang('标题'),
            'agent_ids' => lang('代理id串'),
            'type' => lang('类型'),
            'key' => lang('对应key'),
            'text' => lang('对应值')
        ];
        $set_key = [
            ['type'=>[0=>lang('图片'), 1=>lang('文本'), 2=>lang('富文本')]]
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}