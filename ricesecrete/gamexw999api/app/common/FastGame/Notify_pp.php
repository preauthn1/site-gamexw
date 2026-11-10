<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserFreebetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_pp extends Notify
{
    private $thirds_name = 'pp';

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
        $arr['error'] = $code;
        $arr['description'] = $msg;
        $arr = array_merge($arr, $data);
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //下注
    function bet($param, $trdobj, $cfg){
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        $data = $param['param'];
        $action = 'bet';
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        if(!$CheckSign){
            return $this->ReturnData(5, 'hash error', []);
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$data);
            return $this->ReturnData(7,'Invalid argument');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = $data['roundId'].'_'.$data['reference'];
                $amount = Third::transExchangerate($data['amount'],0,2);  //金额转换
                //判断是否是免费游戏
                $bonus=false;
                if (isset($data['bonusCode']) && !empty($data['bonusCode'])){
                    $bonusCode=explode('|',$data['bonusCode'])[0];
                    $freebet=UserFreebetsModel::find($bonusCode);
                    $bonus=!!$freebet;
                }
                if ($amount < $ubalance || $bonus) {
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn,'gb_cusfield'=>$data['roundId']])->find();
                    if(!$bet){
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                        if (!$bonus) {//如果不是免费游戏则扣减用户钱包
                            $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        }else{
                            $betcjprice=0;
                        }
                        $data_bet = [
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_cjprice'=>$betcjprice,
                            'gb_sn'=>$order_sn,
                            'gb_cusfield'=>$data['roundId'],
                            'gb_winlose'=>1,
                            'gb_bettime'=>intval($data['timestamp']/1000),
                            'gb_overtime'=>'',
                            'gb_winprice'=>0,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        $bid = (new GameBets())->insertGetId($data_bet);
                        if ($bonus) {//核销免费游戏
                            UserFreebetsModel::VerificationFreeBet($freebet,$order_sn);
                            //if (empty($freebet->ufg_usedbygbsn)) $freebet->ufg_usedbygbsn = $order_sn;
                            //$freebet->ufg_usedprice += $amount;
                            //$freebet->save();
                        }
                        $res['transactionId'] = intval($bid);
                    }else{
                        $res['transactionId'] = $bet->gb_id;
                    }
                    Db::commit();
                } else {
                    $code = 1;$msg = 'Insufficient player balance';
                    $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                    Db::rollback();
                    return $this->ReturnData($code,$msg,[]);
                }
            } else {
                $res['transactionId'] = 0;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['transactionId'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $code = 0;$msg = 'Success';
        $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
        $res['cash'] = isset($uid) ? $this->getBalance($uid):0;
        $res['bonus'] = $bonus?Third::transExchangerate($freebet->ufg_pricecount-$freebet->ufg_usedprice,1,2):0;//如果是奖励游戏，则返回奖励金额
        $res['usedPromo'] = $bonus?$data['amount']:0;
        return $this->ReturnData($code,$msg,$res);
    }

    //结算
    function result($param, $trdobj, $cfg,$ste = 1,$action_name = "result"){
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        $data = $param['param'];
        $action = $action_name;
        $redis_key = $this->thirds_name."_".$action_name."_";
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        if(!$CheckSign){
            return $this->ReturnData(5, 'hash error', []);
        }
        $gid = 0;
        if($ste == 1) {
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
            if (!$gid) {
                $this->PushLog($action, '游戏不存在', $data);
                return $this->ReturnData(7, 'Invalid argument');
            }
        }
        $res = [];
        /*$bonus=false;
        if (isset($data['bonusCode']) && !empty($data['bonusCode'])){
            $bonusCode=explode('|',$data['bonusCode'])[0];
            $freebet=UserFreebetsModel::find($bonusCode);
            $bonus=!!$freebet;
        }*/
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['roundId'];
                $ke = $ste == 1 ? $redis_key . $gid . "_" . $order_sn:$redis_key . "_" . $order_sn;
                $aflag = Redis::get($ke);
                if (!$aflag) {
                    Redis::setex($ke, 1, 1);
                    $wh = $ste == 1 ? ['gb_gid'=>$gid,'gb_cusfield'=>$order_sn,'gb_winlose'=>1]:['gb_cusfield'=>$order_sn,'gb_winlose'=>1];
//                    $ms = $ste == 1 ? "[" . $gid . "]" . "_投注结算_" . ($order_sn) : "[" . $order_sn . "]" . "_投注结算";
                    $ms = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                    $bet = GameBets::where($wh)->field('gb_id,gb_price')->select();
                    if(count($bet) > 0) {
                        $promoWinAmount = 0;
                        $amount = Third::transExchangerate($data['amount'], 0,2);
                        if(isset($data['promoWinAmount'])){
                            $promoWinAmount = Third::transExchangerate($data['promoWinAmount'], 0,2);
                        }
                        if(count($bet) <= 1) {
                            $price = 0;
                            if ($amount - $bet[0]['gb_price'] == 0) {
                                $price = $bet[0]['gb_price'];
                            } else if ($amount > 0) {
                                $price = $amount;
                            } else if ($amount - $bet[0]['gb_price'] < 0) {
                            }
                            $bet2 = GameBets::where('gb_id', $bet[0]['gb_id'])->find();
                            $price = floatval($price+$promoWinAmount);
                            if ($price > 0) {
                                User::accountChange($uid, $price, '+', 2, $ms, true, $cfg['trdcfg']['tc_id'], $gid, $bet2->gb_id, $bet2->gb_price, $bet2->gb_cjprice);
                            }

                            $bet2->gb_overtime = intval($data['timestamp'] / 1000);
                            $bet2->gb_winprice = $price;
                            $bet2->gb_winlose = $price > 0 ? 3 : 2;
                            $bet2->gb_betinfo = json_encode($data);
                            $bet2->save();
                            $res['transactionId'] = $bet[0]['gb_id'];
                        }else{
                            $price2 = 0;
                            foreach ($bet as $v){
                                $bid[] = $v['gb_id'];
                            }
                            if ($amount > 0) {
                                $gb_winlose = 3;
                                $price2 = $amount;
                            } else {
                                $gb_winlose = 2;
                            }
                            $time = intval($data['timestamp'] / 1000);
                            rsort($bid);
                            $max_id = $bid[0];
                            unset($bid[0]);
                            $price2 = floatval($price2+$promoWinAmount);

                            $bet2 = GameBets::where('gb_id', $max_id)->find();
                            $bet2->gb_overtime = $time;
                            $bet2->gb_winprice = $price2;
                            $bet2->gb_winlose = $gb_winlose;
                            $bet2->gb_betinfo = json_encode($data);
                            $bet2->save();

                            $otherBet = (new GameBets())::where([['gb_id','in',$bid]])->select();
                            foreach ($otherBet as $_otherBet) {
                                //TODO 必须循环然后每条处理，否则会造成有效流水没有走模型里面的方法
                                $_otherBet->gb_overtime = $time;
                                $_otherBet->gb_winprice = 0;
                                $_otherBet->gb_winlose = $gb_winlose;
                                $_otherBet->save();
                            }
//                            (new GameBets())::where([['gb_id','in',$bid]])->update(['gb_overtime' => $time,'gb_winprice' => 0,'gb_winlose' => $gb_winlose]);
                            if ($price2 > 0) {
                                User::accountChange($uid, $price2, '+', 2, $ms, true, $cfg['trdcfg']['tc_id'], $gid, $bet2->gb_id, $bet2->gb_price, $bet2->gb_cjprice);
                            }
                            $res['transactionId'] = $max_id;
                        }
                        Db::commit();
                    }else{
                        $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                    $this->PushLog($action,'订单频繁请求',$data);
                    Db::rollback();
                }
            }else{
                $res['transactionId'] = 0;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['transactionId'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $code = 0; $msg = 'Success';
        $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
        $res['cash'] = isset($uid) ? $this->getBalance($uid):0;
        $res['bonus'] = 0;//$bonus?Third::transExchangerate($freebet->ufg_pricecount-$freebet->ufg_usedprice,1,2):0;//如果是奖励游戏，则返回奖励金额;
        return $this->ReturnData($code,$msg,$res);
    }


    //验证用户
    function authenticate($data, $trdobj, $cfg)
    {
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
        $data = $data['param'];
        $this->PushLog('authenticate', '验证用户', $data);
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        if($data['providerId'] != $cfg['trdcfg']['tc_api_prikey']){
            $this->PushLog($data['action_method'], '商户错误', $data);
            return $this->ReturnData(7, 'Merchant does not exist', []);
        }
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        if(!$CheckSign){
            $this->PushLog($data['action_method'], 'hash error', $data);
            return $this->ReturnData(5, 'hash error', []);
        }
        $arr = [];
        try {
            $user = UserModel::where(Meta::UFIELD['uname'], $data['token'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
            if(empty($user)){
                $this->PushLog($data['action_method'], '用户不存在', $data);
                $arr['userId'] = $data['token'];
                $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                $arr['cash'] = 0;
                $arr['bonus'] = 0;
                return $this->ReturnData(10, 'User does not exist', $arr);
            }
            $balance = $this->getBalance($user[Meta::UFIELD['uid']]);
            $arr['userId'] = $data['token'];
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            $arr['cash'] = $balance;
            $arr['bonus'] = 0;//UserFreebetsModel::getBonusBalance($user->id, $cfg['trdcfg']['tc_id']);
            $msg = 'Success';
            $code = 0;
        } catch (\Throwable $e) {
            $msg = 'fail';$code = 100;
            $arr['userId'] = $data['token'];
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            $arr['cash'] = 0;
            $arr['bonus'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog('authenticate', $msg, [$data, $e->getMessage(), $e->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //查询用户余额
    function balance($data, $trdobj, $cfg)
    {
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
        $data = $data['param'];
        $this->PushLog('balance', '查询用户余额', $data);
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        if(!$CheckSign){
            return $this->ReturnData(5, 'hash error', []);
        }
        $user = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
        if(empty($user)){
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            $arr['cash'] = 0;
            $arr['bonus'] = 0;
            return $this->ReturnData(10, 'User does not exist', $arr);
        }
        $balance = $this->getBalance($user[Meta::UFIELD['uid']]);
        $arr['currency'] = $cfg['trdcfg']['tc_currency'];//货币
        $arr['cash'] = floatval($balance);
        $arr['bonus'] = 0;
        return $this->ReturnData(0, 'Success', $arr);
    }

    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1,2);
        return floatval($balance);
    }

    //取消注单
    function refund($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'refund';
        $redis_key = $this->thirds_name."_refund_";
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        if(!$CheckSign){
            $this->PushLog($action,'hash error',$data);
            return $this->ReturnData(5, 'hash error', []);
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['uid'])->find();

            $gid = 0;
            if(isset($data['gameId'])) {
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
            }
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['roundId'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $where = ['gb_cusfield'=>$order_sn,'gb_winlose'=>1];
                    $bet = GameBets::where($where)->find();
                    if($bet){
                        $fee = GameBets::where($where)->sum('gb_price');
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                        User::accountChange($uid, $fee, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt){
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $res['transactionId'] = $bet->gb_id;
                            Db::commit();
//                        } else {
//                            $res['transactionId'] = $bet->gb_id;
//                            $this->PushLog($action,'用户:'.$uid."注单取消失败",$data);
//                            Db::rollback();
//                        }
                    }else{
                        $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                    $this->PushLog($action,'订单频繁请求',$data);
                    Db::rollback();
                }
            } else {
                $res['transactionId'] = 0;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['transactionId'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $code = 0;$msg = 'Success';
        return $this->ReturnData($code,$msg,$res);
    }

    //免费回合结算
    function bonusWin($param, $trdobj, $cfg){
        //TODO 免费游戏的不能再次结算，因为每局都是一个注单，bonusWin最后还会再发送一次注单，所以这里不用处理
        $res = [];
        $res['transactionId'] = 0;
        $code = 0;$msg = 'Success';
        return $this->ReturnData($code,$msg,$res);

//        //TODO 如果经过验证免费游戏不会在正常结束回合结算则要在这里进行结算
//        return $this->result($param, $trdobj, $cfg,2,'bonusWin');
    }

    //累积奖金结算
    function JackpotWin($param, $trdobj, $cfg){
        return $this->PromoWin($param, $trdobj, $cfg,2,'JackpotWin');
    }

    //活动结算
    function PromoWin($param, $trdobj, $cfg,$ste = 1,$action = 'PromoWin'){
        $data = $param['param'];
        $redis_key = $this->thirds_name."_".$action."_";
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        if(!$CheckSign){
            $this->PushLog($action,'hash error',$data);
            return $this->ReturnData(5, 'hash error', []);
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $ste == 1 ? $data['campaignId']:$data['roundId'];
                $ms = $ste == 1 ? "_活动派彩":"_累计奖金";
                $gs_type_name = $ste == 1 ? "活动派彩":"累计奖金";
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bet = Db::name('game_third_special')->where(['gs_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate($data['amount'],0,2);  //金额转换
                        $data_bet = [
                            'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gs_gid'=>$ste == 1 ? 0:GameBets::where(['gb_cusfield'=>$data['roundId']])->value('gb_gid') ??0,
                            'gs_uid'=>$uid,
                            'gs_price'=>$amount,
                            'gs_sn'=>$order_sn,
                            'gs_type'=>1,
                            'gs_type_name'=>$gs_type_name,
                            'gs_time'=>intval($data['timestamp']/1000),
                            'gs_info'=>json_encode($data),
                            'gs_create_time'=>$ts,
                        ];
                        if($amount > 0){
                            User::accountChange($uid, $amount, '+', 2,  "[".$order_sn."]" . $ms);
                        }
                        $bid = Db::name('game_third_special')->insertGetId($data_bet);
                        $res['transactionId'] = intval($bid);
                        Db::commit();
                    }else{
                        $res['transactionId'] = $bet['gs_id'];
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $res['transactionId'] = Db::name('game_third_special')->where(['gs_sn'=>$order_sn])->value('gs_id') ??0;
                    $this->PushLog($action,'订单频繁请求',$data);
                    Db::rollback();
                }
            } else {
                $res['transactionId'] = 0;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['transactionId'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$data, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $code = 0;$msg = 'Success';
        $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
        $res['cash'] = isset($uid) ? $this->getBalance($uid):0;
        $res['bonus'] = 0;
        return $this->ReturnData($code,$msg,$res);
    }

    //真人游戏结算
    function adjustment($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'adjustment';
        $redis_key = $this->thirds_name."_adjustment_";
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        if(!$CheckSign){
            $this->PushLog($action,'hash error',$data);
            return $this->ReturnData(5, 'hash error', []);
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gameId'], 'tag', 'g_id');
        if (!$gid) {
            $this->PushLog($action, '游戏不存在', $data);
            return $this->ReturnData(7, 'Invalid argument');
        }
        $res = [];
        $code = 0;$msg = 'Success';
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = $data['roundId'];
                $reference = $data['reference'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 1);
                    //三方有重复结算，如果临时注单没找到找主注单
                    $baseModel = new GameBets();
                    $bet = $baseModel->where(['gb_gid'=>$gid,'gb_cusfield'=>$order_sn])->field('gb_id,gb_price')->select();
                    if( count($bet) == 0 ){
                        //临时注单没有找主注单
                        $baseModel = new GameBetsModel();
                        $bet = $baseModel->where(['gb_gid'=>$gid,'gb_cusfield'=>$order_sn])->field('gb_id,gb_price')->select();
                    }
                    if(count($bet) > 0) {
                        $amount = Third::transExchangerate(trim($data['amount'], '-'), 0, 2);
                        if ($data['amount'] > 0) {
                            $gb_winlose = 3;
                            $act = '+';
                        } else {
                            $gb_winlose = 2;
                            $act = '-';
                        }
                        if ($act === '-' && $amount > $ubalance) {
                            $code = 1;$msg = 'Insufficient player balance';
                            $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                            Db::rollback();
                            return $this->ReturnData($code,$msg,$res);
                        }
                        $betcjprice = 0;
                        if (count($bet) <= 1) {
                            $bet2 = $baseModel->where('gb_id', $bet[0]['gb_id'])->find();
                            if( $bet2->gb_trans_no != $reference ){
                                //重复请求，过滤
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                if( $act == '-' ){
                                    $betcjprice = User::accountChange($uid, $amount, $act, 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                                }else{
                                    User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet2->gb_id, $bet2->gb_price, $bet2->gb_cjprice);

                                }
                                $bet2->gb_overtime = intval($data['timestamp'] / 1000);
                                //多次请求为三方调整余额，负数为增加下注金额，正数为派彩
                                $bet2->gb_cjprice += $betcjprice;
                                if( $act == '-' ){
                                    $bet2->gb_price += $amount;
                                }else{
                                    $bet2->gb_winprice += $amount;
                                }
                                $bet2->gb_winlose = $gb_winlose;
                                $bet2->gb_trans_no = $reference;
                                $bet2->gb_betinfo = json_encode($data);
                                $bet2->save();
                            }
                            $res['transactionId'] = $bet[0]['gb_id'];
                        } else {
                            foreach ($bet as $v) {
                                $bid[] = $v['gb_id'];
                            }
                            $time = intval($data['timestamp'] / 1000);
                            rsort($bid);
                            $max_id = $bid[0];
                            unset($bid[0]);
                            $bet2 = $baseModel->where('gb_id', $max_id)->find();
                            if( $bet2->gb_trans_no != $reference ){
                                //重复请求，过滤
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                if( $act == '-' ){
                                    $betcjprice = User::accountChange($uid, $amount, $act, 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                }else{
                                    User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet2->gb_id, $bet2->gb_price, $bet2->gb_cjprice);
                                }
                                $bet2->gb_overtime = $time;
                                $bet2->gb_cjprice = $betcjprice;
                                if( $act == '-' ){
                                    $bet2->gb_price += $amount;
                                }else{
                                    $bet2->gb_winprice += $amount;
                                }
                                $bet2->gb_winlose = $gb_winlose;
                                $bet2->gb_trans_no = $reference;
                                $bet2->gb_betinfo = json_encode($data);
                                $bet2->save();

                                $otherBet = $baseModel->where([['gb_id', 'in', $bid]])->select();
                                foreach ($otherBet as $_otherBet) {
                                    //TODO 必须循环然后每条处理，否则会造成有效流水没有走模型里面的方法
                                    $_otherBet->gb_overtime = $time;
                                    $_otherBet->gb_winprice = 0;
                                    $_otherBet->gb_winlose = $gb_winlose;
                                    $_otherBet->gb_trans_no = $reference;
                                    $_otherBet->save();
                                }
//                            (new GameBets())::where([['gb_id', 'in', $bid]])->update(['gb_overtime' => $time, 'gb_winprice' => 0, 'gb_winlose' => $gb_winlose]);
                            }
                            $res['transactionId'] = $max_id;
                        }
                        Db::commit();
                    }else{
                        $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                        return $this->ReturnData(1,'订单不存在或状态异常');
                    }
                }else{
                    $res['transactionId'] = GameBets::where(['gb_cusfield'=>$order_sn])->value('gb_id') ??0;
                    $this->PushLog($action,'订单频繁请求',$data);
                    Db::rollback();
                    return $this->ReturnData(1,'订单频繁请求');
                }
            }else{
                $res['transactionId'] = 0;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
                return $this->ReturnData(1,'用户信息不存在');
            }
        } catch (\Exception $ex) {
            $res['transactionId'] = 0;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->ReturnData(1,'过程错误');
        }
        $res['currency'] = $cfg['trdcfg']['tc_currency'];//货币
        $res['cash'] = isset($uid) ? $this->getBalance($uid):0;
        $res['bonus'] = 0;
        return $this->ReturnData($code,$msg,$res);
    }

    //回合通知（用于输的操作）
    function EndRound($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'EndRound';
        $redis_key = $this->thirds_name."_refund_";
        $CheckSign = $trdobj->CheckSign($cfg,$data);
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        if(!$CheckSign){
            $this->PushLog($action, 'hash error', $data);
            return $this->ReturnData(5, 'hash error', []);
        }
        $ts=time();
        $res = [];
        /*$bonus=false;
        if (isset($data['bonusCode']) && !empty($data['bonusCode'])){
            $bonusCode=explode('|',$data['bonusCode'])[0];
            $freebet=UserFreebetsModel::find($bonusCode);
            $bonus=!!$freebet;
        }*/
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['userId'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['roundId'];
                $wid = $data['win']??0;
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $where = ['gb_cusfield'=>$order_sn,'gb_winlose'=>1];
                    $bet = GameBets::where($where)->find();
                    if($bet){
                        if($wid <= 0){
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 2;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            Db::commit();
                        }
                    }else{
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $this->PushLog($action,'订单频繁请求',$data);
                    Db::rollback();
                }
            } else {
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误',[$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $code = 0;$msg = 'Success';
        $res['cash'] = isset($uid) ? $this->getBalance($uid):0;
        $res['bonus'] = 0;//$bonus?Third::transExchangerate($freebet->ufg_pricecount-$freebet->ufg_usedprice,1,2):0;//如果是奖励游戏，则返回奖励金额
        return $this->ReturnData($code,$msg,$res);
    }
}
