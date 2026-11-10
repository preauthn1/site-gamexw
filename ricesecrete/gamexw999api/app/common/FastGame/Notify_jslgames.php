<?php

namespace app\common\FastGame;

use app\api\redis\ApiRedis;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsSon, GamePayLogs, Games, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;
use app\common\lib\Util;

class Notify_jslgames extends Notify {

    private $tcid = 0;

    private $thirdClass = null;

    private $traceId = null;


    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj, $cfg) {
        $param = $datas['param'];
        // 获取回调对应方法
        $s = $param['s'];
        $sArr = explode('/', $s);
        $action = $sArr[count($sArr) - 1];
        if (method_exists($this, $action)) {
            return $this->$action($param, $trdobj, $cfg);
        } else {
            throw new \Exception("回调地址错误");
        }
    }

    public function wallet($datas, $trdobj, $cfg) {
        $datas = $datas['param'] ?? $datas;
        $this->tcid = $cfg['trdcfg']['tc_id'];
        $this->traceId = $datas['traceId'] ?? '';
        $this->pushLog($datas, 'wallet', [$trdobj, $cfg]);
        $s_arr = explode('wallet/', $datas['s']);
        $this->pushLog($datas, $s_arr[1], $trdobj);
        //验证token
        $sign = $datas['sign'];
        $_sign = $trdobj->CheckSign($cfg, $datas);
        // 获取余额没有对应平台信息，无法校验
        if ($sign !== $_sign) {
            return $this->returnData([], 'sign error');
        }
        //验证货币
        $this->currency = strtoupper($datas['currency']);
        if ($this->currency != strtoupper($cfg['trdcfg']['tc_currency'])) {
            $this->pushLog($datas, ['currency error', $datas['currency']], $trdobj);
            return $this->returnData([], 'currency error');
        }
        switch ($s_arr[1]) {
            case 'balance':
                //获取余额
                return $this->balance($datas, $trdobj, $cfg);
            case 'bet':
                //下注交易，从钱包余额中扣除金额
                return $this->bet($datas, $trdobj, $cfg);
            case 'bets':
                //批量下注交易，从钱包余额中扣除金额
                return $this->bets($datas, $trdobj, $cfg);
            case 'bet_result':
                //下注交易请求，从用户余额中借记和/或贷记资金
                return $this->bet_result($datas, $trdobj, $cfg);
            case 'rollback':
                //对下注交易的回滚操作
                return $this->betRollback($datas, $trdobj, $cfg);
        }
        $this->pushLog($datas, '未找到对应方法', $trdobj);
        return $this->returnData([], 'SC_INVALID_REQUEST', 'error function');
    }

    //取消
    private function betRollback($datas, $trdobj, $cfg) {
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS', 'USER NOT EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if (!$bet) {
                //注单不存在
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS', 'bet is exist');
            }
            if ($bet->gb_winlose == 0) {
                return $this->returnData([
                    'balance' => (float)$userBalance,
                    'username' => $username
                ]);
            }
            //计算该退回给用户多少钱
            $userAddAmount = $bet->gb_price - $bet->gb_winprice;
            if ($userAddAmount > 0) {
                $symbol = '+';
                $type = 3;
            } else {
                $symbol = '-';
                $type = 4;
                $userAddAmount = abs($userAddAmount);
                if ($userAddAmount > $userBalance) {
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    return $this->returnData([], 'SC_INSUFFICIENT_FUNDS', 'INSUFFICIENT FUNDS');
                }
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'code', 'g_id');

        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INTERNAL_ERROR', 'system error');
        }

        Db::startTrans();
        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                return $this->returnData([], 'SC_INTERNAL_ERROR', 'save bet error');
            }
            if ($userAddAmount > 0) {
                //增加或减少用户余额
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '注单回滚');
                if ($symbol == '+') {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                } else {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg);
                }
