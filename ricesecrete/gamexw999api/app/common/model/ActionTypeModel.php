<?php
namespace app\common\model;
use think\Model;
class ActionTypeModel extends Model
{
    protected $name = 'action_type';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'name' => 'varchar',
        'icon' => 'varchar',
        'sort'  =>  'tinyint',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'icon'=>lang('图标'),
            'sort'=>lang('排序'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}