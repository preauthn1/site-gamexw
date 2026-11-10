<?php
namespace app\common\model;
use think\Model;
class FlyerOrderModel extends Model
{
    protected $name = 'flyer_order';
    protected $schema = [
        'id'      => 'int',
        'type' =>  'tinyint',
        'uid' =>  'int',
        'username' =>  'varchar',
        'lebal' =>  'tinyint',
        'channel' =>  'varchar',
        'order_id' =>  'varchar',
        'money' =>  'decimal',
        'is_full' =>  'tinyint',
        'add_time' =>  'bigint',
        'game_name' =>  'varchar',
    ];
}