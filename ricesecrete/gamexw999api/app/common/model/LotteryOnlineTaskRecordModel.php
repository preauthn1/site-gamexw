<?php

namespace app\common\model;

use think\Model;

class LotteryOnlineTaskRecordModel extends Model{

    protected $name = 'lottery_online_task_record';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'task_id'                   => 'int',
        'type'                      => 'tinyint',
        'receive_num'               => 'tinyint',
        'receive_amount'            => 'decimal',
        'receive_integral'          => 'decimal',
        'uid'                       => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('类型（0每天，1每周，2每月）'),
            'task_id'=>lang('任务id'),
            'receive_num'=>lang('领取数量'),
            'receive_amount'=>lang('获得金额'),
            'receive_integral'=>lang('获得积分'),
            'uid'=>lang('参与标签限制'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [
            ['type'=>[0=>lang('每天'), 1=>lang('每周'), 2=>lang('每月')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}