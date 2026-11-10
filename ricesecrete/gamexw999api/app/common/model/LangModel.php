<?php
namespace app\common\model;
use think\Model;
class LangModel extends Model
{
	protected $name = 'lang';
	protected $schema = [
        'id'          => 'int',
        'title'  =>  'varchar',
        'lang'  =>  'varchar',
        'remark'  =>  'varchar',
        'is_default'  =>  'tinyint',
        'flag'  =>  'varchar',
        'status'  =>  'tinyint',
        'sort'  =>  'tinyint',
        'web_status'  =>  'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'title'=>lang('语言'),
            'lang'=>lang('标识'),
            'is_default'=>lang('是否默认'),
            'status'=>lang('状态'),
            'sort'=>lang('排序'),
        ];
        $set_key = [
            ['is_default'=>[0=>lang('否'),1=>lang('是')]],
            ['status'=>[1=>lang('启用'),0=>lang('关闭')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}