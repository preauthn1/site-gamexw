<?php
namespace app\api\controller;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\IntegralMxModel;
use app\common\payment\Entry;
use app\api\redis\ApiRedis;
use think\facade\Log;
use think\facade\View;
use think\facade\Request;
use app\common\model\LotteryConfigModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use PHPQRCode\QRcode;
class Notify
{
    public function index(){
        $sys=(new ApiRedis())->systemInfo();
        $param = Request::param();
        if(!$param){
            $param = file_get_contents("php://input");
            $param = json_decode($param,true);
        }
        $channel = $param['channel'];
        unset($param['s']);
        unset($param['channel']);
        Log::write([$channel,$param], "payment");
        $pay_set = PaySetModel::get_config($channel);
        $config = $pay_set['config'];$config['channel'] = $channel;
        $Entry = new Entry($config,$sys['domain']);
        $result = $Entry->notify($pay_set['pay_sys_sign'], $param);//回调结果
        if (isset($result['order_sn']) && isset($result['trade_sn'])){
            $money = 0;
            if(isset($result['money'])){
                $money = $result['money']*1;
            }
            $handle_res = $Entry->handle($result['order_sn'], $result['trade_sn'], $money);
            if ($handle_res === true){
                //抽奖转盘充值U金额送积分
                if($pay_set['pay_type'] == 1){
                    $this->recharge($result['order_sn']);
                }
                $msg = '';
                if(is_array($result['result'])){$msg = json_encode($result['result']);}else{$msg = $result['result'];}
                Log::write(['Notify_success', $channel,$msg,$param, request()->header()], "payment");
                echo $msg;exit();
            }else{
                Log::write(['Notify_return',$channel,$handle_res,$param, request()->header()],"pay_error");
                echo "faile";exit();
            }
        }else{
            Log::write(['Notify_return',$channel,$result,$param, request()->header()],"pay_error");
            echo $result;exit();
        }
    }
    
    //异步回调提现支付ydpay
    public function withdrawal(){
        $sys=(new ApiRedis())->systemInfo();
        $param = Request::param();
        if(!$param){
            $param = file_get_contents("php://input");
            $param = json_decode($param,true);
        }
        $channel = $param['channel'];
        unset($param['s']);
        unset($param['channel']);
        Log::write([$channel,$param], "withdrawal");
        $pay_set = PaySetModel::get_config($channel);
        $config = $pay_set['config'];$config['channel'] = $channel;
        $Entry = new Entry($config, $sys['domain']);
        $result = $Entry->notify_withdrawal($pay_set['pay_sys_sign'], $param);//回调结果
        if (isset($result['order_sn']) && isset($result['othr_number']) && isset($result['status'])){
            $handle_res = $Entry->handle_withdrawal($result['order_sn'], $result['othr_number'], $result['status'], $result['msg']);
            if ($handle_res === true){
                $msg = '';
                if(is_array($result['result'])){$msg = json_encode($result['result']);}else{$msg = $result['result'];}
                Log::write(['Withdrawal_success', $channel,$msg,$param, request()->header()], "withdrawal");
                echo $msg;exit();
            }else{
                Log::write(['Withdrawal_return',$channel,$handle_res,$param, request()->header()],"pay_error");
                echo "faile";exit();
            }
        }else{
            Log::write(['Withdrawal_return',$channel,$result,$param, request()->header()],"pay_error");
            echo 'faile';exit();
        }
    }
    
    //通用
    public function success(){
        return View::fetch('notify/success');
    }
    //通用
    public function error(){
        return View::fetch('notify/error');
    }

    //抽奖充值U送积分
    public function recharge($order_sn){
        if(empty($order_sn)){return;}
        $Config = LotteryConfigModel::where('id',1)->field('uprice,integral')->find();
        if($Config['uprice'] > 0 && $Config['integral'] > 0){
            $pay = UserOfflineModel::where("order_sn",$order_sn)->find();
            if (!empty($pay) && $pay['apply_price'] >= $Config['uprice']) {
                IntegralMxModel::cz_mx($pay['uid'],1,$Config['integral'],'充值赠送积分');
                UserMsgModel::add_msg($pay['uid'], "充值送积分", lang("充值奖励已到账: 积分").'{:integral}', 11, $pay['id'],['integral'=>$Config['integral']]);
            }
        }
    }

    //订单查询
    public function getorder(){
        $param = Request::param();
        if(!$param){
            $param = file_get_contents("php://input");
            $param = json_decode($param,true);
        }
        $channel = $param['channel'];
        unset($param['s']);
        unset($param['channel']);
        Log::write(['Getorder',$channel,$param], "withdrawal");
        $pay_set = PaySetModel::get_config($channel);
        $config = $pay_set['config'];$config['channel'] = $channel;
        $Entry = new Entry($config,'');
        $result = $Entry->getorder($pay_set['pay_sys_sign'], $param);//查询结果
        Log::write(['Getorder_return',$channel,$result,$param],"withdrawal");
        echo $result;
    }


    //获取支付二维码
    public function qrcode(){
        $param = Request::param();
        $ApiRedis = new ApiRedis();
        $object = new QRcode();
        $order_sn = $ApiRedis->get($param['order_sn']);
        if(!$order_sn){
            ajax_return('二维码已失效',0);
        }
        $errorCorrectionLevel = intval(3) ;//容错级别
        $matrixPointSize = intval(5);//生成图片大小
        $img = $object->png($order_sn, false, $errorCorrectionLevel, $matrixPointSize, 2);
        return response($img, 200, ['Content-Length' => filesize($img)])->contentType('image/png');
    }


    public function only_one_notify(){
        $param = Request::param();
        if(isset($param['type'])){
                if($param['type'] == 'deposit'){
                    $this->index();
                }
                if($param['type'] == 'withdrawal'){
                    $this->withdrawal();
                }
        }

    }
}