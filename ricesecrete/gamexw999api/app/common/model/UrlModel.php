<?php
namespace app\common\model;
use think\Model;
class UrlModel extends Model
{
    protected $name = 'url';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'name' => 'varchar',
        'url'  =>  'varchar',
        'is_menu'  =>  'tinyint',
        'sort'  =>  'int',
        'icon'  =>  'varchar',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'url'=>lang('链接'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}