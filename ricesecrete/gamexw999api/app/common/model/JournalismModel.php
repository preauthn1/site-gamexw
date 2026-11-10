<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class JournalismModel extends Model
{
    protected $name = 'journalism';
    protected $schema = [
        'id'          => 'int',
        'type_id'          => 'int',
        'title'  =>  'varchar',
        'pic' =>  'varchar',
        'brief' =>  'varchar',
        'content' =>  'text',
        'addtime' =>  'int',
        'sort'  =>  'int',
        'start_time'  =>  'int',
        'end_time'  =>  'int',
        'amount'  =>  'decimal',
        'status' =>  'tinyint',
        'lang' =>  'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type_id'=>lang('分类id'),
            'title'=>lang('标题'),
            'pic'=>lang('封面图'),
            'brief'=>lang('简介'),
            'addtime'=>lang('添加时间'),
            'sort'=>lang('排序'),
            'status'=>lang('状态'),
            'start_time'=>lang('开始时间'),
            'end_time'=>lang('结束时间'),
            'amount'=>lang('赛事金额'),
            'lang'=>lang('语种'),
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('开启')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //设置开始时间时
    public function setStartTimeAttr($value){
        return is_numeric($value) ? $value : strtotime($value);
    }

    //设置结束时间时
    public function setEndTimeAttr($value){
        return is_numeric($value) ? $value : strtotime($value);
    }

    //取出开始时间时
    public function getStartTimeAttr($value){
        return is_numeric($value) && $value ? date('Y-m-d H:i:s', $value) : $value;
    }

    //取出结束时间时
    public function getEndTimeAttr($value){
        return is_numeric($value) && $value ? date('Y-m-d H:i:s', $value) : $value;
    }

    //关联中间表
    public function journalismGame(){
        return $this->hasMany(JournalismGameModel::class, 'journalism_id', 'id');
    }
}