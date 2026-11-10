<?php
namespace app\common\model;
use think\Model;
class LangItemDetailModel extends Model
{
    protected $name = 'lang_item_detail';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'          => 'int',
        'lang_id' => 'int',
        'item_id' => 'int',
        'text' => 'text',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang_id'=>lang('语言'),
            'item_id'=>lang('类目'),
            'text'=>lang('内容'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }


}