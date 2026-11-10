<?php
namespace app\common\model;
use think\Model;
use app\common\model\LangItemDetailModel;

class LangItemsModel extends Model
{
    protected $name = 'lang_items';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'          => 'int',
        'title' => 'varchar',
        'is_lock' => 'tinyint',
        'is_public' => 'tinyint',
    ];
    
    public function detail()
    {
        return $this->hasMany(LangItemDetailModel::class, 'item_id');
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'title'=>lang('语言类目'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public function getText(): \think\model\relation\HasOne
    {
        return $this->hasOne(LangItemDetailModel::class, 'item_id','id')->bind(['text']);
    }

    public function getTextAgent(): \think\model\relation\HasOne
    {
        return $this->hasOne(UserAgentLangItemModel::class, 'item_id','id');
    }
}