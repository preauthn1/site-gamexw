<?php
namespace app\common\model;
use think\Model;
class GameInfosModel extends Model
{
    protected $name = 'game_infos';
    protected $schema = [
        'gi_id'          => 'int',
        'gi_gid' =>  'int',
        'gi_lang' =>  'varchar',
        'gi_thirdlang' =>  'varchar',
        'gi_name' =>  'varchar',
        'gi_info' =>  'varchar',
        'gi_headimg' =>  'varchar',
        'gi_content' =>  'text',
    ];
}