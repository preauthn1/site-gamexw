<?php

namespace app\common\model;

use think\Model;

class ForecastModel extends Model {

    protected $name = 'forecast';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'            => 'int',
        'lang'          =>  'varchar',
        'banner_url'    =>  'varchar',
        'introduce'     =>  'text',
        'invite_code'   =>  'int',
        'create_time'   =>  'int',
        'update_time'   =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语言'),
            'banner_url'=>lang('banner url'),
            'introduce'=>lang('介绍'),
            'invite_code'=>lang('邀请码'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }



}