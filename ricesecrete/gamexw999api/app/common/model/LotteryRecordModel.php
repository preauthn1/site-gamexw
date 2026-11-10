<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class LotteryRecordModel extends Model
{
    protected $name = 'lottery_record';
    protected $schema = [
        'id'          => 'int',
        'uid'  =>  'tinyint',
        'lottery_id'  =>  'int',
        'prize_id'  =>  'int',
        'title' => 'varchar',
        'time' => 'int',
        'prize_type' => 'tinyint',
        'price' => 'decimal',
        'is_free' => 'tinyint',
        'prize_price'=>'decimal',
        'integral'=>'int',
        'is_fake'=>'tinyint',
        'is_away'=>'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'lottery_id'=>lang('转盘id'),
            'prize_id'=>lang('奖品类型'),
            'title'=>lang('奖品名称'),
            'prize_type'=>lang('奖品类型'),
            'price'=>lang('消费金额'),
            'is_free'=>lang('是否免费'),
            'prize_price'=>lang('奖品金额'),
            'integral'=>lang('消费积分'),
            'is_fake'=>lang('是否假数据'),
        ];
        $set_key = [
            ['prize_type'=>[1=>lang('金额红包'),2=>lang('实体物品'),3=>lang('谢谢参与')]],
            ['is_free'=>[0=>lang('否'),1=>lang('是')]],
            ['is_fake'=>[0=>lang('否'),1=>lang('是')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}