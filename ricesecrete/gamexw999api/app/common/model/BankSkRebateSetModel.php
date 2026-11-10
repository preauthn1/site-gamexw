<?php
namespace app\common\model;
use think\Model;
class BankSkRebateSetModel extends Model
{
	protected $name = 'bank_sk_rebate_set';
    protected $schema = [
        'id'          => 'int',
        'bank_sk_id'          => 'int',
        'min'          => 'decimal',
        'max'          => 'decimal',
        'proportion' =>  'decimal',
        'create_time' =>  'int',
        'update_time' =>  'int',
        'num_limit'          => 'tinyint',
        'water'          => 'tinyint',
        'day'          => 'tinyint',
    ];
}