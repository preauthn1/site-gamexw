<?php
namespace app\common\model;
use think\Model;
class BankSkSetModel extends Model
{
	protected $name = 'bank_sk_set';
	protected $schema = [
        'id'          => 'int',
        'set_content'          => 'text',
        'pay_type'          => 'varchar',
    ];
    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'set_content'=>lang('银行卡参数设置'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }

}