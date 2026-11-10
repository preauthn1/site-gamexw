<?php
namespace app\common\model;
use think\Model;
class WalletSystemModel extends Model
{

	protected $name = 'wallet_system';
	protected $schema = [
        'id'          => 'int',
        'wallet_field'          => 'varchar',
        'wallet_value'          => 'bigint',
        'wallet_introduce'          => 'int',
        'create_time'          => 'varchar',
        'update_time'          => 'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'wallet_field'=>lang('字段'),
            'wallet_value'=>lang('字段对应值'),
            'wallet_introduce'=>lang('字段描述'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}