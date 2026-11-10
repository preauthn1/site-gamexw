<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class GameBetsTimeModel extends Model
{
	protected $name = 'game_bets_time';
	protected $schema = [
        'ts'            => 'int',
        'gb_intime_mingbid'         => 'int',
        'gb_intime_maxgbid'      => 'int',
        'gb_bettime_mingbid'      => 'int',
        'gb_bettime_maxgbid'      => 'int',
        'gb_overtime_mingbid'      => 'int',
        'gb_overtime_maxgbid'      => 'int',
    ];
}