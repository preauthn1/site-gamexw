<?php
namespace app\common\model;
use think\Model;
class MirrorSiteModel extends Model
{
    protected $name = 'mirror_site';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'           => 'int',
        'url'          =>  'varchar',
        'status'       =>  'tinyint',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'url'=>lang('URL'),
            'status'=>lang('状态'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}