<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;

class NationModel extends Model
{
    protected $name = 'nation';

    protected $schema = [
        'id'          => 'int',
        'name'  =>  'varchar',
        'flag'  =>  'varchar',
        'lang' => 'varchar',
        'is_defa' => 'tinyint',
        'state' => 'tinyint',
        'sort' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('国家'),
            'lang'=>lang('语种'),
            'is_defa'=>lang('默认国家'),
            'state' => lang('状态'),
            'sort'=>lang('排序'),
        ];
        $set_key = [
            ['state' => [ 2 =>lang('关闭'), 1 => lang('开启')]],
            ['is_defa' => [ 2 =>lang('关闭'), 1 => lang('开启')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    /**
     * @param $value
     * @return string
     */
    public function setLangAttr($value)
    {
        return $value ? json_encode($value) : [];
    }

    /**
     * @param $value
     * @return array|mixed
     */
    public function getLangAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }


}