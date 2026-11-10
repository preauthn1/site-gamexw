<?php
namespace app\common\model;
use think\Model;
class PaySetModel extends Model
{
	protected $name = 'pay_set';
	protected $schema = [
        'id'          => 'int',
        'pay_type' =>  'tinyint',
        'pay_sys_sign' =>  'varchar',
        'pay_name' =>  'varchar',
        'pay_status' =>  'tinyint',
        'pay_param' =>  'text',
        'pay_bank' =>  'text',
        'pay_field' =>  'text',
        'pay_min_amout' =>  'decimal',
        'pay_max_amout' =>  'decimal',
        'min_withdrawal_amount' =>  'decimal',
        'max_withdrawal_amount' =>  'decimal',
        'recharge_rate' =>  'decimal',
        'withdrawal_rate' =>  'decimal',
        'usdt_type' => 'varchar',
        'update_time' =>  'int',
        'isdel' =>  'tinyint',
        'is_withdra' => 'tinyint',
        'is_hot' => 'tinyint',
        'sort' => 'tinyint',
        'agent_id' => 'int',
        'new_user' => 'tinyint',
        'currency_id' => 'int',
        'is_all' => 'tinyint',
        'start_level_id' => 'int',
        'end_level_id' => 'int',
        'icon' => 'varchar',
        'remarks' => 'varchar',
        'pay_types' => 'int',
        'pay_off_field' => 'text',
        'rebate_agent_id' => 'varchar',
        'is_consist' => 'tinyint',
        'tag_id' => 'int',
    ];
    
    //获取配置秘钥信息
    public static function get_config($field){
        $where = ['isdel'=>0];
        if(is_numeric($field)){
            $where['id'] = $field;
        }else{
            $where['pay_sys_sign'] = $field;
        }
        
        $pay_set=self::where($where)->find();
        if(!$pay_set){echo 'channel does not exist';exit;}
        $pay_set['pay_param']=json_decode($pay_set['pay_param'],true);
        $pay_config=[];
        foreach ($pay_set['pay_param'] as $key => $value) {
            $pay_config[$value['key_name']]=$value['key_value'];
        }
        $pay_set['config']=$pay_config;
        return $pay_set;
    }

    //获取随机渠道支付
    public static function get_rand_pay($apply_price, $map=[]){
        $pay_type = self::where(["pay_status"=>1, "isdel" => 0])->with('currencyApi')->where($map)->orderRaw("rand()")->find();//随机渠道
        if (!isset($pay_type['id'])) {
            ajax_return("支付平台不存在", 0);
        }
       
        $pay_type['pay_param'] = json_decode($pay_type['pay_param'],true);
        $pay_config=[];
        
        foreach ($pay_type['pay_param'] as $key => $value) {
            $pay_config[$value['key_name']]=$value['key_value'];
        }
        $pay_type['config'] = $pay_config;
        return $pay_type;
    }
    
    //获取随机渠道代付付
    public static function get_rand_withdrawal($map=[]){
        $pay_type = self::where(["is_withdra"=>1, "isdel" => 0])->where($map)->orderRaw("rand()")->find();//随机渠道
        if (!isset($pay_type['id'])) {
            ajax_return("支付平台不存在", 0);
        }
       
        $pay_type['pay_param'] = json_decode($pay_type['pay_param'],true);
        $pay_config=[];
        
        foreach ($pay_type['pay_param'] as $key => $value) {
            $pay_config[$value['key_name']]=$value['key_value'];
        }
        $pay_type['config'] = $pay_config;
        return $pay_type;
    }
    
    //获取随机渠道支付
    public static function get_pay($map=[]){
        $pay_type = self::where(["isdel" => 0])->with('currencyApi')->where($map)->find();//指定渠道
        if (!isset($pay_type['id'])) {
            ajax_return("支付平台不存在", 0);
        }
       
        $pay_type['pay_param'] = json_decode($pay_type['pay_param'],true);
        $pay_config=[];
        
        foreach ($pay_type['pay_param'] as $key => $value) {
            $pay_config[$value['key_name']]=$value['key_value'];
        }
        $pay_type['config'] = $pay_config;
        return $pay_type;
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'pay_type'=>lang('渠道类型'),
            'pay_sys_sign'=>lang('支付标识'),
            'pay_name'=>lang('支付名称'),
            'pay_min_amout'=>lang('最低充值金额'),
            'pay_max_amout'=>lang('最高充值金额'),
            'min_withdrawal_amount'=>lang('最低代付'),
            'max_withdrawal_amount'=>lang('最高代付'),
            'usdt_type'=>lang('链接名称'),
            'sort'=>lang('排序'),
            'pay_status'=>lang('开启充值'),
            'is_withdra'=>lang('开启代付'),
            'pay_bank'=>lang('代付银行'),
            'pay_param'=>lang('参数设置'),
            'agent_id'=>lang('代理线'),
            'new_user'=>lang('新老用户'),
            'currency_id'=>lang('货币'),
            'is_all'=>lang('通用渠道'),
            'icon'=>lang('图标'),
            'remarks'=>lang('备注'),
            'is_hot' => lang('热门'),
            'pay_types' => lang('平台渠道类型'),
        ];
        $set_key = [
            ['pay_status'=>[0=>lang('关闭'),1=>lang('开启')]],
            ['is_withdra'=>[0=>lang('关闭'),1=>lang('开启')]],
            ['pay_type'=>[0=>lang('银行卡'),1=>'USDT']],
            ['new_user'=>[0=>lang('不限制'),1=>lang('新用户'),2=>lang('老用户')]],
            ['is_all'=>[2=>lang('否'),1=>lang('是')]],
            ['is_hot'=>[0=>lang('否'),1=>lang('是')]],
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

}