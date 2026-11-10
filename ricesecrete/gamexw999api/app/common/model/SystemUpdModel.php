<?php
namespace app\common\model;
use think\Model;
class SystemUpdModel extends Model
{
	protected $name = 'system_upd';
	protected $schema = [
        'id'          => 'int',
        'start_time'  =>  'bigint',
        'end_time'  =>  'bigint',
        'admin_name'  =>  'bigint',
        'status'  =>  'int',
        'title'  =>  'varchar',
        'content'  =>  'text',
    ];
    
}