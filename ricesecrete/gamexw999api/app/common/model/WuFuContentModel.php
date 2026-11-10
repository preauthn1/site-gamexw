<?php

namespace app\common\model;

use think\Model;

class WuFuContentModel extends Model{

    protected $name = 'wufu_content';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'w_id'          => 'int',
        'lang' => 'varchar',
        'name' => 'varchar',
        'image' => 'varchar',
        'losing_lottery_img' => 'varchar',
        'intro' => 'varchar',
        'introduce' => 'text',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'w_id'=>lang('五福id'),
            'lang'=>lang('语言'),
            'name'=>lang('名称'),
            'image'=>lang('封面'),
            'losing_lottery_img'=>lang('未中奖图片'),
            'intro'=>lang('简介'),
            'introduce'=>lang('介绍'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}