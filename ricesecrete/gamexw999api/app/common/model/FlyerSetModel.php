<?php
namespace app\common\model;
use think\Model;
class FlyerSetModel extends Model
{
	protected $name = 'flyer_set';
    protected $schema = [
        'id'          => 'int',
        'name' =>  'varchar',
        'channel' =>  'varchar',
        'key' =>  'varchar',
        'status' =>  'int',
        'exchange_rate' =>  'decimal',
        'currency' =>  'varchar',
        'api_url' =>  'varchar',
    ];

}