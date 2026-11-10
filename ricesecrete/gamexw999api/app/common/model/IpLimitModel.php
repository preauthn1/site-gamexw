<?php
namespace app\common\model;
use think\Model;
class IpLimitModel extends Model
{
	protected $name = 'ip_limit';
	protected $schema = [
        'id'          => 'int',
        'add_time'  =>  'bigint',
        'update_time'  =>  'bigint',
        'ip'  =>  'bigint',
        'admin_status'  =>  'int',
        'api_status'  =>  'int',
        'remarks'  =>  'varchar',
        'admin_name'  =>  'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'ip'=>'ip',
            'remarks'=>lang('备注'),
            'admin_status'=>lang('后台状态'),
            'api_status'=>lang('前端状态'),
        ];
        $set_key = [
            ['admin_status'=>[0=>lang('允许访问'), 1=>lang('限制访问'), -1 => lang('不限制')]],
            ['api_status'=>[0=>lang('允许访问'), 1=>lang('限制访问'), -1 => lang('不限制')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}