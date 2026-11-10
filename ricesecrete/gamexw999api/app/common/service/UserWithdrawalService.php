<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserBankModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserReportModel;
use app\common\model\UserWithdrawalModel;
use app\common\payment\Entry;
use app\common\redis\CommomRedis;
use app\common\Utils;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Session;

class UserWithdrawalService{

    public static function autoCheck($uid, $add){
        $ApiRedis=new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        //判断是否开启自动审核且申请金额小于系统设置金额
        if( !isset($sys['withdrawal_auto']) || $sys['withdrawal_auto'] != 1 ){
            return false;
        }
        if( !isset($sys['auto_max_amount']) || !isset($sys['savings_multiple']) ){
            return false;
        }
        $payId = $sys['auto_withdrawal_pay_id'];
        if( $sys['auto_max_amount'] != 0 && $add['w_price'] > $sys['auto_max_amount'] ){
            return false;
        }
        if( !isset($sys['auto_withdrawal_pay_id']) || $sys['auto_withdrawal_pay_id'] < 1 ){
            return false;
        }
        //判断提现充值提现比
        if( isset($sys['auto_withdrawal_recharge_rate']) && $sys['auto_withdrawal_recharge_rate'] > 0 ){
            //获取用户充值
            $_condition = [];
            $_condition[] = ['status', 'in', '3,4'];
            $_condition[] = ['uid', '=', $uid];
            $rechargeAmount = UserOfflineModel::where($_condition)->sum('arrival_price');
            //获取用户提现
            $_condition = [];
            $_condition[] = ['status', 'in', '3,4'];
            $_condition[] = ['uid', '=', $uid];
            $withdrawalAmount = UserWithdrawalModel::where($_condition)->sum('w_price');
            $userBalance = UserModel::where('id', '=', $uid)->value('balance');

            if( $rechargeAmount / ($withdrawalAmount + $add['w_price'] + $userBalance) < $sys['auto_withdrawal_recharge_rate'] ){
                return false;
            }
        }

        //获取今天充值
        $startTime = Carbon::now()->startOfDay()->timestamp;
        $endTime = time();
        $condition1 = [];
        $condition1[] = ['update_time', 'between', [$startTime, $endTime]];
        $condition1[] = ['uid', '=', $uid];
        $condition1[] = ['status', 'in', [3,4]];
        $offlineAmount = UserOfflineModel::where($condition1)->sum('arrival_price');

        //获取用户当天打码量
        $condition1 = [];
        $condition1[] = ['su_dt', '=', date('Ymd')];
        $todayFinishSavings = MemberService::getUserFinishWater($uid, $condition1);
        //判断流水是否满足倍数
        if( $offlineAmount * $sys['savings_multiple'] > $todayFinishSavings ){
            return false;
        }

        //自动审核
        self::check([
            'id' => $add['id'],
            'status' => 4,
            'pay_id' => $payId,
            'bank_code' => strtoupper(UserBankModel::where('id', '=', $add['bank_id'])->value('bank_name')),//用户银行卡中的bank_code
            'w_remarks' => '自动审核',
            'user_remarks' => '',
            'is_lock' => 0,
        ]);

        return true;
    }

