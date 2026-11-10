<?php

namespace app\common\model;

use think\Model;

class ServiceUrlModel extends Model {

    protected $name = 'service_url';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const TELEGRAM_TYPE = 0;#telegram
    const WHATSAPP_TYPE = 1;#WhatsApp

    const PC_MODE = 0;#pc
    const PHONE_MODE = 1;#手机


    protected $schema = [
        'id'          => 'int',
        'type'  =>  'tinyint',
        'mode'  =>  'tinyint',
        'url'  =>  'varchar',
        'num'  =>  'int',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'num'=>lang('访问数量'),
            'type'=>lang('类型（0telegram，1WhatsApp）'),
            'mode'=>lang('机型（0pc，1手机）'),
            'url'=>lang('链接'),
        ];
        $set_key = [
            ['type'=>[0=>lang('telegram'), 1=>lang('WhatsApp')],],
            ['model'=>[0=>lang('pc'), 1=>lang('手机')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }



}