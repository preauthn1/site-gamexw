<?php
namespace app\common\model;

use think\Model;

class UserAddressModel extends Model
{
    protected $name = 'user_address';
    protected $schema = [
        'id' => 'int',
        'uid' => 'int',
        'pay_id' => 'int',
        'address' => 'varchar',
        'qr_code' => 'varchar',
        'time' => 'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'address'=>lang('收款地址'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}