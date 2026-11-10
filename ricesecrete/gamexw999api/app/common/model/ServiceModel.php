<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class ServiceModel extends Model
{
    protected $name = 'service';
    protected $schema = [
        'id'          => 'int',
        'name'          => 'varchar',
        'img'          => 'varchar',
        'introduce'  =>  'varchar',
        'link' =>  'varchar',
        'agent_id' =>  'int',
        'status' =>  'tinyint',
        'sort' =>  'int'
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('客服名称'),
            'img'=>lang('客服图标'),
            'introduce'=>lang('客服介绍'),
            'link'=>lang('客服链接'),
            'agent_id'=>lang('代理线'),
            'status'=>lang('状态'),
            'sort'=>lang('排序'),
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}