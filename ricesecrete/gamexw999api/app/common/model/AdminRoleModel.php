<?php
namespace app\common\model;

use think\Model;

class AdminRoleModel extends Model
{
	protected $name = 'admin_role';
	protected $schema = [
        'id'          => 'int',
        'role_name'  =>  'varchar',
        'role_permission'  =>  'text',
        'admin_id'  =>  'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'role_name'=>lang('角色名称'),
            'role_permission'=>lang('权限列表'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public function roleNode(): \think\model\relation\HasMany
    {
        return $this->hasMany(AdminRoleNodeModel::class, 'role_id','id');
    }


}