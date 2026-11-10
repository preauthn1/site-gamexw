<?php

namespace app\common\model;

use think\Model;

class UserCollectModel extends Model{

    protected $name = 'user_collect';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const GAME_TYPE = 0;#游戏类型

    //类型列表
    public static function getTypeList(){
        return [
            self::GAME_TYPE, #游戏
        ];
    }

    protected $schema = [
        'id'            => 'int',
        'type'          => 'tinyint',
        'user_id'       => 'int',
        'other_id'      => 'int',
        'other_data'    => 'text',
        'create_time'   => 'int',
        'update_time'   => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('类型（0游戏，其他待定）'),
            'user_id'=>lang('用户id'),
            'other_id'=>lang('类型对应id（0对应游戏id）'),
            'other_data'=>lang('对应数据快照'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }

    //获取redis缓存key
    public static function getRedisKey($uid, $type, $otherId){
        return 'user_collect_'.$uid.'_'.$type.'_'.$otherId;
    }


}