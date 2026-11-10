<?php

namespace app\common\model;

use think\Model;
use think\facade\Db;

class RuleIndexModel extends Model
{
    protected $name = 'rule_index';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'type' => 'tinyint',
        'content' => 'text',
        'sort' => 'int',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'type' => lang('规则类型'),
            'lang' => lang('规则语种'),
            'content' => lang('规则内容'),
            'sort' => lang('规则排序'),
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }

}