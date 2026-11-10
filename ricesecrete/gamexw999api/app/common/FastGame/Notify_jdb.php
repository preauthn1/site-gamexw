<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_jdb extends Notify
{
    private $thirds_name = 'jbd';

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
        $datas = $datas['param'];
        $data = $this->decrypt($cfg,$datas['x']);  //解密数据
        if($data){
            $Method = 'Action'.$data['action'];
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $this->PushLog($Method,'notify_data',$data);
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            return self::$Method($data,$trdobj,$cfg);
        }else{
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('notify','数据异常',$datas);
            return $this->ReturnData('9999','数据异常');
        }
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(string $code = '0000', string $msg = '', $uid = 0): array
    {
        $arr['status'] = $code;
        $arr['balance'] = $this->getBalance($uid);
        $arr['err_text'] = $msg;
        return $arr;
    }

    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //解密
    private function decrypt($config, $data)
    {
        $data = str_replace(array('-','_') , array('+','/') , $data);
        $data = base64_decode($data);
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $config['trdcfg']['tc_api_signkey'], OPENSSL_NO_PADDING, $config['trdcfg']['tc_api_prikey']);
        return json_decode(utf8_encode(trim($decrypted)),true);
    }

    //下注
    function Action9($data, $trdobj, $cfg){
        $action = 'Action9';
        $redis_key = $this->thirds_name."_Action9_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['mType'], 'code', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData('9999','Invalid argument');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
//                $ubalance = $uinfo[Meta::UFIELD['ubalance']];
                $ubalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
                $order_sn = $data['transferId'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate($data['amount'],0);  //金额转换
                        $data_bet = [
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_sn'=>$order_sn,
                            'gb_winlose'=>1,
                            'gb_bettime'=>intval($data['ts']/1000),
                            'gb_overtime'=>'',
                            'gb_winprice'=>0,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        if ($amount < $ubalance) {
                            if($amount > 0){
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                                $id = (new GameBets())->insertGetId($data_bet);
                                if (!$id){
                                    $code = '9999';$msg = 'Other error';
                                    $this->PushLog($action,'订单写入错误',$data);
                                    Db::rollback();
                                }
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $id, 0, 0);
                                //下注彩金金额
                                $upd = [];
                                $upd['gb_cjprice'] = $betcjprice;
                                $rlt = (new GameBets())->where('gb_id', '=', $id)->update($upd);
//                                if (!$rlt){
//                                    $code = '9999';$msg = 'Other error';
//                                    $this->PushLog($action,'订单写入错误',$data);
//                                    Db::rollback();
//                                }
                                Db::commit();
                                $code = '0000';$msg = 'Success';
                            }
                        } else {
                            $code = '6006';$msg = 'Not enough balance';
                            $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                            Db::rollback();
                        }
                    }else{
                        $code = '9011'; $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = '0000'; $msg = 'frequent requests';
                    $this->PushLog($action,'请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = '7501';$msg = 'Token expired';
                $this->PushLog($action,'用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }

    //结算
    function Action10($data, $trdobj, $cfg){
        $action = 'Action10';
        $redis_key = $this->thirds_name."_Action10_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['mType'], 'code', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData('9999','Invalid argument');
        }
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                foreach ($data['refTransferIds'] as $v) {
                    $order_sn = $v;
                    $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                    if (!$aflag) {
                        Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                        $bet = GameBets::where(['gb_gid' => $gid, 'gb_sn' => $order_sn, 'gb_winlose' => 1])->find();
                        if ($bet) {
                            $price = 0;
                            $amount = Third::transExchangerate($data['amount'], 0);
                            $validBet = Third::transExchangerate($data['validBet'], 0);
                           if ($data['win'] > 0) {
                                $gb_winlose = 3;
                                $price = $amount;
                            } else if ($data['win'] - $data['validBet'] < 0) {
                                $gb_winlose = 2;
                            }
                            if ($price > 0) {
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                User::accountChange($uid, $price, '+', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $validBet, $bet->gb_cjprice);
                            }
                            $bet->gb_overtime = strtotime($data['lastModifyTime']);
                            $bet->gb_winprice = $price;
                            $bet->gb_winlose = $gb_winlose;
                            $bet->gb_price = $validBet;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $code = '0000';$msg = 'Success';
                            Db::commit();
                        } else {
                            $code = '9015';$msg = 'The order does not exist or the status is abnormal';
                            $this->PushLog($action, '订单不存在或状态异常', $data);
                            Db::rollback();
                        }
                    } else {
                        $code = '0000';$msg = 'frequent requests';
                        $this->PushLog($action, '请求频繁', $data);
                        Db::rollback();
                    }
                }
            }else{
                $code = '7501';$msg = 'User information does not exist';
                $this->PushLog($action,'用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }


    //下注并结算
    function Action8($data, $trdobj, $cfg)
    {
        $action = 'Action8';
        $redis_key = $this->thirds_name."_Action8_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['mType'], 'code', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData('9999','Invalid argument');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
//                $ubalance = $uinfo[Meta::UFIELD['ubalance']];
                $ubalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
                $order_sn = strval($data['transferId']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate(trim($data['bet'],'-'),0);
                        $winloseAmount = Third::transExchangerate($data['win'],0);
                        if($data['gType'] == 0){
                            $winloseAmount = $winloseAmount + Third::transExchangerate($data['jackpotWin'],0);
                        }
                        if ($winloseAmount > 0) {
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
                            'gb_sn'=>$order_sn,
                            'gb_winlose'=>$gb_winlose,
                            'gb_bettime'=>strtotime($data['gameDate']),
                            'gb_overtime'=>strtotime($data['lastModifyTime']),
                            'gb_winprice'=>$winloseAmount,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        if ($amount < $ubalance) {
                            $rlt = (new GameBets())->insertGetId($data_bet);
                            if ($rlt){
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, 0, 0);
                                if ($winloseAmount > 0) {
                                    $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    User::accountChange($uid, $winloseAmount, '+', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $amount, $betcjprice);
                                }
                                $code = '0000';$msg = 'Success';
                                Db::commit();
                            } else {
                                $code = '9999';$msg = 'Other error';
                                $this->PushLog($action,'用户:'.$uid."扣款失败",$data);
                                Db::rollback();
                            }
                        } else {
                            $code = '6006';$msg = 'Not enough balance';
                            $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                            Db::rollback();
                        }
                    }else{
                        $code = '6005'; $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = '0000'; $msg = 'frequent requests';
                    $this->PushLog($action,'请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = '7501';$msg = 'Token expired';
                $this->PushLog($action,'用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }

    //查询用户余额
    function Action6($data, $trdobj, $cfg)
    {
        $uid = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->value(Meta::UFIELD['uid']);
        if(empty($uid)){
            $this->PushLog('Action6', '用户不存在', 0);
            return $this->ReturnData('7501', 'Invalid user');
        }
        return $this->ReturnData('0000', 'Success', $uid);
    }

    function getBalance($uid){
//        $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);

        $balance = Third::transExchangerate($balance,1,2);
        return floatval($balance);
    }

    //取消下注并取消结算
    function Action4($data, $trdobj, $cfg){
        $action = 'Action4';
        $redis_key = $this->thirds_name."_Action4_";
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['transferId'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                $gid = 0;
                if( isset($data['mType']) ){
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['mType'], 'code', 'g_id');
                }
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_sn'=>$order_sn,'gb_winlose'=>1])->find();
                    if($bet){
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                        $rlt = User::accountChange($uid, $bet->gb_price, '+', 2, $msg, $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt){
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $code = '0000';$msg = 'Success';
                            Db::commit();
//                        } else {
//                            $code = '6101';$msg = 'Other error';
//                            $this->PushLog($action,'用户:'.$uid."注单取消失败",$data);
//                            Db::rollback();
//                        }
                    }else{
                        $code = '6101';$msg = 'The order does not exist or the status is abnormal';
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $code = '0000'; $msg = 'frequent requests';
                    $this->PushLog($action,'请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = '7501';$msg = 'user does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }

    //取消下注
    function Action11($data, $trdobj, $cfg){
        $action = 'Action11';
        $redis_key = $this->thirds_name."_Action11_";
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $amount = Third::transExchangerate($data['amount'],0);  //金额转换
                $gid = 0;
                if( isset($data['mType']) ){
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['mType'], 'code', 'g_id');
                }
                foreach ($data['refTransferIds'] as $v) {
                    $order_sn = $v;
                    $aflag = Redis::get($redis_key . "_" . $order_sn);
                    if (!$aflag) {
                        Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                        $bet = GameBets::where(['gb_sn' => $order_sn, 'gb_winlose' => 1])->find();
                        if ($bet) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                            if ($rlt) {
                                $bet->gb_overtime = $ts;
                                $bet->gb_winlose = 0;
                                $bet->gb_betinfo = json_encode($data);
                                $bet->save();
                                $code = '0000';$msg = 'Success';
                                Db::commit();
//                            } else {
//                                $code = '6101';
//                                $msg = 'Other error';
//                                $this->PushLog($action, '用户:' . $uid . "注单取消失败", $data);
//                                Db::rollback();
//                            }
                        } else {
                            $code = '6101';$msg = 'The order does not exist or the status is abnormal';
                            $this->PushLog($action, '订单不存在或状态异常', $data);
                            Db::rollback();
                        }
                    } else {
                        $code = '0000';$msg = 'frequent requests';
                        $this->PushLog($action, '请求频繁', $data);
                        Db::rollback();
                    }
                }
            } else {
                $code = '7501';$msg = 'user does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }

    //活动派彩
    function Action12($data, $trdobj, $cfg){
        $action = 'Action12';
        $redis_key = $this->thirds_name."_Action12_";
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['uid'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['transferId'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bet = Db::name('game_third_special')->where(['gs_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate($data['amount'],0,2);  //金额转换
                        $data_bet = [
                            'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gs_gid'=>0,
                            'gs_uid'=>$uid,
                            'gs_price'=>$amount,
                            'gs_sn'=>$order_sn,
                            'gs_type'=>1,
                            'gs_type_name'=>utf8_decode($data['activityName']),
                            'gs_time'=>strtotime($data['activityDate']),
                            'gs_info'=>json_encode($data),
                            'gs_create_time'=>$ts,
                        ];
                        $rlt = Db::name('game_third_special')->insertGetId($data_bet);
                        if($amount > 0){
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], 0, '活动派彩');
                            User::accountChange($uid, $amount, '+', 2,  $msg, true, $cfg['trdcfg']['tc_id']);
                        }
                        $code = '0000';$msg = 'Success';
                        Db::commit();
                    }else{
                        $code = '6101';$msg = 'order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = '0000';$msg = 'frequent requests';
                    $this->PushLog($action,'请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = '7501';$msg = 'user does not exist';
                $this->PushLog($action,'用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = '9999';$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        return $this->ReturnData($code,$msg,$uid ??0);
    }
}
