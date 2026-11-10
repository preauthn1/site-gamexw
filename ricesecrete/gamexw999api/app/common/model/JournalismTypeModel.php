<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class JournalismTypeModel extends Model
{
    protected $name = 'journalism_type';
    protected $schema = [
        'id'          => 'int',
        'name'          => 'varchar',
        'sort'  =>  'int',
        'status' =>  'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('分类名称'),
            'sort'=>lang('排序'),
            'status'=>lang('状态'),
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('开启')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}