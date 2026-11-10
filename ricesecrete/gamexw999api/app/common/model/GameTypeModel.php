<?php
namespace app\common\model;
use think\Model;
class GameTypeModel extends Model
{
	protected $name = 'game_type';

    protected $schema = [
        'id'          => 'int',
        'name' =>  'varchar',
        'icon' =>  'varchar',
        'default_icon' =>  'varchar',
        'sort' =>  'tinyint',
        'state' =>  'tinyint',
        'create_time' =>  'int',
        'update_time' =>  'int',
        'min_amount' =>  'decimal',
        'max_amount' =>  'decimal',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id' => 'id',
            'name' => lang('游戏分类名称'),
            'sort' => lang('排序'),
            'create_time' => lang('添加时间'),
            'update_time' => lang('修改时间'),
            'state' => lang('状态'),
            'set_rate' => lang('设置流水扣减比例'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }






}