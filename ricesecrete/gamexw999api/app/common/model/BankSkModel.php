<?php
namespace app\common\model;
use think\Model;
use think\model\concern\SoftDelete;
class BankSkModel extends Model
{   
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

	protected $name = 'bank_sk';
	protected $schema = [
        'id'          => 'int',
        'type'          => 'tinyint',
        'bank_name'  =>  'varchar',
        'bank_number'  =>  'varchar',
        'bank_khm'  =>  'varchar',
        'bank_open'  =>  'varchar',
        'bank_remarks'  =>  'varchar',
        'bank_qrcode'  =>  'varchar',
        'min_amcount'  =>  'decimal',
        'max_amcount'  =>  'decimal',
        'recharge_rate'  =>  'decimal',
        'withdrawal_rate'  =>  'decimal',
        'bank_sort'  =>  'int',
        'bank_status'  =>  'int',
        'agent_id' => 'int',
        'financial'  =>  'varchar',
        'add_time'  =>  'bigint',
        'delete_time' => 'bigint',
        'new_user' => 'tinyint',
        'currency_id' => 'int',
        'is_all' => 'tinyint',
        'is_hot' => 'tinyint',
        'start_level_id' => 'int',
        'end_level_id' => 'int',
        'icon' => 'varchar',
        'pay_types' => 'int',
        'open_start_time' => 'int',
        'open_end_time' => 'int',
        'rebate_agent_id' => 'varchar',
        'is_consist' => 'tinyint',
        'tag_id' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'bank_name'=>lang('银行名称'),
            'bank_number'=>lang('银行卡号'),
            'bank_khm'=>lang('开户者姓名'),
            'bank_open'=>lang('开户支行'),
            'agent_id'=>lang('代理线'),
            'min_amcount'=>lang('最低充值'),
            'max_amcount'=>lang('最高充值'),
            'bank_sort'=>lang('排序'),
            'bank_remarks'=>lang('备注'),
            'bank_status'=>lang('状态'),
            'new_user'=>lang('新老用户'),
            'financial'=>lang('金融编号'),
            'currency_id'=>lang('货币'),
            'is_all'=>lang('通用渠道'),
            'pay_types' => lang('平台渠道类型'),
            'open_start_end_time'=>lang('充值开放/结束时间'),
        ];
        $set_key = [
            ['bank_status'=>[0=>'禁用',1=>'启用']],
            ['new_user'=>[0=>lang('不限制'),1=>lang('新用户'),2=>lang('老用户')]],
            ['is_all'=>[2=>lang('否'),1=>lang('是')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public function currency(): \think\model\relation\belongsTo
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id', 'id')->bind(['country_code','currency_recharge_rate'=>'recharge_rate']);
    }

    public function currencyApi(): \think\model\relation\belongsTo
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id', 'id')->withoutField('create_time,update_time');
    }

    //获取随机渠道支付
    public static function get_rand_pay($apply_price, $map=[]){
        $pay_type = self::where(["bank_status"=>1])->where($map)->orderRaw("rand()")->find();//随机渠道
        return $pay_type;
    }
}