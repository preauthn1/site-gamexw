<?php

namespace app\common\model;

use think\Model;

class WuFuCardsModel extends Model{

    protected $name = 'wufu_cards';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'w_id' => 'int',
        'name' => 'varchar',
        'image' => 'varchar',
        'amount' => 'decimal',
        'savings' => 'tinyint',
        'is_register_give' => 'tinyint',
        'odds' => 'tinyint',
        'sort' => 'tinyint',
        'type' => 'tinyint',
        'people_condition' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'w_id'=>lang('五福id'),
            'name'=>lang('名称'),
            'image'=>lang('封面'),
            'amount'=>lang('奖励金额'),
            'savings'=>lang('流水倍数'),
            'is_register_give'=>lang('是否注册赠送'),
            'odds'=>lang('中奖几率'),
            'sort'=>lang('排序'),
            'type'=>lang('类型'),
            'people_condition'=>lang('邀请人数条件（0为无效，达到后必送）'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [
            ['is_register_give'=>[0=>lang('否'), 1=>lang('是')],],
            ['type'=>[0=>lang('普通'), 1=>lang('关键卡片')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}