<?php
namespace app\common\model;
use think\Model;
class UserMusicModel extends Model
{
    protected $name = 'user_music';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';

    protected $schema = [
        'id'          => 'int',
        'type' => 'tinyint',
        'uid'  =>  'varchar',
        'music_id'  =>  'decimal',
        'count'  =>  'int',
        'create_time'  =>  'int',
    ];

    const TYPE_DOWNLOAD = 0;#下载
    const TYPE_PLAY = 1;#播放

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('类型'),
            'uid'=>lang('用户uid'),
            'music_id'=>lang('音乐id'),
            'count'=>lang('对应数量'),
        ];
        $set_key = [
            ['type'=>[0=>lang('下载'),1=>lang('播放')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}