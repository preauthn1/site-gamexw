<?php
namespace app\common\model;
use think\Model;
class TroniModel extends Model
{
    protected $name = 'troni';
    protected $schema = [
        'id'          => 'int',
        'uid'  =>  'int',
        'address'  =>  'varchar',
        'money' =>  'decimal',
        'add_time'  =>  'int',
        'update_time'  =>  'int',
    ];

}