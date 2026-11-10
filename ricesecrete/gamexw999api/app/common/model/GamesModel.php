<?php
namespace app\common\model;
use think\Model;
class GamesModel extends Model
{
    protected $name = 'games';
    protected $pk = 'g_id';
    protected $schema = [
        'g_id'          => 'int',
        'g_tcid' =>  'int',
        'g_web_tcid' =>  'int',
        'g_code' =>  'varchar',
        'g_tag' =>  'varchar',
        'g_icon' =>  'varchar',
        'g_state' =>  'int',
        'g_type' =>  'int',
        'g_sort' =>  'int',
        'g_hot' =>  'tinyint',
        'g_sports' =>  'tinyint',
        'g_exfield' =>  'varchar',
        'g_keyword' =>  'varchar',
        'g_is_new' =>  'tinyint',
        'g_is_optimum' =>  'tinyint',
        'g_backstatus' =>  'int',
        'g_fystatus' =>  'int',
        'g_lastcounttime' =>  'int',
        'g_thismaxcounttime' =>  'int',
        'g_rtp' =>  'int',
        'g_show' =>  'tinyint',
        'label'=>'varchar',
        'g_cj' =>  'tinyint',
        'g_lock' =>  'tinyint',
    ];



}