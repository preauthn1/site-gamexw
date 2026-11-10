<?php
namespace app\common\model;
use think\model\Pivot;

class JournalismGameModel extends Pivot
{
	protected $name = 'journalism_game';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
	protected $schema = [
        'id'          => 'int',
        'journalism_id'  =>  'int',
        'game_id'  =>  'int',
        'create_time'=> 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'journalism_id'=>lang('新闻id'),
            'game_id'=>lang('游戏id'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public function game(){
        return $this->hasOne(GameInfosModel::class, 'gi_gid', 'game_id');
    }
    public function game1(){
        return $this->hasOne(GamesModel::class, 'g_id', 'game_id');
    }


}