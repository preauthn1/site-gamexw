<?php
namespace app\common\model;
use think\Model;
class UserWithdrawalModel extends Model
{
	protected $name = 'user_withdrawal';
	protected $schema = [
        'id'          => 'int',
        'order_sn'  =>  'varchar',
        'channel'  =>  'tinyint',
        'uid'  =>  'int',
        'bank_id'  =>  'int',
        'w_price'  =>  'decimal',
        'method_id' => 'int',
        'arrival_price'  =>  'decimal',
        'arrival_prices'  =>  'decimal',
        'w_service'  =>  'decimal',
        'current_currency'  =>  'varchar',
        'arrival_currency'  =>  'varchar',
        'exchange_rate'  =>  'decimal',
        'w_remarks'  =>  'varchar',
        'user_remarks'  =>  'varchar',
        'status'  =>  'int',
        'lebal'  =>  'tinyint',
        'is_lock'  =>  'tinyint',
        'pay_order_sn'  =>  'varchar',
        'pay_time'  =>  'int',
        'pay_type'  =>  'varchar',
        'reason'  =>  'varchar',
        'operator'  =>  'varchar',
        'update_time'  =>  'bigint',
        'add_time'  =>  'bigint',
        'third_err_remarks'  =>  'varchar',
        'type' => 'int',
        'check_time'  =>  'bigint',
        'dec_cjbalance'  =>  'decimal',
        'dec_cjsavings'  =>  'decimal',
        'submit_third_price'  =>  'decimal',
    ];

    /*
     * 获取提现金额
     */
    public static function getWithdrawUserAmount($where=[], $where1=[]){
        $withdraw_price = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->sum("a.w_price");//线下提现
        $usdt_price = 0;
        $withdraw_amount = $withdraw_price + $usdt_price;
        return [$withdraw_price, $usdt_price, $withdraw_amount];
    }
    
    /*
     * 获取提现实际金额
     */
    public static function getWithdrawArrivalUserAmount($where=[], $where1=[]){
        $withdraw_price = self::alias("a")->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->sum("a.arrival_prices");//线下提现
        $usdt_price = 0;
        $withdraw_amount = $withdraw_price + $usdt_price;
        return [$withdraw_price, $usdt_price, $withdraw_amount];
    }
    /*
     * 获取提现人数
     */
    public static function getWithdrawUserCount($where=[], $where1=[]){
        $withdraw_price_num = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->group('uid')->count();
        $usdt_price_sum = 0;
        $count = $withdraw_price_num + $usdt_price_sum;
        return $count;
    }
    
    /*
     * 获取提现手续费
     */
    public static function getWithdrawFeeAmount($where=[],$where1=[]){
        $fee_price = self::alias("a")->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->sum("a.w_service");//线上提现
        $usdt_price = 0;
        $withdraw_fee = $fee_price + $usdt_price;
        return $withdraw_fee;
    }

    /*
     * 获取提现笔数
     */
    public static function getWithdrawCount($where=[]){
        $count = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->count();
        return $count;
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'uid'=>lang('用户id'),
            'price'=>lang('出款金额'),
            'status'=>lang('审核状态'),
            'w_remarks'=>lang('备注'),
            'mx_msg'=>lang('备注'),
            'pay_type'=>lang('三方渠道名'),
            'pay_order_sn'=>lang('银行code'),
            'is_lock'=>lang('锁定状态'),
            'dec_cjsavings'=>lang('扣除彩金流水'),
            'dec_cjbalance'=>lang('扣除彩金余额'),
        ];
        $set_key = [
            ['status'=>[1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过'),4=>lang('三方代付'),5=>lang('进行中')]],
            ['status'=>[0=>lang('解锁'),1=>lang('锁定')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //获取合计
    public static function get_count($wh = [],$wh1 = [],$wh2 = [],$group=''){
        return self::alias('a')->where($wh)->where($wh1)->where($wh2)->group($group)->count();
    }


}