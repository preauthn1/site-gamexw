<?php
namespace app\common\model;
use think\Model;
class GameOperationModel extends Model
{
	protected $name = 'game_operation';
    protected $schema = [
        'id'          => 'int',
        'game_id' =>  'int',
        'operation_id' =>  'text',
        'action' =>  'text',
        'message' =>  'text',
        'add_time' =>  'int',
    ];
}