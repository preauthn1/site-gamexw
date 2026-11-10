<?php

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;

class PayAgentModel extends Model {
    use SoftDelete;

    protected $name = 'agent';
    protected $autoWriteTimestamp = 'datetime';
    protected $deleteTime = 'delete_time';
    protected $schema = [
        'id'          => 'int',
        'username' =>  'varchar',
        'password' =>  'varchar',
        'realname' =>  'varchar',
        'nickname' =>  'varchar',
        'recharge' =>  'varchar',
        'ad' =>  'varchar',
        'avatar' =>  'varchar',
        'last_login_ip' =>  'varchar',
        'telegram' =>  'varchar',
        'whats_app' =>  'varchar',
        'balance' =>  'decimal',
        'daily_max_recharge' =>  'int',
        'commission' => 'int',
        'status' => 'tinyint',
        'isopen' => 'tinyint',
        'sort' => 'tinyint',
        'last_login_time' =>  'datetime',
        'create_time' =>  'datetime',
        'update_time' =>  'datetime',
        'delete_time' =>  'datetime',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'username'=>lang('账号'),
            'password'=>lang('密码'),
            'realname'=>lang('真实姓名'),
            'nickname'=>lang('昵称'),
            'balance'=>lang('余额'),
            'sort'=>lang('排序'),
            'status'=>lang('状态'),
        ];
        $set_key = [
            ['status'=>[0=>lang('禁用'),1=>lang('开启')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}