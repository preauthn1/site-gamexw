<?php

namespace app\common\model;

use think\Model;

class UserAgentLangItemModel extends Model {

    protected $name = 'user_agent_website_lang_item';

    protected $scene = [
        'id'                        => 'int',
        'lang_id'                   => 'int',
        'item_id'                   => 'int',
        'text'                      => 'text',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang_id'=>lang('语言id'),
            'item_id'=>lang('字段id'),
            'text'=>lang('翻译'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }


    public function getText(): \think\model\relation\HasOne
    {
        return $this->hasOne(LangItemDetailModel::class, 'item_id','id')->bind(['text']);
    }
}