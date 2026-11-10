<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
use think\model\concern\SoftDelete;
class UserBankModel extends Model
{   
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $name = 'user_bank';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'bank_type'          => 'tinyint',
        'bank_id'          => 'int',
        'bank_name'          => 'varchar',
        'bank_number'          => 'varchar',
        'bank_open'          => 'varchar',
        'bank_hm'          => 'varchar',
        'province'          => 'varchar',
        'city'          => 'varchar',
        'remarks'          => 'varchar',    
        'financial'          => 'varchar',
        'add_time'          => 'bigint',
        'delete_time'          => 'bigint',
        'currency_id'          => 'int',
        'branches'          => 'varchar',
        'branches_code'          => 'varchar',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'bank_hm'=>lang('开户名/usdt链接名/钱包名称'),
            'bank_name'=>lang('银行名称/usdt链接名/钱包名称'),
            'bank_number'=>lang('银行卡号/usdt收款地址/收款地址'),
            'bank_open'=>lang('支行信息/U二维码链接/收款二维码'),
            'currency_id'=>lang('货币'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }

    public function currency(): \think\model\relation\belongsTo
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id', 'id')->bind(['country_code','currency_withdrawal_rate'=>'withdrawal_rate']);
    }

    public function currencyApi(): \think\model\relation\belongsTo
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id', 'id')->withoutField('create_time,update_time');
    }
}