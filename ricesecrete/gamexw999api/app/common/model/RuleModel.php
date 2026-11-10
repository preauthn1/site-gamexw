<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class RuleModel extends Model
{
	protected $name = 'rule';
	protected $schema = [
        'id'          => 'int',
        'lang'          => 'varchar',
        'type'  =>  'tinyint',
        'game_id' => 'int',
        'content' =>  'text'
    ];
    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('规则类型'),
            'lang'=>lang('规则语种'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}