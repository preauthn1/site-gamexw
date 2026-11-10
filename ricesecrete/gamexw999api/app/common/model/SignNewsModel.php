<?php
namespace app\common\model;
use think\Model;
class SignNewsModel extends Model
{
    protected $name = 'sign_news';
    protected $schema = [
        'id'          => 'int',
        'pid'  =>  'int',
        'lang' => 'varchar',
        'type'  =>  'tinyint',
        'news_title'  =>  'varchar',
        'news_images'  =>  'varchar',
        'news_intro'  =>  'varchar',
        'news_content'  =>  'text',
        'add_time'  =>  'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语种'),
            'news_title'=>lang('标题'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}