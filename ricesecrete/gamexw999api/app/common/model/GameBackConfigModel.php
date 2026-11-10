<?php
namespace app\common\model;
use think\Model;
class GameBackConfigModel extends Model
{
    protected $name = 'game_back_config';
    protected $schema = [
        'gbc_id'          => 'int',
        'gbc_gtid' =>  'int',
        'gbc_viplvl' =>  'int',
        'gbc_rate' =>  'decimal',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'gbc_id' => 'id',
            'gbc_gtid' => lang('游戏分类id'),
            'gbc_viplvl' => lang('VIP等级id'),
            'gbc_rate' => lang('返水比例'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }
}