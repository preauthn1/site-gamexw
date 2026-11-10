<?php

namespace app\common\model;

use think\Model;

class UserOnlineModel extends Model{
    protected $name = 'user_online';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'int',
        'socket_uid'                => 'varchar',
        'online_time'               => 'int',
        'offline_time'              => 'int',
        'time'                      => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户uid'),
            'socket_uid'=>lang('socket uid'),
            'online_time'=>lang('上线时间'),
            'offline_time'=>lang('下线时间'),
            'time'=>lang('在线时长'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}