<?php
namespace app\common\payment;
use app\api\factory\BotFactory;
use app\api\redis\ApiRedis;
use app\common\lib\EmailCode;
use app\common\lib\Util;
use app\common\model\AdvertisingModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\UserLevelModel;
use app\common\model\UserMsgModel;
use app\common\model\UserModel;
use app\common\model\UserAdminLogModel;
use app\common\redis\CommomRedis;
use app\common\service\MessageService;
use app\common\service\PixelService;
use app\common\service\UserOfflineService;
use app\common\service\UserWithdrawalService;
use app\manage\factory\AdvertisingFactory;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class Entry{
	
    private $config;
    private $domain;

    /**
     * @param $config 配置支付信息-如商户号
     * @param $domain 后台域名、配置回调地址
    **/ 
    public function __construct($config,$domain){
        $this->config=$config;
        $this->domain=$domain;
    }


    /**
     * 支付
     * @param $packageName 包名
     * @param $input 输入参数
    **/ 
    public function pay($packageName,$input){            
        $obj= "\app\common\payment\\".$packageName;            
        $rdata=(new $obj($this->config,$this->domain))->create($input);
        return $rdata;
    }

    /**
     * 提现
     * @param $packageName 包名
     * @param $field 验证参数
     * @param $input 输入参数
     * @param $isAsynch 是否异步 0 不是 1 是
     * @param $action 执行的某个方法
    **/ 
    public function withdrawal($packageName, $input, $isAsynch = 0){
        $obj = "\app\common\payment\\".$packageName;
        if($isAsynch == 1){
            $rdata = (new $obj($this->config,$this->domain))->create_withdrawal($input, $isAsynch);
        }else{
            $rdata = (new $obj($this->config,$this->domain))->create_withdrawal($input);
        }
        return $rdata;
    }

    /**
     * 订单查询
     * @param $packageName 包名
     * @param $input 输入参数
     **/
    public function getorder($packageName, $input){
        $obj = "\app\common\payment\\".$packageName;
        $object = new $obj($this->config,$this->domain);
        if(!method_exists($object,'getorder')){
            return 'method does not exist';
        }
        $rdata = $object->getorder($input);
        return $rdata;
    }

    /**
     * notify回调
     * @param $packageName 包名
     * @param $post 验证参数
    **/ 
    public function notify($packageName,$post){
        try {
            $obj= "\app\common\payment\\".$packageName;
            return (new $obj($this->config,$this->domain))->notify($post);
        } catch (\Exception $e) {
            return $e->getMessage();
        }		
    }
    
    /**
     * 提现回调
     * @param $packageName 包名
     * @param $post 验证参数
    **/ 
    public function notify_withdrawal($packageName,$post){
        try {
            $obj= "\app\common\payment\\".$packageName;
            return (new $obj($this->config,$this->domain))->notify_withdrawal($post);
        } catch (\Exception $e) {
            return $e->getMessage();
        }		
    }
    
    public function handle($order_sn, $trade_sn, $money=0){
        $CommomRedis=new CommomRedis();
        $sn = empty($order_sn) ? $trade_sn : $order_sn;
        $where = empty($order_sn) ? ['othr_number'=>$trade_sn] : ['order_sn'=>$order_sn];
        Db::startTrans();
        try {
            if (!$CommomRedis->lock("pay_".$sn)) {
                throw new \Exception("请勿频繁访问", 0);
            }
            
            $pay = UserOfflineModel::where($where)->find();
            if (!isset($pay['id'])) {
                Log::write("充值回调数据不存在:".UserOfflineModel::getLastSql(), "payment");
            	throw new \Exception("数据不存在", 0);
            }
            if ($pay['status'] == 3 || $pay['status'] == 4) {
            	throw new \Exception("请勿重复支付", 0);
            }
            if ($money > 0 && $money != $pay['apply_price']) {
            	$pay['apply_price'] = $money;
            }
            
            $upd = [];
            $first = 2;
            if(UserOfflineModel::where(["uid"=>$pay['uid'], 'first' => 1])->where([["status", 'in',"3,4"]])->count() == 0){
                $first = 1;
            }
            
            $arrival_price = $pay['apply_price'] * $pay['exchange_rate'];
            $upd['apply_price'] = $pay['apply_price'];
            $upd['arrival_price'] = $arrival_price;
            $upd['othr_number'] = $trade_sn;
            $upd['status'] = 3;
            $upd['first']=$first;
            $upd['update_time'] = time();
            $res = UserOfflineModel::where("id",$pay['id'])->update($upd);
            if($res){
                $arrival_price = UserOfflineModel::where(['id' => $pay['id']])->value('arrival_price');
                $msg = '充值成功, 订单号: {:order_sn}, 金额: {:price}, 币种: {:currency}';
                $currency = Util::i18n_currency_code($pay['arrival_currency']);
                UserMsgModel::add_msg($pay['uid'], "充值提示", $msg, 15, $pay['id'],['order_sn'=>$order_sn,'price'=>$arrival_price,'currency'=>$currency], 'CN', $first);
                UserModel::cz_balance($pay['uid'], $arrival_price, "+", true, "线上入款", 10);
                UserOfflineModel::recharge_rebate($pay['uid'], $arrival_price, $pay['channel']);


                if($pay['type'] == 1){
                    UserOfflineModel::payset_rebate($pay['id'], $pay['uid'], $pay['pay_id'], $arrival_price); // 线上渠道充值返利
                }else if($pay['type'] == 0){
                    UserOfflineModel::bank_sk_rebate($pay['id'], $pay['uid'], $pay['bank_sk_id'], $arrival_price);//线下渠道充值返利
                }

                if($first == 1){
                    //判断是首次
                    UserModel::where('id',$pay['uid'])->update(['xc_date' => date("Ym")]);

                    // 发送首充投流像素事件
                    PixelService::firstRecharge($pay['uid'], $order_sn, $pay['arrival_currency'], $arrival_price);
                } else {
                    PixelService::recharge($pay['uid'], $order_sn, $pay['arrival_currency'], $arrival_price);
                }
                MessageService::sendRechargeMsg($pay['uid'], $arrival_price, 'recharge', env('message_reset_time', 0));

                //给后台发送充值成功消息
                $isOnSystemMessage = env('IS_ON_SYSTEM_MESSAGE', 0);
                if ( $isOnSystemMessage == 1) {
                    MessageService::sendAdminGroupMsg('用户【'.$pay['uid'].'】充值成功');
                }

//                if (isset($pay['not_auto_action']) && $pay['not_auto_action'] == 0 ) {
//                    action_task($pay['uid'], [1,2], $pay['id']);
//                }
//                Queue::push("User@upgrade", ['id' => $pay['uid'], 'money' => $upd['arrival_price']], 'upgrade');
                //成功回调方法
                UserOfflineService::successCallback(UserOfflineModel::where('id', $pay['id'])->find());
            }
            // 提交事务
            Db::commit();
            //发送机器人消息，该方法放事务外，防止卡事务
            (new BotFactory())->AutonMessage(4, $pay['id']);

            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            //TODO: 查看日志 同一秒生成两条 success记录
//            $CommomRedis->unlock("pay_".$sn);//释放锁
            return $e->getMessage();
        }
    }
    
    public static function handle_withdrawal($order_sn, $transaction_id='', $status=1, $remarks = '')
    {	
        $CommomRedis=new CommomRedis();
        Db::startTrans();
        try {
            if (!$CommomRedis->lock("withdrawal_".$order_sn)) {
                throw new \Exception("请勿频繁访问", 0);
            }
            $where[] = ['order_sn','=',$order_sn];
            if($transaction_id){
                //去掉三方订单查询，防止请求代付时候超时没有三方订单号，影响回调不成功
//                $where[] = ['pay_order_sn','=',$transaction_id];
            }
            $pay = UserWithdrawalModel::where($where)->find();
            if (!isset($pay['id'])) {
                Log::write("提现回调数据不存在:".UserWithdrawalModel::getLastSql(), "withdrawal");
            	throw new \Exception("数据不存在", 0);
            }
            //状态（-9代付失败可审核， -2已取消，-1代付失败，1待审核，2拒绝，3通过，4手动通过，5进行中，6封控复查）
            if (in_array($pay['status'], [-9, -2, -1, 2, 3, 4])) {
            	throw new \Exception("请勿重复操作", 0);
            }
            $ApiRedis=new ApiRedis();
            $sys = $ApiRedis->systemInfo();
            $upd = [];
            $withdrawalErrRedisKey = 'withdrawal_error_bank_key_'.$pay['uid'].'_'.$pay['bank_id'];
            if ($status == 1) {
               $upd['pay_time']=time();
               $upd['status'] = 3;
                Util::email_notify('with', $pay['uid']);
               //删除提现失败redis
                $ApiRedis->delete($withdrawalErrRedisKey);
            }else{
                //判断是否是三方代付失败继续审核状态
                if( isset($sys['withdrawal_extra_status']) && $sys['withdrawal_extra_status'] == 1 ){
                    $upd['status'] = -9;
                }else{
                    $upd['status'] = -1;
                }
                $upd['third_err_remarks'] = $remarks;
                //设置提现失败redis，再次发起提现后会验证,保存五天
                $ApiRedis->set($withdrawalErrRedisKey, 1, 86400 * 5);
            }
            $upd['update_time'] = time();
            UserWithdrawalModel::where("id",$pay['id'])->update($upd);
            if ($status == 3) {
                MessageService::sendWithdrawMsg($pay['uid'], 3);
                $msg = lang('提现成功, 订单号: {:order_sn}, 金额: {:price} {:currency}');
                UserMsgModel::add_msg($pay['uid'], "提现提示", $msg, 16, $pay['id'],['order_sn'=>$order_sn,'price'=>$pay['arrival_price'],'currency'=>$pay['arrival_currency']]);
                // 提现事件
                PixelService::withdraw($pay['uid'], $order_sn, $pay['current_currency'], $pay['w_price']);
                //发送行销模板消息
            }
            if ($status == 2 && $upd['status'] == -1) {
                MessageService::sendWithdrawMsg($pay['uid'], 2);
                UserModel::cz_balance($pay['uid'], $pay['w_price'], "+", true, '提现拒绝');//返回余额
                UserAdminLogModel::add_log($pay['uid'], "第三方提现失败,订单号: {$order_sn}, 金额: {$pay['arrival_price']}, 原因: " . $remarks ?: '无', "第三方反馈");

                //退回彩金及彩金流水
                if( $pay['dec_cjbalance'] > 0 ){
                    UserModel::cz_cjbalance($pay['uid'], $pay['dec_cjbalance'], '+', '提现拒绝', 1, $pay['id'], 101, $pay['dec_cjsavings']);
                }
            }
            // 提交事务
            Db::commit();
            //发送机器人消息，该方法放事务外，防止卡事务
            (new BotFactory())->AutonMessage(5, $pay['id']);
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $CommomRedis->unlock("withdrawal_".$order_sn);//释放锁
            return $e->getMessage();
        }

    }


    /**
     * 获取代收余额
     * @param $packageName 包名
     * @param $type int 是否抛出错误信息 1 默认抛出错误
     **/
    public function get_balance($packageName, $type = 1){
        $obj = "\app\common\payment\\".$packageName;
        $payment =  (new $obj($this->config,$this->domain));
        if (method_exists($payment, 'get_balance')) {
            $rdata = $payment->get_balance($type). '';
            if($rdata == 'fail'){
                $res['code'] = 2;
            }else{
                $res['code'] = 1;
                $res['balance'] = $rdata;
            }
        } else {
            $res['code'] = 0;
        }
        return $res;
    }

    /**
     * 获取代付余额
     * @param $packageName 包名
     * @param $type int 是否抛出错误信息 1 默认抛出错误
     **/
    public function get_with_balance($packageName, $type = 1){
        $obj = "\app\common\payment\\".$packageName;
        $payment = (new $obj($this->config,$this->domain));
        if (method_exists($payment, 'get_with_balance')) {
            $rdata = $payment->get_with_balance($type);
            if($rdata == 'fail'){
                $res['code'] = 2;
            }else{
                $res['code'] = 1;
                $res['balance'] = $rdata;
            }
        } else {
            $res['code'] = 0;
        }
        return $res;
    }

    /**
     * 获取渠道汇率
     * @param $packageName 包名
     * @param $type int 是否抛出错误信息 1 默认抛出错误
     **/
    public function get_rate($packageName, $type = 1){
        $obj = "\app\common\payment\\".$packageName;
        $payment = (new $obj($this->config,$this->domain));
        if (method_exists($payment, 'get_rate')) {
            $rdata = $payment->get_rate($type);
            if($rdata == 'fail'){
                $res['code'] = 2;
            }else{
                $res['code'] = 1;
                $res['data'] = $rdata;
            }
        } else {
            $res['code'] = 0;
        }
        return $res;
    }
}