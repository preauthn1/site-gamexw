<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class LotteryPrizeModel extends Model
{
    protected $name = 'lottery_prize';
    protected $schema = [
        'id'          => 'int',
        'lottery_id'  =>  'int',
        'type'  =>  'tinyint',
        'pic'  =>  'varchar',
        'title' => 'varchar',
        'amount' => 'decimal',
        'stock' => 'int',
        'probability' => 'tinyint',
        'sort' => 'int',
        'water' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lottery_id'=>lang('转盘id'),
            'type'=>lang('奖品类型'),
            'pic'=>lang('图片'),
            'title'=>lang('名称'),
            'amount'=>lang('金额'),
            'stock'=>lang('库存'),
            'probability'=>lang('中奖几率'),
            'sort'=>lang('排序'),
            'water'=>lang('流水倍数'),
        ];
        $set_key = [
            ['type'=>[1=>lang('金额红包'),2=>lang('实体物品'),3=>lang('谢谢参与')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}