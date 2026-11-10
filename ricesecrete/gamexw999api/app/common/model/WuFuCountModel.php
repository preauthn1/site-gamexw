<?php

namespace app\common\model;

use think\Model;

class WuFuCountModel extends Model{

    protected $name = 'wufu_count';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'uid' => 'int',
        'type' => 'tinyint',
        'use_type' => 'tinyint',
        'w_id' => 'int',
        'card_id' => 'int',
        'invite_uid' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    const TYPE_INVITE = 0;#邀请获得
    const TYPE_FIRST_RECHARGE = 1;#首次充值获得
    const TYPE_FIRST_GAME = 2;#首次游戏获得
    const TYPE_REGISTER = 3;#注册获得
    const TYPE_TASK = 4;#任务获得
    const TYPE_DOWNLOAD_APP = 5;#下载app

    const USE_TYPE_NO = 0;#未使用
    const USE_TYPE_ONE = 1;#单张使用
    const USE_TYPE_ALL = 2;#集体使用

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'type'=>lang('类型（0邀请获得，1首次充值获得，2玩游戏获得，3注册获得，4任务获得，5下载APP登录获得）'),
            'w_id'=>lang('五福id'),
            'card_id'=>lang('卡片id'),
            'invite_uid'=>lang('邀请用户'),
            'use_type'=>lang('使用类型'),
        ];
        $set_key = [
            ['type'=>[0=>lang('邀请获得'), 1=>lang('首次充值获得'), 2=>lang('玩游戏获得'), 3=>lang('注册获得'), 4=>lang('任务获得'), 5=>lang('下载APP登录获得')],],
            ['use_type'=>[0=>lang('未使用'), 1=>lang('单张使用'), 2=>lang('集体使用')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}