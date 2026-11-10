<?php
namespace app\common\model;
use think\Model;
class TelegrambotConfigModel extends Model
{
    protected $name = 'telegrambot_config';

    protected $schema = [
        'id'          => 'int',
        'api_key' => 'varchar',
        'bot_name' => 'varchar',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'api_key'=>lang('机器人token'),
            'bot_name'=>lang('机器人名称'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }
}