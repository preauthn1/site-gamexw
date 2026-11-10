<?php

namespace app\common\model;

use think\facade\Session;
use think\Model;
use function foo\func;

class AdminNodeOldModel extends Model
{
    protected $name = 'admin_node_old';

    protected $schema = [
        'id'          => 'int',
        'node_name'  =>  'varchar',
        'node_url' => 'varchar',
        'path' => 'varchar',
        'name' => 'varchar',
        'pid'  =>  'int',
        'icon'  =>  'varchar',
        'sort'  =>  'tinyint',
        'is_admin'  =>  'tinyint',
        'not'  =>  'tinyint',
        'special_menu'  =>  'varchar',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    /*只为了获取下面的单个数据 用来判断 如果存在数据 则不显示删除按钮*/
    public function childrenOne(): \think\model\relation\HasOne
    {
        return $this->hasone(self::class, 'pid', 'id')->bind(['cid' => 'id']);
    }



    public function parent(): \think\model\relation\HasOne
    {
        return $this->hasone(self::class, 'id', 'pid');
    }


    /*获取菜单节点 因为只存在一级菜单 所以只获取一级*/
    public function menuNode()
    {
        return $this->hasMany(self::class, 'pid', 'id')
                    ->order('sort ASC, id DESC')
                    ->field('node_name,icon,id,pid,not,node_url,sort,path,name');
    }


    /*递归获取树状节点*/
    public function children(): \think\model\relation\hasMany
    {
        return $this->hasMany(self::class, 'pid','id')
            ->with('children')
            ->withSearch(['id']);
    }

    /*id搜索器 限制获取当前用户已有的权限 如果当前是修改的话 判断当前的角色有没有某个权限节点 赋值选中*/
    public function searchIdAttr($query){
        $role_node_data = Session::get('role_node_data');
        return $query
            //当前用户已经拥有的权限  如果是系统管理员 就获取全部
            ->when($role_node_data['admin_role_id'] > 1, function($query) use ($role_node_data){
                $query->where('id', 'in', $role_node_data['role_permission']);
            })
            //修改角色的时候 角色已经拥有的节点 选中
            ->withAttr('checked', function($value,$data) use ($role_node_data){
                return in_array($data['id'], $role_node_data['role_node_arr'] );
            })
            ->withAttr('title', function($value,$data){
                return lang($value);
            })
            //追加一个选中的属性
            ->append(['checked'])
            //不允许给其他用户的权限节点 比如权限规格
            ->where(['not'=>0])
            //去掉不展示的菜单
            ->where('special_menu','in',$role_node_data['NodeShowArr'])
            ->order('sort desc, id')
            ->field('id, pid, node_name as title');
    }



}