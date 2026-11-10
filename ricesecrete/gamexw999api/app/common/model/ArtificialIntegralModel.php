<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
use app\common\model\UserModel;

class ArtificialIntegralModel extends Model
{
    protected $name = 'artificial_integral';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'lebal'          => 'tinyint',
        'type'          => 'tinyint',
        'admin_name'          => 'varchar',
        'old_integral'          => 'int',
        'new_integral'          => 'int',
        'integral'          => 'int',
        'msg'          => 'varchar',
        'add_time'          => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'uid'=>lang('用户id'),
            'type'=>lang('类型'),
            'integral'=>lang('调整积分'),
            'mx_msg'=>lang('备注'),
        ];
        $set_key = [
            ['type'=>[1=>lang('增加'),2=>lang('减少')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }



}