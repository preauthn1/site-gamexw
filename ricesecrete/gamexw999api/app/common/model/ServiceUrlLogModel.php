<?php

namespace app\common\model;

use think\Model;

class ServiceUrlLogModel extends Model {

    protected $name = 'service_url_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'            => 'int',
        'address'       =>  'varchar',
        'ip'            =>  'varchar',
        'url'           =>  'varchar',
        'uid'           =>  'int',
        'create_time'   =>  'int',
        'update_time'   =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'ip'=>lang('IP'),
            'uid'=>lang('用户id'),
            'address'=>lang('地区'),
            'url'=>lang('链接'),
            'telegram_id'=>lang('telegram_id'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }



}