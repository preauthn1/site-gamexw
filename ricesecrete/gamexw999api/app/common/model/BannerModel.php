<?php
namespace app\common\model;
use think\Model;
class BannerModel extends Model
{
	protected $name = 'banner';
	protected $schema = [
        'id'      => 'int',
        'lang'  =>  'varchar',
        'img'  =>  'varchar',
        'mobile_img'  =>  'varchar',
        'type'  =>  'tinyint',
        'url'  =>  'varchar',
        'jump_url'  =>  'varchar',
        'start_time'  =>  'int',
        'end_time'  =>  'int',
        'agent_id'  =>  'varchar',
        'status'  =>  'tinyint',
        'show_position'  =>  'tinyint',
        'sort'  =>  'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('公告语种'),
            'img'=>lang('封面图片'),
            'mobile_img'=>lang('手机端图片'),
            'url'=>lang('超链接'),
            'start_time'=>lang('开始时间'),
            'end_time'=>lang('结束时间'),
//            'type'=>lang('类型'),
            'status'=>lang('状态'),
            'show_position'=>lang('显示位置'),
            'sort'=>lang('排序'),
            'agent_id'=>lang('代理id'),
        ];
        $set_key = [
//            ['type'=>[0=>lang('PC端'),1=>lang('手机端')]],
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
            ['show_position'=>[1=>lang('首页'),2=>lang('游戏'),3=>lang('体育')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}