<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class AnalystModel extends Model
{
	protected $name = 'analyst';
	protected $schema = [
        'id'          => 'int',
        'name' =>  'varchar',
        'birthday' =>  'varchar',
        'position' =>  'varchar',
        'accuracy' =>  'int',
        'head' =>  'varchar',
        'intro' =>  'text',
        'status' =>  'tinyint',
    ];

}