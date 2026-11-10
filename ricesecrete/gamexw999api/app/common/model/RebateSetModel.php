<?php
namespace app\common\model;
use think\Model;
class RebateSetModel extends Model
{
	protected $name = 'rebate_set';
    protected $schema = [
        'id'          => 'int',
        'ps_id'          => 'int',
        'min'          => 'decimal',
        'max'          => 'decimal',
        'num_limit'          => 'tinyint',
        'water'          => 'tinyint',
        'day'          => 'tinyint',
        'proportion' =>  'decimal',
        'create_time' =>  'int',
        'update_time' =>  'int',
    ];
}