<?php
namespace app\common\model;
use think\Model;
class TelegrambotMessageModel extends Model
{
    protected $name = 'telegrambot_message';

    protected $schema = [
        'id'          => 'int',
        'type' => 'tinyint',
        'text' => 'varchar',
        'lang'  =>  'varchar',
        'number'  =>  'int',
        'min_price'  =>  'decimal',
        'max_price'  =>  'decimal',
        'status'  =>  'tinyint',
        'chat_id'  =>  'varchar',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('类型'),
            'text'=>lang('发送内容'),
            'lang'=>lang('发送语言'),
            'number'=>lang('单次发送数量'),
            'min_price'=>lang('随机最小金额'),
            'max_price'=>lang('随机最大金额'),
            'status'=>lang('状态'),
            'chat_id'=>lang('发送目标ID'),
        ];
        $set_key = [
            ['type'=>[1=>lang('充值申请'),2=>lang('充值成功'),3=>lang('提现申请'),4=>lang('提现成功'),5=>lang('游戏派彩')]],
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}