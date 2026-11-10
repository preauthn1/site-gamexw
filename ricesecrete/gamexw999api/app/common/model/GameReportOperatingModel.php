<?php
namespace app\common\model;
use think\Model;
class GameReportOperatingModel extends Model
{
	protected $name = 'game_report_operating';
    protected $schema = [
        'id'          => 'int',
        'bets_number' =>  'decimal',
        'bets_total' =>  'decimal',
        'pay_total' =>  'decimal',
        'user_total_loss' =>  'decimal',
        'platform_total_lose' =>  'decimal',
        'channel' =>  'varchar',
        'game_name' =>  'varchar',
        'add_time' =>  'int',
        'day_time' =>  'int',
        'game_code' =>  'varchar',
    ];

}