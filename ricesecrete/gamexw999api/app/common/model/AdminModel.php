<?php
namespace app\common\model;

use think\Model;

class AdminModel extends Model
{
    protected $name = 'admin';
    protected $schema = [
        'id'          => 'int',
        'parent_id'  =>  'int',
        'username'  =>  'varchar',
        'password' =>  'varchar',
        'name' =>  'varchar',
        'role_id' =>  'int',
        'limit_ip' => 'varchar',
        'google_secret' => 'varchar',
        'goodsle_bind' => 'tinyint',
        'google_check' => 'tinyint',
        'status' => 'int',
        'usertokenkey' => 'varchar',
        'usertoken' => 'text',
        'perms' => 'varchar',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('管理员姓名'),
            'username'=>lang('管理员账号'),
            'role_id'=>lang('系统角色'),
            'limit_ip'=>lang('登录IP限制'),
            'password'=>lang('密码'),
            'google_check'=>lang('google 验证'),
            'reset_google'=>lang('重置google密钥'),
            'status'=>lang('状态'),
        ];
        $set_key = [
            ['google_check'=>[0=>lang('关闭'),1=>lang('启用')]],
            ['reset_google'=>[0=>lang('关闭'),1=>lang('启用')]],
            ['status'=>[0=>lang('禁用'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public function adminRole(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(AdminRoleModel::class, 'role_id', 'id');
    }

    //递归获取单条线下的所有管理员
    public function adminChildren(): \think\model\relation\HasMany
    {

        //以 id 为主 去查parent_id 为id 的 （查下级）
        return $this->hasMany(self::class,'parent_id','id')->with('adminChildren')->field('id, parent_id');
    }
    public $num = 0;
    /*递归获取当前用户的上级 如果超过 5 就不能再添加管理员  manage -> admin -> a->b->c*/
    public function adminParent(): \think\model\relation\HasOne
    {
        //以 parent_id 为主 去查id 为parent_id 的 （查上级）
        return $this->hasOne(self::class,'id','parent_id')
                    ->with('adminParent')
                    ->field('id, parent_id');
    }


}