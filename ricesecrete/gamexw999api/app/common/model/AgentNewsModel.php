<?php
namespace app\common\model;
use think\Model;
class AgentNewsModel extends Model
{
    protected $name = 'agent_news';
    protected $schema = [
        'id'          => 'int',
        'lang' => 'varchar',
//        'title'  =>  'text',
        'content'  =>  'text',
        'icon'  =>  'varchar',
        'image'  =>  'varchar',
        'add_time'  =>  'int',
        'type_id'  =>  'int',
        'rec_code'  =>  'int',
        'pid'  =>  'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语种'),
            'title'=>lang('问题'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}