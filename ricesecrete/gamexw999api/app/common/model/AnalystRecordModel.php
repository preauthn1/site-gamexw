<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class AnalystRecordModel extends Model
{
	protected $name = 'analyst_record';
	protected $schema = [
        'id'          => 'int',
        'aid' =>  'int',
        'sc_id' =>  'int',
        'type' =>  'tinyint',
        'indemnity' =>  'tinyint',
        'bd_id' => 'int',
        'odds' =>  'decimal',
        'status' =>  'tinyint',
    ];

}