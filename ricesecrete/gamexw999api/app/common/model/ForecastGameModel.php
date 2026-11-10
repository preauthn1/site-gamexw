<?php

namespace app\common\model;

use think\Model;

class ForecastGameModel extends Model {

    protected $name = 'forecast_game';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'            =>  'int',
        'forecast_id'   =>  'int',
        'tcid'          =>  'int',
        'gid'           =>  'int',
        'time'          =>  'int',
        'sort'          =>  'tinyint',
        'create_time'   =>  'int',
        'update_time'   =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'forecast_id'=>lang('预测id'),
            'tcid'=>lang('平台id'),
            'gid'=>lang('游戏id'),
            'time'=>lang('时间周期（单位：分钟）'),
            'sort'=>lang('排序'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }



}