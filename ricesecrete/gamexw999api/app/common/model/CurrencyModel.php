<?php
namespace app\common\model;

use think\Model;

class CurrencyModel extends Model
{
    protected $name = 'currency';
    protected $schema = [
        'id'    => 'int',
        'type'  =>  'tinyint',
        'name'  =>  'varchar',
        'country_code'  =>  'varchar',
        'currency_code'  =>  'varchar',
        'symbol'  =>  'varchar',
        'symbol_img'  =>  'varchar',
        'recharge_rate' =>  'decimal',
        'withdrawal_rate' =>  'decimal',
        'link_name' =>  'varchar',
        'state' =>  'tinyint',
        'is_defa' =>  'tinyint',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    public function getField(): array
    {
        $field = [
            'id'=>'id',
            'type' => lang('货币类型'),
            'name' => lang('货币名称'),
            'country_code' => lang('国家代码'),
            'symbol'  =>  lang('货币符号'),
            'symbol_img'  =>  lang('货币符号图片'),
            'recharge_rate' => lang('充值汇率'),
            'withdrawal_rate' => lang('提现汇率'),
            'state' => lang('状态'),
            'link_name' => lang('链接名称'),
            'is_defa' => lang('默认语种'),
        ];
        $set_key = [
            ['state' => [ 2 =>lang('关闭'), 1 => lang('开启')]],
            ['is_defa' => [ 2 =>lang('关闭'), 1 => lang('开启')]],
            ['type' => [ 2 =>lang('法币'), 1 => lang('USDT')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    /**
     * @param $value
     * @return string
     */
    public function setLinkNameAttr($value)
    {
        return $value ? json_encode($value) : [];
    }

    /**
     * @param $value
     * @return array|mixed
     */
    public function getLinkNameAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * @param $value
     * @return array|string
     */
    public function getTypeAttr($value)
    {
        $type = [1 => 'USDT',2 => '法币'];
        return ['text' => $type[$value], 'val' => $value];
    }

}