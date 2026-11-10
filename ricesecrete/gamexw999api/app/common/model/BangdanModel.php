<?php
namespace app\common\model;
use think\Model;
class BangdanModel extends Model
{
	protected $name = 'bangdan';
	protected $schema = [
        'id'          => 'int',
        'add_time'          => 'bigint',
        'issue_number'  =>  'varchar',
        'start_date' =>  'bigint',
        'end_date' =>  'bigint',
        'f_a_arr' =>  'text',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'start_date'=>lang('期号开始时间'),
            'end_date'=>lang('期号结束日期'),
            'issue_number'=>lang('期号'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}