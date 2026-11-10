<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_jili extends Notify
{
    private $thirds_name = 'jili';

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj, $cfg)
    {
        return false;
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 0, string $msg = '', array $data = []): array
    {
        $arr['errorCode'] = $code;
        $arr['message'] = $msg;
        $arr = array_merge($arr, $data);
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //老虎机及鱼机下注
    function bet($param, $trdobj, $cfg){
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        $data = $param['param'];
        $third = $data['third'];
        $action = $third.':bet';
        $redis_key = "jili_bet_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(3,'参数无效');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $this->PushLog($action,'data',$data);
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['token'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = strval($data['round']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 60);//60秒内不接受重得action
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate($data['betAmount'],0);  //金额转换
                        $winloseAmount = Third::transExchangerate($data['winloseAmount'],0);
                        $gb_winlose = 1;
                        if ($data['winloseAmount'] > 0) {
                            $gb_winlose = 3;
                        } else {
                            $gb_winlose = 2;
                        }
                        $data_bet = [
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_cjprice'=>0,
                            'gb_sn'=>$order_sn,
                            'gb_winlose'=>$gb_winlose,
                            'gb_bettime'=>$ts,
                            'gb_overtime'=>$data['wagersTime'],
                            'gb_winprice'=>$winloseAmount,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        if ($amount < $ubalance) {
                            if($amount > 0){
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                $data_bet['gb_cjprice'] = $betcjprice;
                            }
                            $bid = (new GameBets())->insertGetId($data_bet);
                            if ($gb_winlose == 3 && $winloseAmount > 0) {
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                User::accountChange($uid, $winloseAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bid, $data_bet['gb_price'], $data_bet['gb_cjprice']);
                            }
                            $code = 0;$msg = 'Success';
                            $res['username'] = $data['token'];
                            $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                            $res['balance'] = $this->getBalance($uid);
                            $res['txId'] = $bid;
                            $res['token'] = $data['token'];
                            Db::commit();
                        } else {
                            $code = 2;$msg = 'Not enough balance';
                            $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                            Db::rollback();
                        }
                    }else{
                        $code = 1; $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 1; $msg = 'Already accepted';
                    $this->PushLog($action,'订单重复请求',$data);
                    Db::rollback();
                }
            } else {
                $code = 4;$msg = 'Token expired';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 5;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$param, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$res);
    }

    //牌局型下注
    function sessionBet($param, $trdobj, $cfg){
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        $data = $param['param'];
        $third = $data['third'];
        $action = $third.':sessionBet';
        $redis_key = "jili_sessionBet_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(3,'参数无效');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $this->PushLog($action,'data',$data);
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['token'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = strval($data['sessionId']);
                $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                if($data['type'] == 1 && empty($bet)){
                    if($data['preserve'] > 0){
                        $money = $data['preserve'];
                    }else if($data['turnover'] > 0){
                        $money = $data['turnover'];
                    }else{
                        $money = $data['betAmount'];
                    }
                    $amount = Third::transExchangerate($money, 0);
                    $data_bet = [
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_gid' => $gid,
                        'gb_uid' => $uid,
                        'gb_intime' => $ts,
                        'gb_price' => $amount,
                        'gb_sn' => $order_sn,
                        'gb_winlose' => 1,
                        'gb_bettime' => $ts,
                        'gb_overtime' => '',
                        'gb_winprice' => '',
                        'gb_betinfo' => json_encode($data)
                    ];
                    if ($amount < $ubalance) {
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        $data_bet['gb_cjprice'] = $betcjprice;
                        $bid = (new GameBets())->insertGetId($data_bet);
                        $code = 0;
                        $msg = 'Success';
                        $res['username'] = $data['token'];
                        $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                        $res['balance'] = $this->getBalance($uid);
                        $res['txId'] = intval($bid);
                        $res['token'] = $data['token'];
                        Db::commit();
                    } else {
                        $code = 2;
                        $msg = 'Not enough balance';
                        $this->PushLog($action, '用户:' . $uid . "余额不足", $data);
                        Db::rollback();
                    }
                }else{
                    if($data['type'] == 1){
                        $amount1 = Third::transExchangerate($data['betAmount'], 0);
                        $betcjprice = 0;
                        if ($amount1 < $ubalance) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                            $betcjprice = User::accountChange($uid, $amount1, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        } else {
                            $code = 2;
                            $msg = 'Not enough balance';
                            $this->PushLog($action, '用户:' . $uid . "余额不足", $data);
                            Db::rollback();
                            return $this->ReturnData($code,$msg,$res);
                        }
                        $bet->gb_cjprice += $betcjprice;
                        $bet->gb_price = $bet->gb_price + $data['betAmount'];
                        $bet->gb_betinfo = json_encode($data);
                        $bet->save();
                    }else{
                        $price = 0;
                        $turnover = $data['turnover'] ??0;
                        $preserve = $data['preserve'] ??0;
                        if ($data['winloseAmount'] > 0) {
                            $gb_winlose = 3;
                            if($preserve > 0){
                                $money2 = $bet->gb_price + $data['winloseAmount'] - $turnover;
                            }else{
                                $money2 = $data['winloseAmount'];
                            }
                            $price = Third::transExchangerate($money2,0);
                        } else {
                            $gb_winlose = 2;
                            $money2 = $bet->gb_price - $turnover;
                            $price = Third::transExchangerate($money2,0);
                        }
                        if($price > 0){
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                            User::accountChange($uid, $price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                        }
                        $bet->gb_overtime = $data['wagersTime'];
                        $bet->gb_winprice = $price;
                        $bet->gb_winlose = $gb_winlose;
                        $bet->gb_betinfo = json_encode($data);
                        $bet->save();
                    }
                    $code = 0;
                    $msg = 'Success';
                    $res['username'] = $data['token'];
                    $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                    $res['balance'] = $this->getBalance($uid);
                    $res['txId'] = $bet->gb_id;
                    $res['token'] = $data['token'];
                    Db::commit();
                }
            }else{
                $code = 4;$msg = 'Token expired';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$param, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$res);
    }


    //验证用户
    function auth($data, $trdobj, $cfg): array
    {
        $arr = [];
        $msg = '查询余额错误';
        try {
            $uid = UserModel::where('rec_code', $data['param']['token'])->value('id');
            if(empty($uid)){
                return $this->ReturnData(4, '无效用户', $arr);
            }
            $balance = $this->getBalance($uid);
            $arr['username'] = $data['param']['token'];
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            $arr['balance'] = $balance;
            $arr['token'] = $data['param']['token'];
            $msg = 'success';
            $code = 0;
        } catch (\Throwable $ex) {
            $msg = 'fail';
            $code = 5;
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

    //老虎机及鱼机取消注单
    function cancelBet($param, $trdobj, $cfg){
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        $data = $param['param'];
        $third = $data['third'];
        $action = $third.':cancelBet';
        $redis_key = "jili_cancelBet_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(3,'参数无效');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $this->PushLog($action,'data',$data);
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['token'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = strval($data['round']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn,'gb_winlose'=>1])->find();
                    if($bet){
                        Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 60);//60秒内不接受重得action
                        if ($data['betAmount'] > 0) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($uid, $bet->gb_price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();

                            $code = 0;$msg = 'Success';
                            $res['username'] = $data['token'];
                            $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                            $res['balance'] = $this->getBalance($uid);
                            $res['txId'] = $bet->gb_id;
                            Db::commit();
                        } else {
                            $code = 3;$msg = 'Invalid parameter';
                            $this->PushLog($action,'无效参数',$data);
                            Db::rollback();
                        }
                    }else{
                        $code = 6; $msg = 'Already accepted and cannot be canceled';
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 6; $msg = 'Already accepted';
                    $this->PushLog($action,'订单重复请求',$data);
                    Db::rollback();
                }
            } else {
                $code = 5;$msg = 'user does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 5;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$param, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$res);
    }

    //牌局型取消注单
    function cancelSessionBet($param, $trdobj, $cfg){
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        $data = $param['param'];
        $third = $data['third'];
        $action = $third.':cancelSessionBet';
        $redis_key = "jili_cancelSessionBet_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(3,'参数无效');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $this->PushLog($action,'data',$data);
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['token'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = strval($data['sessionId']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn,'gb_winlose'=>1])->find();
                    if($bet){
                        Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 60);//60秒内不接受重得action
                        if (isset($data['preserve']) && $data['preserve'] > 0) {
                            $money = $data['preserve'];
                        }else{
                            $money = $data['betAmount'];
                        }
                        if($money > 0){
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($uid, $bet->gb_price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $code = 0;$msg = 'Success';
                            $res['username'] = $data['token'];
                            $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                            $res['balance'] = $this->getBalance($uid);
                            $res['txId'] = $bet->gb_id;
                            Db::commit();
                        } else {
                            $code = 3;$msg = 'Invalid parameter';
                            $this->PushLog($action,'无效参数',$data);
                            Db::rollback();
                        }
                    }else{
                        $code = 2; $msg = 'Round not found';
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 5; $msg = 'Order repeat request';
                    $this->PushLog($action,'订单重复请求',$data);
                    Db::rollback();
                }
            } else {
                $code = 5;$msg = 'user does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 5;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$param, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$res);
    }


}
