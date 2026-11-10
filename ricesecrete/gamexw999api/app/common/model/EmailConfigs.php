<?php
namespace app\common\model;
use think\facade\Config;
use think\Model;
class EmailConfigs extends Model
{
    protected $name = 'email_configs';
    protected $schema = [
        'id'          => 'int',
        'sms_name'  =>  'varchar',
        'sms_iden'  =>  'varchar',
        'sms_content'  =>  'text',
        'sms_status'  =>  'tinyint',
        'is_del'  =>  'tinyint',
        'notify_status'  =>  'tinyint',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id' => 'id',
            'sms_name' => lang('名称'),
            'sms_iden' => lang('标识'),
            'sms_content' => lang('参数内容'),
            'sms_status' => lang('状态'),
            'notify_status' => lang('通知邮件状态'),
        ];
        $set_key = [
            ['sms_status' => [0 => lang('关闭'), 1 => lang('开')]],
            ['notify_status' => [2 => lang('关闭'), 1 => lang('开')]],
        ];
        return ['field'=>$field, 'set_key'=>$set_key, 'table'=>$this->name];
    }

    //获取单条配置
    public static function GetOne($wh = []){
        $where = [['is_del','=',0],['sms_status','=',1]];
        $sms = self::where($where)->field('sms_iden,sms_content')->where($wh)->orderRaw("rand()")->find();
        if( !$sms ){
            return '';
        }
        $sms_content = $sms['sms_content'] ? json_decode($sms['sms_content'],true):[];
        $configs = [];
        foreach($sms_content as $k=>$v){
            $configs[$v['key_name']] = $v['key_value'];
        }
        return $sms ? ['iden'=>$sms['sms_iden'],'configs'=>$configs]:"";
    }

}