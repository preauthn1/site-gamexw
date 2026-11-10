<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;
use think\facade\Request;

class Notify_ka extends Notify
{
    private $thirds_name = 'ka';

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
     * @return array
     */
    private function ReturnData(int $code = 0, string $msg = 'success', array $data = []): array
    {
        $arr['status'] = $msg;
        $arr['statusCode'] = $code;
        $arr = array_merge($arr, $data);
        $this->PushLog('ReturnData','返回数据',$arr);
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //验证用户
    function start($data, $trdobj, $cfg,$action = 'start',$msg = '验证用户')
    {
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        $data = Request::param(false);
        $this->PushLog($action, $msg, $data);
        if(!$this->verify($data, $trdobj, $cfg)){
            $this->PushLog($action,'哈希码验证失败',$data);
            return $this->ReturnData(3,'Verification failed');
        }
        $arr = [];$balance = 0;
        $user = UserModel::where(Meta::UFIELD['uname'], $data['partnerPlayerId'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
        if($user){
            $balance = $this->getUserBalance($user[Meta::UFIELD['uid']])*100;
            $msg = 'success';$code = 0;
        }else{
            $msg = 'Player does not exist';$code = 5;
            $this->PushLog($action, '用户不存在', $data);
        }
        if($action == 'start'){
            $arr['playerId'] = $data['partnerPlayerId'];
            $arr['sessionId'] = $data['sessionId'];
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
        }
        if($action != 'end'){
            $arr['balance'] = intval($balance);
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    //查询余额
    function balance($data, $trdobj, $cfg)
    {
        return $this->start($data, $trdobj, $cfg,'balance','查询余额');
    }

    //验证
    function verify($data, $trdobj, $cfg)
    {
        $hash = $data['hash'];
        unset($data['third'],$data['action_method'],$data['s'],$data['hash']);
        $myhmac = $trdobj->MakeSign($cfg,$data);
        if($hash == $myhmac){
            return true;
        }else{
            return false;
        }
    }

    function getBalance($uid){
        $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
        $balance = Third::transExchangerate($balance,1,2);
        return intval($balance*100);
    }

    //下注并结算
    function play($param, $trdobj, $cfg){
        $data = Request::param(false);$action = 'play';
        $redis_key = $this->thirds_name."_play_";
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        if(!$this->verify($data, $trdobj, $cfg)){
            $this->PushLog($action,'哈希码验证失败',$data);
            return $this->ReturnData(3,'Verification failed');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(500,'The game does not exist');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['partnerPlayerId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getUserBalance($uid);
                $order_sn = $data['transactionId'].'_'.$data['round'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount =  floatval(Third::transExchangerate($data['betAmount'],0)/100);
                        $winAmount = floatval(Third::transExchangerate($data['winAmount'],0)/100);
                        $gb_winlose = 1;
                        if ($winAmount > 0) {
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
                            'gb_bettime'=>$ts,
                            'gb_overtime'=>$ts,
                            'gb_winprice'=>$winAmount,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        if ($data['freeGames']) {
                            $data_bet['gb_price'] = 0;
                            $betId = (new GameBets())->insertGetId($data_bet);
                            if ($gb_winlose == 3) {
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                User::accountChange($uid, $winAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $betId, $data_bet['gb_price'], 0);
                            }
                            $code = 0;
                            $msg = 'success';
                            Db::commit();
                        } else {
                            if ($amount < $ubalance) {
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                $data_bet['$betcjprice'] = $betcjprice;
                                $betId = (new GameBets())->insertGetId($data_bet);
                                if ($gb_winlose == 3) {
                                    $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    User::accountChange($uid, $winAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $betId, $data_bet['gb_price'], $data_bet['gb_cjprice']);
                                }
                                $code = 0;
                                $msg = 'success';
                                Db::commit();
                            } else {
                                $code = 200;
                                $msg = 'Not enough balance';
                                $this->PushLog($action, '用户:' . $uid . "余额不足", $data);
                                Db::rollback();
                            }
                        }
                    }else{
                        $code = 1; $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 1; $msg = 'Frequent order requests';
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = 5;$msg = 'Player does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 1;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ??0);
        $res['playerIp'] = $data['playerIp'];
        return $this->ReturnData($code,$msg,$res);
    }

    //游戏派彩
    function credit($param, $trdobj,$cfg){
        $data = Request::param(false);$action = 'credit';
        $redis_key = $this->thirds_name."_credit_";
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        if(!$this->verify($data, $trdobj, $cfg)){
            $this->PushLog($action,'哈希码验证失败',$data);
            return $this->ReturnData(3,'Verification failed');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(500,'The game does not exist');
        }
        $res = [];
        $ts = time();
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['partnerPlayerId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['transactionId'].'_cr_'.$data['creditIndex'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = floatval(Third::transExchangerate($data['amount'], 0)/100);
                        $gb_winlose = 1;
                        if ($amount > 0) {
                            $gb_winlose = 3;
                        } else{
                            $gb_winlose = 2;
                        }
                        $data_bet = [
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>0,
                            'gb_sn'=>$order_sn,
                            'gb_winlose'=>$gb_winlose,
                            'gb_bettime'=>$ts,
                            'gb_overtime'=>$ts,
                            'gb_winprice'=>$amount,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        $betId = (new GameBets())->insertGetId($data_bet);
                        if ($amount > 0) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                            User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $betId, $data_bet['gb_price'], $data_bet['gb_cjprice']);
                        }
                        $code = 0;$msg = 'success';
                        Db::commit();
                    }else{
                        $code = 1; $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 1; $msg = 'Frequent order requests';
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = 5;$msg = 'Player does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 1;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ??0);
        return $this->ReturnData($code,$msg,$res);
    }

    //取消注单
    function revoke($param, $trdobj, $cfg){
        $data = Request::param(false);$action = 'revoke';
        $redis_key = $this->thirds_name."_revoke_";
        $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
        if(!$this->verify($data, $trdobj, $cfg)){
            $this->PushLog($action,'哈希码验证失败',$data);
            return $this->ReturnData(3,'Verification failed');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['partnerPlayerId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $ubalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $gid = 0;
                if( isset($data['gameId']) ){
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
                }

                $order_sn = $data['revokedAction'] == 'play' ? $data['transactionId'].'_'.$data['round']:$data['transactionId'].'_cr_'.$data['round'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bets = GameBets::where(['gb_sn'=>$order_sn])->find();
                    if($bets){
                        $bet2 = GameBets::where(['gb_sn'=>$order_sn,'gb_winlose'=>0])->find();
                        if(!$bet2){
                            $bet = $win = 0;
                            if($data['revokedAction'] == 'play'){
                                $win = floatval(Third::transExchangerate($data['win'], 0)/100);
                                $bet = floatval(Third::transExchangerate($data['bet'], 0)/100);
                            }else{
                                $win = floatval(Third::transExchangerate($data['amount'], 0)/100);
                            }
                            if($bet > 0 && $data['round'] == 0){
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                                User::accountChange($uid, $bet, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet2->id, $bet2->gb_price, $bet2->gb_cjprice);
                            }
                            if($win > 0 && $win < $ubalance){
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                                $betcjprice = User::accountChange($uid, $win, '-', 4, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                $bets->gb_overtime = $ts;
                                $bets->gb_winlose = 0;
                                $bets->gb_betinfo = json_encode($data);
                                $bets->save();
                                $code = 0;$msg = 'success';
                                Db::commit();
                            }else{
                                $code = 200;$msg = 'Insufficient user balance, deduction failed';
                                $this->PushLog($action,'用户:'.$uid."余额不足,扣款失败",$data);
                                Db::rollback();
                            }
                        }else{
                            $code = 401; $msg = 'Abnormal status';
                            $this->PushLog($action,'订单状态异常',$data);
                            Db::rollback();
                        }
                    }else{
                        $code = 400; $msg = 'Frequent order requests';
                        $this->PushLog($action,'订单不存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 1; $msg = 'Frequent order requests';
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = 5;$msg = 'Player does not exist';
                $this->PushLog($action,'用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = 1;$msg = 'Other error';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ??0);
        return $this->ReturnData($code,$msg,$res);
    }

    //退出游戏验证
    function end($param, $trdobj, $cfg){
        return $this->start($param, $trdobj, $cfg,'end','退出游戏验证');
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

}
