<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class LotteryConditionRecordModel extends Model
{
    protected $name = 'lottery_condition_record';
    protected $schema = [
        'id'                    => 'int',
        'lottery_id'            => 'int',
        'uid'                   => 'int',
        'type'                  => 'tinyint',
        'reward_count'          => 'int',
        'update_time'           => 'int',
        'create_time'           => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lottery_id'=>lang('关联转盘Id'),
            'uid'=>lang('用户uid'),
            'type'=>lang('奖励类型'),
            'reward_count'=>lang('奖励次数'),
        ];
        $set_key = [
            ['type'=>[-1, lang('所有'), 0 => lang('下注金额'), 1 => lang('充值金额'), 2 => lang('倍数'), 3 => lang('累计充值'), 4 => lang('在线时长'), 5 => lang('累计下注'), 6 => lang('游戏时长')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}