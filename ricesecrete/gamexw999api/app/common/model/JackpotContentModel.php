<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class JackpotContentModel extends Model
{
    protected $name = 'jackpot_content';
    protected $schema = [
        'id'          => 'int',
        'j_id'  =>  'int',
        'lang'  =>  'varchar',
        'introduce' =>  'longtext',
        'create_time' =>  'bigint',
        'update_time' =>  'bigint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'j_id'=>lang('配置id'),
            'lang'=>lang('语言'),
            'introduce'=>lang('规则内容'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }

}