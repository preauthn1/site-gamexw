<?php

namespace app\common\model;

use think\Model;

class PddPhoneModel extends Model {

    protected $name = 'pdd_phone';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';



    protected $scene = [
        'id'                                => 'int',
        'phone'                             => 'varchar',
        'create_time'                       => 'int',
        'update_time'                       => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'phone'=>lang('号码'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}