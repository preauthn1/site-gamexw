<?php
namespace app\common\model;
use think\Model;
class MusicModel extends Model
{
    protected $name = 'music';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'name' => 'varchar',
        'file'  =>  'varchar',
        'size'  =>  'decimal',
        'download_num'  =>  'int',
        'play_num'  =>  'int',
        'status'  =>  'tinyint',
        'sort'  =>  'int',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'file'=>lang('文件'),
            'size'=>lang('大小（单位：KB）'),
            'download_num'=>lang('下载数量'),
            'play_num'=>lang('播放数量'),
            'status'=>lang('状态'),
            'sort'=>lang('排序'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}