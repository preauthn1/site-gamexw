<?php

namespace app\common\model;

use think\Model;

class UserLogModel extends Model {

    protected $name = 'user_log';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    //奖励类型
    const ONLINE_TIME_TYPE = 'online_time';#在线时长
    const GAME_TIME_TYPE = 'game_time';#游戏时长
    //获取奖励类型数组
    public static function getRewardTypeArr(){
        return [
            self::ONLINE_TIME_TYPE,#在线时长
            self::GAME_TIME_TYPE,#游戏时长
        ];
    }


    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'int',
        'type'                      => 'varchar',
        'data'                      => 'text',
        'datetime'                  => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('标题'),
            'type'=>lang('类型'),
            'data'=>lang('数据'),
            'datetime'=>lang('记录时间'),
        ];
        $set_key = [
            ['type'=>['online'=>lang('在线时间'), 'game_time'=>lang('游戏时间')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}