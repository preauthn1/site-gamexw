<?php
namespace app\common\model;

use think\Model;

class UserLevelDownRecordModel extends Model{

    protected $name = 'user_level_down_record';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const WEEK_REWARD_TYPE = 1;
    const MONTH_REWARD_TYPE = 2;


    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'int',
        'level_id'                  => 'int',
        'old_level_id'              => 'int',
        'time'                      => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户ID'),
            'level_id'=>lang('当前VIP等级（降级后）'),
            'old_level_id'=>lang('原来VIP等级（降级前）'),
            'time'=>lang('降级时间'),
        ];
        $set_key = [
            
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}