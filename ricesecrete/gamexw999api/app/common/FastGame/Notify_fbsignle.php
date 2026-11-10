<?php


namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;

class Notify_fbsignle extends Notify
{
    private $thirds_name = '';

    private $game_code = 'fb_signle';

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj,$cfg)
    {
        return false;
    }

    /**
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 0, string $msg = '', array $data = []): array
    {
        $arr['code'] = $code;
        $arr['message'] = $msg;
        $arr['data'] = $data;
        return $arr;
    }

    private function PushLog($action, $msg, $data){
        Utils::L($this->thirds_name, [$action. $msg, $data]);
    }

    public function fb($datas, $trdobj, $cfg){
        $param = $datas['param'];
        $action = $param['callback'];
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
        try {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            $info = [];
            if($action != 'health'){
                //sync_transaction 接口的时候 merchantUserId 是在二维数组中
                $u_id = $param['merchantUserId'] ?? $param[0]['merchantUserId'];
                $utuser = UserThirds::where(['ut_trdname' => $u_id])->find();
                $info['uid'] = $utuser->ut_uid;
                $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
                $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                //这里要校验货币 获取余额的时候 没有货币id过来
                if($action != 'balance'){
                    if($action == 'sync_orders'){
                        $currency = $param['currency'];
                    }else{
                        $currency = $param['currencyId'] ?? $param[0]['currencyId'];
                    }
                }else{
                    $currency = $info['currency'];
                }

                $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                if($currency != $info['currency']){
                    $this->PushLog($action, '货币错误', $datas);
                    $res['code'] = 1;
                    return $res;
                }
            }
            if($signflag){
                $res = $this->$action($param, $trdobj, $cfg, $info);
            } else {
                $this->PushLog($action, '签名错误', $datas);
                $res['code'] = 1;
            }
            $this->PushLog($action, '执行完成', $res);
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action, '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $res['code'] = 1;
        }

        return $res;
    }

    private function health(): array
    {
        return $this->ReturnData();
    }

    private function balance($data, $trdobj, $cfg, $info): array
    {
        $msg = '查询余额错误';
        $arr = [];
        try {
            $balance = $this->getUserBalance($info['uid']);
            $arr['balance'] = $balance;
            $arr['currencyId'] = $info['currency'];
            $code = 0;
            $msg = '';
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog('balance', '过程错误', [$data, $e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
        }
        return $this->ReturnData($code, $msg, [$arr]);
    }

    //订单支付  支付的时候写入一条订单数据 存订单号 流水号。确保记录了流水号 在流水数据接口 可以操作这条数据
    public function order_pay($data, $trdobj, $cfg, $info){
        $ts = time();
        Db::startTrans();
        try {
            $code = 0;
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $userBalance = $this->getUserBalance($info['uid']);
            $aflag = Redis::get("fb_order_pay_" . $info['gid'] . "_" . $data['businessId']);
            if(!$aflag) {
                Redis::setex("fb_order_pay_" . $info['gid'] . "_" . $data['businessId'], 1, 60);//60秒内不接受重复得action
                if ($data['transactionType'] == 'OUT' && $data['status'] == 1) { //类型是扣款的时候 并且是正常的时候
                    //投注额大于最低投注的时候 才能正常下注
                    $amount = abs($data['amount']);
                    if($amount >= $cfg['trdcfg']['tc_min_bet']){
                        //从我方到三方的时候 汇率相乘 回来的时候相除
                        $amount = Third::transExchangerate($amount,0);
                        //TODO 写入一条初始订单 订单回调的时候删除原始订单 再重新生成
                        if ($amount < $userBalance) {
                            $msg = UserModel::betRemarks($data['businessId'], $cfg['trdcfg']['tc_name'], $info['gid']);
                            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                            //减少余额成功的时候
                            $bet = [
                                'gb_gid' => $info['gid'],
                                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                                'gb_uid' => $info['uid'],
                                'gb_intime' => $ts,
                                'gb_price' => $amount,
                                'gb_cjprice' => $betcjprice,
                                'gb_sn' => $data['businessId'],
                                'gb_winlose' => 1,
                                'gb_bettime' => $ts,
                                'gb_overtime' => '',
                                'gb_winprice' => 0,
                                'gb_betinfo' => ''//原始订单为空的时候就是下注的时候生成的初始订单
                            ];
                            //写入注单
                            /* @GameBets $GameBetsModel */
                            $res = (new GameBets())->insertGetId($bet);
                            if ($res){
                                $this->PushLog($data['callback'], '注单写入成功', $bet);
                                Db::commit();
                            }else{
                                $this->PushLog($data['callback'], '注单写入失败', $bet);
                                $code = 1;
                                Db::rollback();
                            }
                        }else{
                            $this->PushLog($data['callback'], '用户余额不足', $data);
                            $code = 9;
                            Db::rollback();
                        }
                    }else{
                        $this->PushLog($data['callback'], '注金低于最低下注金额', $data);
                        $code = 1;
                        Db::rollback();
                    }
                }else{
                    $this->PushLog($data['callback'], '类型错误', $data);
                    $code = 1;
                    Db::rollback();
                }
            }else{
                $this->PushLog($data['callback'], '订单重复请求', $data);
                $code = 1;
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['callback'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
            Db::rollback();
        }
        return $this->ReturnData($code);
    }

    private function TypeEnum($k){
        $enum['IN']	= '转入';
        $enum['OUT']	= '转出';
        $enum['BET']	= '押注';
        $enum['WIN']	= '订单结算';
        $enum['REFUND']	= '退款';
        $enum['CASHOUT']	= '提前结算';
        $enum['CANCEL_DEDUCT']	= '订单取消补扣';
        $enum['CANCEL_RETURN']	= '订单取消返还';
        $enum['SETTLEMENT_ROLLBACK_RETURN']	= '结算回滚返还';
        $enum['SETTLEMENT_ROLLBACK_DEDUCT']	= '结算回滚补扣';
        $enum['CASHOUT_CANCEL_DEDUCT']	= '提前结算订单取消补扣';
        $enum['CASHOUT_CANCEL_RETURN']	= '提前结算订单取消返还';
        return $enum[$k];
    }

    //流水数据推送
    public function sync_transaction($data, $trdobj, $cfg, $info){
        $ts = time();
        Db::startTrans();
        $return = [];
        $order_play_data = $data[0];
        try {
            $code = 0;
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            //比较疑惑 数据结构是 下标0的数据
            $amount = abs($order_play_data['amount']);
            $amount = Third::transExchangerate($amount,0);
            /* @GameBets $GameBetsModel */
            $GameBetsModel = app()->make(GameBets::class);
            $GamePayLogsModel = app()->make(GamePayLogs::class);
            $gd_sn = $order_play_data['businessId'];
            $action_name = $this->TypeEnum($order_play_data['transferType']);

            $aflag = Redis::get("fb_sync_transaction_" . $info['gid'] . "_" . $order_play_data['transactionId']);
            if(!$aflag) {
                Redis::setex("fb_sync_transaction_" . $info['gid'] . "_" . $order_play_data['transactionId'], 1, 60);//60秒内不接受重复得action
                $PayLogs = $GamePayLogsModel->where(['gpl_sn' => $this->game_code.'_'.$order_play_data['transactionId']])->find();

                $wh = $upd = [];
//                $wh['gb_winlose'] = 1; //未结算注单
                $wh['gb_gid'] = $info['gid'];
                $wh['gb_sn'] = $gd_sn;
                //先查询有没有这个订单 没有就不做处理 直接返回成功
                $GameBets = $GameBetsModel->where($wh)->find();

                if(isset($PayLogs['gpl_id'])){
                    $this->PushLog($data['callback'], '订单已处理', $data);
                    $code = 1;
                    Db::rollback();
                }else{
                    //状态等于0的时候 是下注的订单取消 返回金额
                    if($order_play_data['status'] == 0){
                        if(isset($GameBets['gb_id'])){
                            $msg = UserModel::betRemarks($gd_sn, $cfg['trdcfg']['tc_name'], $info['gid'], '取消');
                            User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets->id, $GameBets->gb_price, $GameBets->gb_cjprice);
                            $GameBets['gb_winlose'] = 0; // 无效注单
                            $GameBets['gb_overtime'] = time();
                            $GameBets->gb_betinfo = json_encode($data);
                            $GameBets->save();
                            //写入注单
                            $pay_log = [
                                'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                                'gpl_gid' => $info['gid'],
                                'gpl_uid' => $info['uid'],
                                'gpl_type' => 1,
                                'gpl_price' => $amount,
                                'gpl_sn' => $this->game_code.'_'.$order_play_data['transactionId'],
                                'gpl_info' => json_encode($data), //更新原始注单
                                'gpl_text' => $action_name,
                                'gpl_state' => 1,
                                'gpl_create_time' =>  date("Y-m-d H:i:s"),
                            ];
                            $GamePayLogsModel->save($pay_log);
                            $this->PushLog($data['callback'], '注单更新成功', $order_play_data);
                            Db::commit();
                        }
                    }else{
                        if($order_play_data['transactionType'] == 'OUT'){
                            $msg = UserModel::betRemarks($gd_sn, $cfg['trdcfg']['tc_name'], $info['gid']);
                            //扣钱的操作
                            //当类型是押注的时候 查看有没有这个注单。有就是已经扣过了 返回成功即可
                            $gpl_type = 2;
                            if($order_play_data['transferType'] == 'BET'){
                                if(isset($GameBets['gb_id'])){
                                    $rlt = true;
                                    $this->PushLog($data['callback'], "订单已扣款", $order_play_data);
                                }else{
                                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                                }
                            }else{
                                //不是bet的时候 扣款都是补扣
                                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                            }
                        }else{
                            $msg = UserModel::betRemarks($gd_sn, $cfg['trdcfg']['tc_name'], $info['gid'], '结算');
                            //加钱的操作 二次结算也是发送的 WIN 但是流水id不同  记录流水id  如果有这个流水id 就不再执行 防止后台 重复点击推送。二次结算 按 正常结算即可  将流水id 记录到 pay_log 中
                            $gpl_type = 1;
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets->id, $GameBets->gb_price, $GameBets->gb_cjprice);

                        }
                        if($order_play_data['transferType'] != 'BET'){
                            $pay_log = [
                                'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                                'gpl_gid' => $info['gid'],
                                'gpl_uid' => $info['uid'],
                                'gpl_type' => $gpl_type, // 1加钱 2扣钱
                                'gpl_price' => $amount,
                                'gpl_sn' => $this->game_code.'_'.$order_play_data['transactionId'],
                                'gpl_info' => json_encode($data), //更新原始注单
                                'gpl_text' => $action_name,
                                'gpl_state' => 1,
                                'gpl_create_time' =>  date("Y-m-d H:i:s"),
                            ];
                            $GamePayLogsModel->save($pay_log);
                        }
                        $this->PushLog($data['callback'], "类型:{$action_name} 余额更新成功", $order_play_data);
                        Db::commit();
                    }
                }

            }else{
                $this->PushLog($data['callback'], '订单重复请求', $data);
                $code = 1;
                Db::rollback();
            }

        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['callback'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
            $return['transactionId'] = $order_play_data['transactionId'];
            Db::rollback();
        }
        return $this->ReturnData($code, '' , $return);
    }

    //订单数据推送
    public function sync_orders($data, $trdobj, $cfg, $info){
        Db::startTrans();
        try {
            $gd_sn = $data['id'];

            $wh = $upd = [];
//            $wh['gb_winlose'] = 1; //未结算注单
            $wh['gb_gid'] = $info['gid'];
            $wh['gb_sn'] = $gd_sn;
            $aflag = Redis::get("fb_sync_orders_" . $info['gid'] . "_" . $gd_sn);
            if(!$aflag) {
                Redis::setex("fb_sync_orders_" . $info['gid'] . "_" . $gd_sn, 1, 3);//3秒内不接受重复得action  因为二次结算的时候 是从 已结算到未结算 再到已结算

                /* @GameBets $GameBetsModel */
                $GameBetsModel = app()->make(GameBets::class);
                $GameBets = $GameBetsModel->where($wh)->find();
                //订单不操作余额 余额漏加 在后台手动推送 重新加点
                if(isset($GameBets['gb_id'])){
                    //未结算注单的时候 正常结算  推送注单一定建立在 我方生成了 初始注单。也就是order_play 生成了。在这里结算注单 或者取消注单。如果查询不到注单 直接返回不存在。
                    //结算订单的时候  可能是订单二次结算（二次结算的时候 补扣在流水接口 重新结算在该接口） 可能是订单重复推送
//                    if($GameBets['gb_winlose'] == 1){
                    $gb_winprice = 0;
                    $sta = 1; //当订单状态为 1 或者 4 的时候  可能只是重复推送了 状态不变
                    $time = 0;
                    if($data['orderStatus'] == 2 || $data['orderStatus'] == 3){
                        $sta = 0;
                    }else if($data['orderStatus'] == 5){
                        if(isset($data['settleAmount']) && $data['settleAmount'] > 0){
                            //彩金
                            $gb_winprice = abs($data['settleAmount']);
//                            //从我方到三方的时候 汇率相乘 回来的时候相除
                            $gb_winprice = Third::transExchangerate($gb_winprice,0);
                            $sta = 3;
                        }else{
                            $sta = 2;
                        }
                    }
                    $upd['gb_winlose'] = $sta;
                    $upd['gb_winprice'] = isset($data['settleAmount']) ? $gb_winprice : 0; //这个字段没有结算的时候 不存在
                    $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
                    $upd['gb_overtime'] = isset($data['settleTime']) ? $data['settleTime'] / 1000 : $time;
                    $res = $GameBets->save($upd);
                    if ($res) {
                        $this->PushLog($data['callback'], '订单写入成功', $upd);
                        $code = 0;
                        Db::commit();
                    }else{
                        $this->PushLog($data['callback'], '订单写入失败', $upd);
                        $code = 1;
                        Db::rollback();
                    }
//                    }else{
//
//
//                    }

                }else{
                    $this->PushLog($data['callback'], '订单不存在', $data);
                    $code = 1;
                    Db::rollback();
                }
            }else{
                $this->PushLog($data['callback'], '订单重复请求', $data);
                $code = 1;
                Db::rollback();
            }

        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['callback'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
            Db::rollback();
        }
        return $this->ReturnData($code);
    }

    private function getSta($settleResult, $orderStatus){
        $sta = 1;
        $time = 0;
        if(!empty($settleResult)){
            switch($settleResult){
                case 2 :
                    $sta = 4;
                    $time = time();
                    break;
                case 3 :
                    $sta = 2;
                    $time = time();
                    break;
                case 4 :
                case 5 :
                case 6 :
                    $sta = 3;
                    $time = time();
                    break;
                case 7 :
                    $sta = 0;
                    break;
            }
        }else{
            //不存在settleResult的时候 以orderStatus状态
            if($orderStatus == 2 || $orderStatus == 3){
                $sta = 0;
            }
        }
        return [$sta, $time];
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

}
