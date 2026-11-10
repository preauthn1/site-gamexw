<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class AgentSillModel extends Model
{
    protected $name = 'agent_sill';
    protected $schema = [
        'id'          => 'int',
        'sill_name'  =>  'varchar',
        'param'  =>  'int',
        'remark'  =>  'int',
    ];

}