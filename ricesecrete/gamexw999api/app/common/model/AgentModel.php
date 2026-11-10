<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class AgentModel extends Model
{
    protected $name = 'agent';
    protected $schema = [
        'id'          => 'int',
        'username'  =>  'varchar',
        'rec_code'  =>  'int',
        'lebal' => 'int',
        'status' => 'int',
        'money' => 'decimal',
        'remark' => 'varchar'
    ];

}