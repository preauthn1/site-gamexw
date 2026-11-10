<?php
namespace app\common\model;
use think\Model;
class GameUserModel extends Model
{
	protected $name = 'game_user';
    protected $schema = [
        'id'          => 'int',
        'game_id' =>  'int',
        'uid' =>  'int',
        'member_id' =>  'text',
        'username' =>  'text',
        'min_transfer' =>  'decimal',
        'max_transfer' =>  'decimal',
        'amount' =>  'decimal',
        'add_time' =>  'bigint',
        'online' =>  'int',
        'currency' =>  'int',
        'odds_type' =>  'int',
        'last_login_time' =>  'int',
    ];
}