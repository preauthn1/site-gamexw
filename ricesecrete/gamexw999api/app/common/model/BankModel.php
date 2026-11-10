<?php
namespace app\common\model;
use think\Model;
class BankModel extends Model
{
	protected $name = 'bank';
	protected $schema = [
        'id'          => 'int',
        'add_time'  =>  'bigint',
        'bank_name'  =>  'varchar',
        'bank_logo'  =>  'text',
    ];
    
}