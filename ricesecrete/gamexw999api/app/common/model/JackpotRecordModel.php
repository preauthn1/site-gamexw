<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class JackpotRecordModel extends Model
{
    protected $name = 'jackpot_record';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'price'  =>  'decimal',
        'proportion' =>  'decimal',
        'type' =>  'tinyint',
        'ranking' =>  'int',
        'day' =>  'int',
        'create_time' =>  'bigint',
    ];

}