<?php
namespace app\common\model;
use think\Model;
class MonitorSetModel extends Model
{
    protected $name = 'monitor_set';
    protected $schema = [
        'id'          => 'int',
        'turnover'  =>  'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'turnover'=>lang('成交量预警'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }
}