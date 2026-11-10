<?php
namespace app\common\model;
use think\Model;
class AdminPermissionModel extends Model
{
	protected $name = 'admin_permission';
	protected $schema = [
        'id'          => 'int',
        'permission_name'  =>  'varchar',
        'permission_url'  =>  'varchar',
        'pid'  =>  'int',
    ];
    
}