    //提现审核
    // TODO 该方法有自动和后台调用，改的时候记得两个位置传参必须一致，否则会报错
    //数据格式$info = [
    //  "id" => '', 提现id
    //  "status" => '', 状态 2 驳回 3 通过 4 渠道支付 6 封控审核
    //  "pay_id" => '', 支付渠道id
    //  "bank_code" => '', 银行编码
    //  "is_lock" => '', 0
    //  "user_remarks" => '', //用户备注
    //  "w_remarks" => '', //备注
    //]
    public static function check($info){
        $CommomRedis=new CommomRedis();
        $CommomRedis=new CommomRedis();
        $sys = Utils::sysInfo();
        //5分钟内不能操作同一笔订单
        if (!$CommomRedis->lock('withdrawal_'.$info['id'], 300)) {
            return Respons::run(0,[],lang('请勿频繁访问'));
        }

        $data = UserWithdrawalModel::alias("a")->join("dq_user b","a.uid=b.id")->join("dq_user_bank c","a.bank_id=c.id")
            ->where("a.id",$info['id'])->field("a.*,b.qh,b.username,b.phone,b.mail,b.lebal,c.bank_number,c.bank_open,c.bank_hm,c.bank_name,c.financial, b.rec_code, c.branches_code, c.branches")->find();

        //判断是否锁定
        if( $data['is_lock'] == 1 ){
            $CommomRedis->unlock('withdrawal_'.$info['id']);
            return Respons::run(0,[],lang('当前订单已锁定，请解锁后再操作'));
        }

        if ($data['status'] > 1 && !in_array($data['status'], [5, 6])) {
            $CommomRedis->unlock('withdrawal_'.$info['id']);
            return Respons::run(0,[],lang('该订单已处理，请勿重复处理'));
        }

        if (!in_array($info['status'], [2, 3, 4, 6])) {
            $CommomRedis->unlock('withdrawal_'.$info['id']);
            return Respons::run(0,[],lang('请选择审核状态'));
        }

        if ($info['status'] == $data['status']) {
            $CommomRedis->unlock('withdrawal_'.$info['id']);
            return Respons::run(0,[],lang('请勿重复审核'));
        }
        $withdrawalErrRedisKey = 'withdrawal_error_bank_key_'.$data['uid'].'_'.$data['bank_id'];
        $apiRedis = new ApiRedis();
        Db::startTrans();
        try {
            if (!isset($data['id'])) {
                $CommomRedis->unlock('withdrawal_'.$info['id']);
                Db::rollback();
                return Respons::run(0,[],lang('数据不存在'));
            }
            $upd = [];
            $admin = Session::get("adminInfo");
            if ($info['status']==4) {
                if(!isset($info['pay_id'])){
                    $CommomRedis->unlock('withdrawal_'.$info['id']);
                    Db::rollback();
                    return Respons::run(0,[],lang('请选择代付渠道'));
                }
                $pay_type = PaySetModel::get_pay(['id' => $info['pay_id']]);//随机渠道
                if( $pay_type['min_withdrawal_amount'] > 0 && $data['w_price'] < $pay_type['min_withdrawal_amount'] ){
                    $CommomRedis->unlock('withdrawal_'.$info['id']);
                    Db::rollback();
                    return Respons::run(0,[],lang('当前渠道最低出款{:amount}', ['amount' => $data['w_price']]));
                }
                if( $pay_type['max_withdrawal_amount'] > 0 && $data['w_price'] > $pay_type['max_withdrawal_amount'] ){
                    $CommomRedis->unlock('withdrawal_'.$info['id']);
                    Db::rollback();
                    return Respons::run(0,[],lang('当前渠道最高出款{:amount}', ['amount' => $data['w_price']]));
                }

                $param['user_id'] = $data['uid'];
                $param['phone'] = $data['phone'];
                $param['mail'] = $data['mail'];
                $param['qh'] = $data['qh'];
                $param['rec_code'] = $data['rec_code'];
                $param['username'] = $data['username'];
//                $param['amount'] = $data['arrival_price'];//个数
                $param['amount'] = sprintf('%.2f', $data['arrival_price']);//#入口改为两位小数，统一改所有渠道
                $param['order_sn'] = $data['order_sn'];
                $param['address'] = $data['bank_number'];
                $param['bank_number'] = $data['bank_number'];
                $param['account_name'] = $data['bank_hm'];
                $param['ifsc'] = $data['financial'];
                $param['bankName'] = $data['bank_name'];
                //金融编码 传递给第三方支付渠道
                $param['bank_code'] = isset($info['bank_code'])?$info['bank_code']:"";
                $param['bank_open'] = $data['bank_open'];
                $param['branches'] = $data['branches'] ?? '';
                $param['branches_code'] = $data['branches_code'] ?? '';
                $param['pay_ip'] = Util::get_ip();
                $domain = env('CHARGE_WITHDRAWAL_DOMAIN', '');
                if( !$domain ){
                    $domain = $sys['domain'];
                }
                $param['notify_url'] = $domain . '/withdrawal/notify/'.$pay_type['id']; // 异步通知地址
                //先修改操作人数据，防止三方报错导致没有添加到操作人这些数据
                $upd['id'] = $data['id'];
                $upd['operator'] = $admin['username'] ?? '自动审核';
                if( isset($info['w_remarks']) ){
                    $upd['w_remarks']=$info['w_remarks'];
                }
                $upd['method_id'] = $pay_type['id'];
                $upd['pay_type'] = $pay_type['pay_sys_sign'];
                $upd['third_err_remarks'] = '';//进行中
                $upd['is_lock'] = $info['is_lock'] ?? 0;//锁定状态
                $upd['status'] = 5;//进行中
                $res = UserWithdrawalModel::update($upd);
                // 提交事务
                Db::commit();

                $Entry = new Entry($pay_type['config'], $sys['domain']);
                $res = $Entry->get_balance($pay_type['pay_sys_sign'], 2);
                $res2 = $Entry->get_with_balance($pay_type['pay_sys_sign'], 2);
                //先查询平台的代付余额
                if(isset($res2['code']) && $res2['code'] == 1){
                    if($param['amount'] > $res2['balance']){
                        $m = lang('平台余额不足，当前平台余额：') . $res2['balance'];
                        return Respons::run(0, '', $m);
                    }
                }else{
                    //不存在代付函数的时候获取代收余额
                    if(isset($res['code']) && $res['code'] == 1){
                        if($param['amount'] > $res['balance']){
                            $m = lang('平台余额不足，当前平台余额：') . $res['balance'];
                            return Respons::run(0, '', $m);
                        }
                    }
                }


                $withdrawalNo = $Entry->withdrawal($pay_type['pay_sys_sign'],$param);

                $upd = [];
                $upd['id'] = $data['id'];
                $upd['pay_order_sn'] = $withdrawalNo;
                $upd['status'] = 5;//进行中
                $upd['check_time'] = time();//审核时间
                $res = UserWithdrawalModel::update($upd);
            }else{
                $upd['user_remarks']=$info['user_remarks']??'';
                if ($info['status']==2) {
                    //驳回提现金额
                    UserModel::cz_balance($data['uid'],$data['w_price'],"+",true,'提现拒绝');
                    UserAdminLogModel::add_log($data['uid'],"提现审核拒绝, 订单号: {$data['order_sn']}, 金额: {$data['w_price']}, 原因: " . $upd['user_remarks'] ?: $info['w_remarks'],Session::get("adminInfo.name"));
                    MessageService::sendWithdrawMsg($data['uid'], 2, 'withdraw', $upd['user_remarks']);

                    $msg = lang('提现审核未通过，订单号: {:order_sn},  原因: {:user_remarks}');
                    UserMsgModel::add_msg($data['uid'], '提现审核', $msg, 16, $info['id'], ['order_sn' => $data['order_sn'], 'user_remarks' => $upd['user_remarks']]);

                    //退回彩金及彩金流水
                    if( $data['dec_cjbalance'] > 0 ){
                        UserModel::cz_cjbalance($data['uid'], $data['dec_cjbalance'], '+', '提现拒绝', 1, $data['id'], 101, $data['dec_cjsavings']);
                    }
                    //删除提现失败redis
                    $apiRedis->delete($withdrawalErrRedisKey);
                }

                $upd['w_remarks']=$info['w_remarks'];
                $upd['status']=$info['status'];
                $upd['operator'] = $admin['username'];
                $upd['third_err_remarks'] = '';//进行中
                $upd['update_time']=time();
                $upd['is_lock'] = $info['is_lock'] ?? 0;//锁定状态
                $upd['id']=$info['id'];
                $upd['check_time']=time();
                $res = UserWithdrawalModel::update($upd);
                if ($info['status'] == 3) {
                    $upd['pay_time']=time();
                    UserReportModel::updateReport($data['uid'], 11, $data['w_price']); //更新记录报表
                    MessageService::sendWithdrawMsg($data['uid'], 3, 'withdraw', $upd['user_remarks']);
                    UserMsgModel::add_msg($data['uid'], '提现审核', lang('订单号').' {:order_sn} '.lang('提现审核已通过').' {:user_remarks}', 16, $info['id'], ['order_sn' => $data['order_sn'], 'user_remarks' => $upd['user_remarks']]);
                    Util::email_notify('with', $data['uid']);
                    // 提现事件
                    PixelService::withdraw($data['uid'], $data['order_sn'], $data['current_currency'], $data['w_price']);
                    //设置提现失败redis，再次发起提现后会验证,保存五天
                    $apiRedis->set($withdrawalErrRedisKey, 1, 86400 * 5);
                }
                // 提交事务
                Db::commit();
            }
//            $data2 = UserWithdrawalModel::where('id',$info['id'])->find();
            $rear_data = "状态:{$upd['status']};备注:" . isset($upd['user_remarks']) ?: '';
//            if (isset($info['w_remarks']) && $info['w_remarks']) {
//                $rear_data .= ";备注:{$info['w_remarks']}";
//            }
            if (isset($info['bank_code']) && $info['bank_code']) {
                $rear_data .= ";银行代码:{$info['bank_code']}";
            }
            if (isset($info['pay_id']) && $info['pay_id'] && isset($pay_type)) {
                $rear_data .= ";到付渠道:{$pay_type['pay_name']}-{$pay_type['remarks']}";
            }
            Utils::addOptLog("提现审核:UID:{$data['uid']};订单号:{$data['order_sn']};状态:{$data['status']}", $rear_data, "user_withdrawal", "提现审核");
            return Respons::run($res?1:0);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $CommomRedis->unlock('withdrawal_'.$info['id']);
            //保存错误信息
            $errorMessage = $e->getMessage();
            $upd = [];
            $upd['id'] = $data['id'];
            $upd['third_err_remarks'] = $errorMessage;//进行中
            UserWithdrawalModel::update($upd);
            return Respons::run(0,[$e->getTrace()],$e->getMessage());
        }
    }

    //渠道根据银行名称获取银行编码
    public static function getBankCodeByBankName($bankName){
        $bank_code = '';
        $paySetList = PaySetModel::where('is_withdra', '=', 1)->field('pay_bank')->select();
        if( $paySetList ){
            foreach ($paySetList as $k => $v){
                $pay_bank = json_decode($v['pay_bank'], true);
                foreach ($pay_bank as $_k => $_v){
                    if( mb_strpos($_v['bank_name'], $bankName) !== false ){
                        return $_v['bank_code'];
                    }
                }
            }
        }
         return $bank_code;
    }

}