<?php
namespace app\common\model;
use think\Model;
class GameTicketModel extends Model
{
	protected $name = 'game_ticket';
    protected $schema = [
        'id'      => 'int',
        'ref_id' =>  'text',
        'away_id' => 'bigint',
        'away_name' => 'text',
        'away_name_en' => 'text',
        'away_score' => 'int',
        'ba_status' => 'int',
        'bet_choice' => 'text',
        'bet_choice_en' => 'text',
        'bet_team' => 'text',
        'bet_type' => 'int',
        'bet_type_name' => 'text',
        'bet_type_name_en' => 'text',
        'excluding' => 'text',
        'home_id' => 'bigint',
        'home_name' => 'text',
        'home_name_en' => 'text',
        'home_score' => 'int',
        'is_live' => 'int',
        'kick_off_time' => 'bigint',
        'league_id' => 'bigint',
        'league_name' => 'text',
        'league_name_en' => 'text',
        'match_date_time' => 'bigint',
        'match_id' => 'bigint',
        'odds' => 'decimal',
        'odds_id' => 'bigint',
        'odds_type' => 'int',
        'point' => 'text',
        'point2' => 'text',
        'sport_type' => 'int',
        'sport_type_name' => 'text',
        'sport_type_name_en' => 'text'
    ];
}