//                if( !$rlt ){
//                    Db::rollback();
//                    $this->pushLog($datas, '取消注单余额更新失败', $trdobj);
//                    return $this->returnData([], 'SC_INVALID_REQUEST');
//                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INTERNAL_ERROR', 'system error');
        }

        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        return $this->returnData([
            'balance' => (float)$balance,
            'username' => $username
        ]);
    }

    //派彩
    private function bet_result($datas, $trdobj, $cfg) {
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            $betAmount = $datas['betAmount'];#下注金额
            $betAmount = Third::transExchangerate($betAmount, 0, 2);
            $winAmount = $datas['winAmount'];#派彩金额
            $winAmount = Third::transExchangerate($winAmount, 0, 2);
            $isFreespin = $datas['isFreespin'];#是否是免费次数
            $isEndRound = $datas['isEndRound'];#免费次数是否用完
            $isBet = $datas['isBet'];# 1表示有下注请求，2表示没下注请求，直接结算

            if ($winAmount < 0) {
                $this->pushLog($datas, '结算金额为负', $trdobj);
                return $this->returnData([], 'SC_INVALID_REQUEST', 'amount error');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS', 'USER NOT EXISTS');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if ($betAmount == 0 && $winAmount == 0) {
                return $this->returnData([
                    'balance' => (float)$balance,
                    'username' => $username
                ]);
            }
            if ($isBet == 1 && $betAmount > $balance) {
                $this->pushLog($datas, '用户余额不足', $trdobj);
                return $this->returnData([], 'SC_INSUFFICIENT_FUNDS', 'INSUFFICIENT FUNDS');
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'code', 'g_id');
            $ts = time();
        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }

        Db::startTrans();
        try {
            $addAmount = 0;
            //判断是否是免费游戏
            if ($isFreespin) {
                //判断免费游戏是否结束
                if ($isEndRound) {
                    //结束了统计子注单后更改主订单状态
                    $addAmount = GameBetsSon::where('gb_sn', '=', $betId)->sum('gb_winprice');
                } else {
                    //没有结束操作子注单
                    $dataSon = [
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_gid' => $gid,
                        'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                        'gb_intime' => $ts,
                        'gb_price' => 0,
                        'gb_sn' => $betId,
                        'gb_winlose' => $winAmount > 0 ? 3 : 2,
                        'gb_bettime' => $ts,
                        'gb_overtime' => $ts,
                        'gb_winprice' => $winAmount,
                        'gb_betinfo' => json_encode($datas)
                    ];
                    (new GameBetsSon())->insertGetId($dataSon);
                }
            } else {
                // 不是免费游戏直接结算
                $addAmount = $winAmount;
            }
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if ($isBet == 2 && empty($bet)) {
                return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS');
            }
            if (!$bet) {
                //注单不存在则为下单和结算都是这个接口，写入注单并扣除用户余额
                $data = [
                    'gb_gid' => $gid,
                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
                    'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                    'gb_intime' => $ts,
                    'gb_price' => 0,
                    'gb_sn' => $betId,
                    'gb_winlose' => 1,
                    'gb_bettime' => $ts,
                    'gb_overtime' => $ts,
                    'gb_winprice' => 0,
                    'gb_betinfo' => json_encode($datas)
                ];
                $gbId = (new GameBets())->insertGetId($data);
                $bet = GameBets::where('gb_id', '=', $gbId)->find();
            }
            $bet->gb_overtime = $ts;
            if ($isBet == 1) {
                $bet->gb_price += floatval($betAmount);
            }
            $bet->gb_winprice += $addAmount;
            $bet->gb_winlose = $bet->gb_winprice > 0 ? 3 : 2;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '修改注单失败', $trdobj);
                return $this->returnData([], 'SC_INTERNAL_ERROR', 'update bet error');
            }

            // 下注才扣款
            if ($betAmount > 0 && $isBet == 1) {
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
            Db::commit();
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST', 'system error');
        }
    }

    //下注
    private function bet($datas, $trdobj, $cfg) {
        try {
            $username = $datas['username'];
            $betId = $datas['betId'];
            $gameCode = $datas['gameCode'];
            $amount = $datas['amount'];
            $amount = Third::transExchangerate($amount, 0, 2);
            if ($amount < 0) {
                $this->pushLog($datas, '下注余额为负', $trdobj);
                return $this->returnData([], 'amount is negative');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS', 'USER NOT EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if ($amount > $userBalance) {
                $this->pushLog($datas, '用户余额不足', $trdobj);
                return $this->returnData([], 'INSUFFICIENT FUNDS');
            }
            //判断注单是否存在,存在直接返回
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if ($bet) {
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                return $this->returnData([
                    'balance' => (float)$balance,
                    'username' => $username
                ]);
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'code', 'g_id');
            $ts = time();
            $data = [
                'gb_gid' => $gid,
                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                'gb_intime' => $ts,
                'gb_price' => $amount,
                'gb_sn' => $betId,
                'gb_winlose' => 1,
                'gb_bettime' => $ts,
                'gb_overtime' => $ts,
                'gb_winprice' => 0,
                'gb_betinfo' => json_encode($datas)
            ];
        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'system error');
        }
        Db::startTrans();
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
            if (!$res) {
                Db::rollback();
                $this->pushLog($datas, '写入注单错误', $trdobj);
                return $this->returnData([], 'bet insert error');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    private function bets($datas, $trdobj, $cfg) {
        $orders = $datas['orders'] ?? [];
        if (empty($orders)) {
            return $this->returnData([], 'SC_INVALID_REQUEST', '缺少订单数据');
        }
        $username = $datas['username'];
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
        if (!$uinfo) {
            //用户不存在
            $this->pushLog($datas, '用户名不存在', $trdobj);
            return $this->returnData([], 'SC_USER_NOT_EXISTS', 'USER NOT EXISTS');
        }
        $total = 0;
        $dataAll = [];
        foreach ($orders as $order) {
            $betId = $order['betId'];
            $gameCode = $order['gameCode'];
            $amount = Third::transExchangerate($order['amount'], 0, 2);
            if ($amount < 0) {
                return $this->returnData([], 'SC_INVALID_REQUEST', 'amount is negative');
            }
            $total += floatval($amount);
            // 判断注单是否存在,存在直接返回
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if ($bet) {
                return $this->returnData([], 'SC_TRANSACTION_DUPLICATED', '注单重复');
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'code', 'g_id');
            if (empty($gid)) {
                return $this->returnData([$gameCode, $cfg['trdcfg']['tc_id']], 'SC_INVALID_GAME', '游戏代码无效');
            }
            $ts = time();
            $dataAll[] = [
                'gb_gid' => $gid,
                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                'gb_intime' => $ts,
                'gb_price' => $amount,
                'gb_sn' => $betId,
                'gb_winlose' => 1,
                'gb_bettime' => $ts,
                'gb_overtime' => $ts,
                'gb_winprice' => 0,
                'gb_betinfo' => json_encode($datas)
            ];
        }
        $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        if ($total > $userBalance) {
            return $this->returnData([], 'SC_INSUFFICIENT_FUNDS', 'INSUFFICIENT FUNDS');
        }

        Db::startTrans();
        try {
            foreach ($dataAll as $k => $data) {
                //扣减用户余额
                $msg = UserModel::betRemarks($data['gb_sn'], $cfg['trdcfg']['tc_name'], $data['gb_gid']);
                $bet_cj_price = User::accountChange($uinfo[Meta::UFIELD['uid']], $data['gb_price'], '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $data['gb_gid']);
                //彩金投注金额
                $dataAll[$k]['gb_cjprice'] = $bet_cj_price;
            }
            $res = (new GameBets())->insertAll($dataAll);
            if (!$res) {
                Db::rollback();
                $this->pushLog($datas, '写入注单错误', $trdobj);
                return $this->returnData([], 'SC_UNKNOWN_ERROR', 'bet insert error');
            }
            Db::commit();
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), [$trdobj], 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    //获取用户余额
    private function balance($datas, $trdobj, $cfg) {
        $recCode = $datas['username'];
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $recCode)->find();
        if (!$uinfo) {
            //用户不存在
            $this->pushLog($datas, '用户名不存在', $trdobj);
            return $this->returnData([], 'user no exist');
        }
        return $this->returnData([
            'username' => $recCode,
            'balance' => (float)$this->getUserBalance($uinfo[Meta::UFIELD['uid']]),
            'currency' => strtoupper($cfg['trdcfg']['tc_currency'])
        ]);
    }

    //通用返回
    private function returnData($data, $status = 'SC_OK', $msg = '') {
        $returnData = [
            'traceId' => $this->traceId,
            'status' => $status,
            'msg' => $msg
        ];
        if ($data) {
            $returnData['data'] = $data;
        }
        return $returnData;
    }

    protected function pushLog($action, $msg, $data, $dir = 'log') {
        Utils::L($this->tcid . '/' . $dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid) {
        try {
            $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
            $balance = Third::transExchangerate($balance, 1);
        } catch (\Exception $e) {
            $this->pushLog('error', ['getUserBalance', $e->getMessage(), $e->getTraceAsString()], [$uid]);
            $balance = 0;
        }
        return $balance;
    }

}