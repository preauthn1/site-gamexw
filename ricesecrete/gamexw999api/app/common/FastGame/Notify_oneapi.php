<?php

namespace app\common\FastGame;

use app\api\redis\ApiRedis;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsSon, GamePayLogs, Games, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;
use app\common\lib\Util;

class Notify_oneapi extends Notify{

    private $tcid = 0;

    private $thirdClass = null;

    private $traceId = null;

    private $currency = null;


    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj,$cfg){
        $param = $datas['param'];
        //获取回调对应方法
        $s = $param['s'];
        $sArr = explode('/', $s);
        $action = $sArr[count($sArr) - 1];
        unset($param['s']);unset($param['action_method']);
        return $this->$action($param, $trdobj, $cfg);
    }

    function wallet($datas, $trdobj, $cfg){
        $this->thirdClass = new Third_oneapi();
        $this->tcid = $cfg['trdcfg']['tc_id'];
        $this->traceId = $datas['param']['traceId']??$datas['traceId'];
        $this->pushLog($datas, 'wallet', [$trdobj, $cfg]);
        $s_arr = explode( 'wallet/', $datas['param']['s']);
        $this->pushLog($datas, $s_arr[1], $trdobj);
        //验证货币
        $this->currency = strtoupper($datas['param']['currency']);
        if( $this->currency != strtoupper($cfg['trdcfg']['tc_currency']) ){
            $this->pushLog($datas, ['check currency'], $trdobj);
//            return $this->returnData([], 'SC_CURRENCY_NOT_SUPPORTED');
            return $this->returnData([], 'SC_WRONG_CURRENCY');
        }
        //验证token
        $res = $this->verifyToken($datas, $trdobj, $cfg);
        //获取余额没有对应平台信息，无法校验
        if( $res !== true ){
            return $this->returnData([], $res);
        }
        $datas = $datas['param'] ?? $datas;
        switch ($s_arr[1]) {
            case 'balance':
                //获取余额
                return $this->balance($datas, $trdobj, $cfg);
            case 'bet':
                //下注交易，从钱包余额中扣除金额
                return $this->bet($datas, $trdobj, $cfg);
            case 'bet_result':
                //下注交易请求，从用户余额中借记和/或贷记资金
                return $this->bet_result($datas, $trdobj, $cfg);
            case 'rollback':
                //对下注交易的回滚操作
                return $this->betRollback($datas, $trdobj, $cfg);
            case 'adjustment':
                //对游戏回合中赢取金额的调整。
                return $this->adjustment($datas, $trdobj, $cfg);
        }
        $this->pushLog($datas, '未找到对应方法', $trdobj);
        return $this->returnData([], 'SC_WRONG_CURRENCY');
    }

    //验证token
    public function verifyToken($datas, $trdobj, $cfg){
        //验证token
        $signature = $datas['header']['X-Signature'] ?? $datas['header']['x-signature'] ?? '';
        $apiSecret = $cfg['trdcfg']['tc_api_signkey'];
        $params =  $datas['param']??$datas;
        unset($params['s'], $params['third'], $params['action_method']);
        //参数类型转换
        if( isset($params['betTime']) ){
            $params['betTime'] = (int)$params['betTime'];
        }
        if( isset($params['settledTime']) ){
            $params['settledTime'] = (int)$params['settledTime'];
        }
        if( isset($params['isFreespin']) ){
            $params['isFreespin'] = (int)$params['isFreespin'];
        }
        if( isset($params['isEndRound']) ){
            $params['isEndRound'] = (int)$params['isEndRound'];
        }
        if( isset($params['timestamp']) ){
            $params['timestamp'] = (int)$params['timestamp'];
        }
        if( isset($params['amount']) ){
            $params['amount'] = (float)$params['amount'];
        }
        if( isset($params['winAmount']) ){
            $params['winAmount'] = (float)$params['winAmount'];
        }
        if( isset($params['jackpotAmount']) ){
            $params['jackpotAmount'] = (float)$params['jackpotAmount'];
        }
        if( isset($params['betAmount']) ){
            $params['betAmount'] = (float)$params['betAmount'];
        }
        if( isset($params['effectiveTurnover']) ){
            $params['effectiveTurnover'] = (float)$params['effectiveTurnover'];
        }
        if( isset($params['winLoss']) ){
            $params['winLoss'] = (float)$params['winLoss'];
        }
        $_signature = $this->thirdClass->createSignature($apiSecret, $params);
        if( $_signature != $signature ){
            $this->pushLog($datas, 'signature验证错误', [$trdobj, $cfg, $apiSecret, $signature, $_signature], 'error');
            return 'SC_INVALID_SIGNATURE';
        }
        // $token = $params['token'] ?? '';
        // $username = $params['username'] ?? '';
        // if( !$token || !$username ){
        //     $this->pushLog($datas, 'token或username错误', [$trdobj, $cfg, $apiSecret, $token, $username], 'error');
        //     return 'SC_INVALID_SIGNATURE';
        // }
        // $condition = [];
        // $condition[] = ['ut_trdid', '=', $username];
        // $condition[] = ['ut_token', '=', $token];
        // if( !UserThirds::where($condition)->order('ut_lastin desc')->value('ut_uid') ){
        //     $this->pushLog($datas, '用户信息不存在', [$trdobj, $cfg, $apiSecret, $condition, $token], 'error');
        //     return 'SC_INVALID_TOKEN';
        // }
        return  true;
    }

    //对游戏回合中赢取金额得调整
    private function adjustment($datas, $trdobj, $cfg){
        Db::startTrans();
        try {
            $username = $datas['username'];
            $gameCode = $datas['gameCode'];
            $roundId = $datas['roundId'];#回合id
            $amount = $datas['amount'];#调整金额
            $externalTransactionId = $datas['externalTransactionId'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if( !$uinfo ){
                //用户不存在
                Db::rollback();
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取主注单和子注单
            $bet = GameBets::where('gb_trans_no', '=', $externalTransactionId)->find();
            if( !$bet ){
                //注单不存在
                Db::rollback();
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS');
            }
            $betSon = GameBetsSon::where('gb_sn_ac', '=', $roundId)->find();
            $addAmount = $amount;
            if( $amount > 0){
                $symbol = '+';
                $type = 3;
            }else{
                $symbol = '-';
                $type = 4;
                $amount = abs($addAmount);
                if( $amount > $userBalance ){
                    Db::rollback();
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    return $this->returnData([], 'SC_INSUFFICIENT_FUNDS');
                }
            }
            $gid = $this->thirdClass->getGameIdByGameCode($gameCode);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
        try {
            //主注单已结算则修改注单金额和状态
            if( !in_array($bet->gb_winlose, [0, 1]) ){
                $bet->gb_winprice = $bet->gb_winprice + $amount >= 0 ? $bet->gb_winprice + $amount : 0;
                $bet->gb_winlose = $bet->gb_winprice > 0 ? 3 : 2;
                $bet->gb_betinfo = json_encode($datas);
                $rlt = $bet->save();
                if( !$rlt ){
                    Db::rollback();
                    $this->pushLog($datas, '更新主注单错误', $trdobj);
                    return $this->returnData([], 'SC_INVALID_REQUEST');
                }
            }
            if( $betSon ){
                //存在子注单则修改自注单金额
                $betSon->gb_winprice = $betSon->gb_winprice + $amount >= 0 ? $betSon->gb_winprice + $amount : 0;
                $betSon->gb_winlose = $betSon->gb_winprice > 0 ? 3 : 2;
                $betSon->gb_betinfo = json_encode($datas);
                $rlt = $betSon->save();
                if( !$rlt ){
                    Db::rollback();
                    $this->pushLog($datas, '更新子注单错误', $trdobj);
                    return $this->returnData([], 'SC_INVALID_REQUEST');
                }
            }
            if( $addAmount > 0 ){
                //增加或减少用户余额
                $msg = UserModel::betRemarks($bet->gb_sn, $cfg['trdcfg']['tc_name'], $gid, '调整');
                if( $symbol == '+' ){
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $addAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                }else{
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $addAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    //取消
    private function betRollback($datas, $trdobj, $cfg){
        Db::startTrans();
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if( !$uinfo ){
                //用户不存在
                Db::rollback();
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if( !$bet ){
                //注单不存在
                Db::rollback();
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS');
            }
            if( $bet->gb_winlose == 0 ){
                Db::rollback();
                return $this->returnData([
                    'balance' => (float)$userBalance,
                    'username' => $username
                ]);
            }
            //计算该退回给用户多少钱
            $userAddAmount = $bet->gb_price - $bet->gb_winprice;
            if( $userAddAmount > 0 ){
                $symbol = '+';
                $type = 3;
            }else{
                $symbol = '-';
                $type = 4;
                $userAddAmount = abs($userAddAmount);
                if( $userAddAmount > $userBalance ){
                    Db::rollback();
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    return $this->returnData([], 'SC_INSUFFICIENT_FUNDS');
                }
            }
            $gid = $this->thirdClass->getGameIdByGameCode($gameCode);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }

        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST');
            }
            if( $userAddAmount > 0 ){
                //增加或减少用户余额
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '取消');
                if( $symbol == '+' ){
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                }else{
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                }
//                if( !$rlt ){
//                    Db::rollback();
//                    $this->pushLog($datas, '取消注单余额更新失败', $trdobj);
//                    return $this->returnData([], 'SC_INVALID_REQUEST');
//                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }

    }

    //派彩
    private function bet_result($datas, $trdobj, $cfg){
        Db::startTrans();
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            $roundId = $datas['roundId'];#回合id
            $isFreespin = $datas['isFreespin'];#是否是免费次数
            $isEndRound = $datas['isEndRound'];#免费次数是否用完
            $resultType = $datas['resultType'];#类型
            $transactionId = $datas['transactionId'];#交易id，不能重复
            $externalTransactionId = $datas['externalTransactionId'];
            $betAmount = $datas['betAmount'];#下注金额
            $betAmount = Third::transExchangerate($betAmount, 0, 2);
            $winAmount = $datas['winAmount'];#派彩金额
            $winAmount = Third::transExchangerate($winAmount, 0, 2);
            $jackpotAmount = $datas['jackpotAmount'];#jackpotAmount
            $jackpotAmount = Third::transExchangerate($jackpotAmount, 0, 2);
            if( $winAmount < 0 ){
                Db::rollback();
                $this->pushLog($datas, '结算金额为负', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST');
            }
            $winAmount = $winAmount + $jackpotAmount;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if( !$uinfo ){
                //用户不存在
                Db::rollback();
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $apiRedis = new ApiRedis();
            if( (
                $betAmount == 0 && $winAmount == 0) || #下注和派彩都为0
                $apiRedis->has($transactionId) #交易id重复了直接返回
            ){
                Db::rollback();
                return $this->returnData([
                    'balance' => (float)$balance,
                    'username' => $username
                ]);
            }
            if( in_array(strtoupper($resultType), ['BET_WIN', 'BET_LOSE']) && $betAmount > $balance ){
                Db::rollback();
                $this->pushLog($datas, '用户余额不足', $trdobj);
                return $this->returnData([], 'SC_INSUFFICIENT_FUNDS');
            }
            $gid = $this->thirdClass->getGameIdByGameCode($gameCode);
            $ts = time();
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }

        try {
            $addAmount = 0;
            //判断是否是免费游戏
            if( $isFreespin ){
                //判断免费游戏是否结束
                if( $isEndRound ){
                    //结束了统计子注单后更改主订单状态
                    $addAmount = GameBetsSon::where('gb_sn', '=', $betId)->sum('gb_winprice');
                }else{
                    //没有结束操作子注单
                    $dataSon = [
                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                        'gb_gid'=>$gid,
                        'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                        'gb_intime'=>$ts,
                        'gb_price'=>0,
                        'gb_sn'=> $betId,
                        'gb_sn_ac'=> $roundId,
                        'gb_winlose'=> $winAmount > 0 ? 3 : 2,
                        'gb_bettime'=>$ts,
                        'gb_overtime'=>$ts,
                        'gb_winprice'=> $winAmount,
                        'gb_betinfo'=>json_encode($datas)
                    ];
                    (new GameBetsSon())->insertGetId($dataSon);
                }
            }
            $addAmount += $winAmount;
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if( !$bet ){
                if( in_array(strtoupper($resultType), ['WIN', 'LOSE', 'END']) ){
                    Db::rollback();
                    return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS');
                }
                //注单不存在则为下单和结算都是这个接口，写入注单并扣除用户余额
                $data = [
                    'gb_gid'=>$gid,
                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                    'gb_intime'=>$ts,
                    'gb_price'=>0,
                    'gb_sn'=>$betId,
                    'gb_winlose'=> 1,
                    'gb_bettime'=>$ts,
                    'gb_overtime'=>$ts,
                    'gb_trans_no'=>$externalTransactionId,
                    'gb_winprice'=> 0,
                    'gb_betinfo'=>json_encode($datas)
                ];
                $gbId = (new GameBets())->insertGetId($data);
                $bet = GameBets::where('gb_id', '=', $gbId)->find();
            }
            $bet->gb_overtime = $ts;
            if( !in_array(strtoupper($resultType), ['LOSE', 'END']) ) {
                $bet->gb_price += $betAmount;
                $bet->gb_winprice += $addAmount;
            }
            $bet->gb_winlose = $bet->gb_winprice > 0 ? 3 : 2;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($datas, '修改注单失败', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST');
            }
            //resultType：
            //                          -""WIN"" -  赢了，加额度
            //                          -""BET_WIN"" - 赢了，加额度 (需扣除下注额度)
            //                          -""BET_LOSE"" - 输了，扣额度 (需扣除下注额度)
            //                          -""LOSE"" - 无需作任何动作，无需加减额度
            //                          - ""END"" - 只是通知贵司该下注已结算，无需作任何动作，无需加减额度
            if( in_array(strtoupper($resultType), ['BET_WIN', 'BET_LOSE']) ){
                if ($betAmount > 0) {
                    $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//                if(!$rlt){
//                    Db::rollback();
//                    $this->pushLog($datas, '操作用户余额失败', $trdobj);
//                    return $this->returnData([], 'SC_INVALID_REQUEST');
//                }
                    $bet->gb_cjprice = $betcjprice;
                    $bet->save();
                }
            }
            if( in_array(strtoupper($resultType), ['WIN', 'BET_WIN']) ) {
                if ($addAmount > 0) {
                    //增加用户用户余额
                    $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $addAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                if( !$rlt ){
//                    Db::rollback();
//                    $this->pushLog($datas, '修改注单失败', $trdobj);
//                    return $this->returnData([], 'SC_INVALID_REQUEST');
//                }
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            //生成本次交易缓存
            $apiRedis->set($transactionId, 1, 86400);
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    //下注
    private function bet($datas, $trdobj, $cfg){
        Db::startTrans();
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            $externalTransactionId = $datas['externalTransactionId'];
            $transactionId = $datas['transactionId'];#交易id，不能重复
            $amount = $datas['amount'];
            $amount = Third::transExchangerate($amount, 0, 2);
            if( $amount < 0 ){
                Db::rollback();
                $this->pushLog($datas, '下注余额为负', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if( !$uinfo ){
                Db::rollback();
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( $amount > $userBalance ){
                Db::rollback();
                $this->pushLog($datas, '用户余额不足', $trdobj);
                return $this->returnData([], 'SC_INSUFFICIENT_FUNDS');
            }
            $apiRedis = new ApiRedis();
            //判断注单是否存在,存在直接返回
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if( $bet || $apiRedis->has($transactionId) ){
                Db::rollback();
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                return $this->returnData([
                    'balance' => (float)$balance,
                    'username' => $username
                ]);
            }
            $gid = $this->thirdClass->getGameIdByGameCode($gameCode);
            $ts = time();
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$betId,
                'gb_winlose'=> 1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_trans_no'=>$externalTransactionId,
                'gb_winprice'=> 0,
                'gb_betinfo'=>json_encode($datas)
            ];
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
        try {
            //扣减用户余额
            $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//            if( !$rlt ){
//                Db::rollback();
//                $this->pushLog($datas, '操作用户余额失败', $trdobj);
//                return $this->returnData([], 'SC_INVALID_REQUEST');
//            }
            //写入注单
            $data['gb_cjprice'] = $betcjprice;
            $res = (new GameBets())->insertGetId($data);
            if( !$res ){
                Db::rollback();
                $this->pushLog($datas, '写入注单错误', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            //生成本次交易缓存
            $apiRedis->set($transactionId, 1, 86400);
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    //获取用户余额
    private function balance($datas, $trdobj, $cfg){
        $recCode = $datas['username'];
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $recCode)->find();
        if( !$uinfo ){
            //用户不存在
            $this->pushLog($datas, '用户名不存在', $trdobj);
            return $this->returnData([], 'SC_USER_NOT_EXISTS');
        }
        Db::startTrans();
        $balance = (float)$this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        Db::commit();
        return $this->returnData([
            'username' => $recCode,
            'balance' => $balance,
        ]);
    }

    //通用返回
    private function returnData($data, $status = 'SC_OK'){
        $returnData = [
            // 'traceId' => $this->thirdClass->generateUUID(),
            'traceId' => $this->traceId,
            'status' => $status,
        ];
        if( $data ){
            $data['currency'] = $this->currency;
            $returnData['data'] = $data;
        }
        return $returnData;
    }

    protected function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid, 0, 0, 0, true);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

}