<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;
use think\facade\Request;

class Notify_bgs extends Notify
{
    private $thirds_name = 'bgs';

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
    private function ReturnData(bool $code = true, string $msg = '', array $data = []): array
    {
        $arr['is_success'] = $code;
        $arr['err_msg'] = $msg;
        $arr = array_merge($arr, $data);
        $this->PushLog('ReturnData', '返回数据', $arr);
        return $arr;
    }

    private function PushLog($action, $msg, $data, $file = null)
    {
        if (!$file) {
            $file = $this->thirds_name;
        }
        Utils::L($file, [$action, $msg, $data]);
    }

    //查询玩家信息
    function player_summary($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $action = 'player_summary';
        if (!$this->verify($data, $trdobj, $cfg)) {
            $this->PushLog($action, '签名错误', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'sign error');
        }
        $this->PushLog($action, 'data', $data);
        $arr = [];
        $msg = '';
        $user = UserModel::where(Meta::UFIELD['uname'], $data['username'])->field(Meta::UFIELD['uid'] . ',' . Meta::UFIELD['ubalance'])->find();
        if ($user) {
            $balance = $this->getBalance($user[Meta::UFIELD['uid']]);
            $code = true;
            $arr['username'] = $data['username'];
            $arr['balance'] = floatval($balance );
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
        } else {
            $code = false;
            $msg = 'user does not exist';
            $this->PushLog($action, '用户不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //验证
    function verify($data, $trdobj, $cfg)
    {
        $sign = $data['sign'];
        $key = $trdobj->MakeSign($cfg, $data);
        if ($sign == $key) return true; else return false;
    }


    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

    //下注
    function bet($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $action = 'bet';
        if (!$this->verify($data, $trdobj, $cfg)) {
            $this->PushLog($action, '签名错误', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'sign error');
        }
        $redis_key = $this->thirds_name . "_" . $action . "_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game_code'], 'tag', 'g_id');
        if (!$gid) {
            $this->PushLog($action, '游戏不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'Invalid argument');
        }
        $this->PushLog($action, 'data', $data);
        $ts = time();
        $res = [];
        $msg = '';
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['username'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if ($uinfo) {
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = $data['round_id'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid' => $gid, 'gb_sn' => $order_sn])->find();
                    if (!$bet) {
                        $amount = Third::transExchangerate($data['amount'], 0, 2);  //金额转换
                        $data_bet = [
                            'gb_tcid' => $cfg['trdcfg']['tc_id'],
                            'gb_gid' => $gid,
                            'gb_uid' => $uid,
                            'gb_intime' => $ts,
                            'gb_price' => $amount,
                            'gb_sn' => $order_sn,
                            'gb_winlose' => 2,
                            'gb_bettime' => $data['timestamp'],
                            'gb_overtime' => '',
                            'gb_winprice' => 0,
                            'gb_betinfo' => json_encode($data)
                        ];
                        if ($amount < $ubalance)
                        {
                            $betcjprice = 0;
                            if ($amount > 0) {
                                $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                            }
                            $data_bet['gb_cjprice'] = $betcjprice;
                            (new GameBets())->insert($data_bet);
                            $code = true;
                            Db::commit();
                        } else {
                            $code = false;
                            $msg = 'Not enough balance';
                            $this->PushLog($action, '用户:' . $uid . "余额不足", $data, "{$cfg['trdcfg']['tc_id']}/error");
                            Db::rollback();
                        }
                    } else {
                        $code = false;
                        $msg = 'Order already exists';
                        $this->PushLog($action, '订单已存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
                        Db::rollback();
                    }
                } else {
                    $code = false;
                    $msg = 'frequent requests';
                    $this->PushLog($action, '请求频繁', $data, "{$cfg['trdcfg']['tc_id']}/error");
                    Db::rollback();
                }
            } else {
                $code = false;
                $msg = 'Token expired';
                $this->PushLog($action, '用户信息不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = false;
            $msg = 'Other error';
            $this->PushLog($action, '过程错误', $ex->getMessage(), "{$cfg['trdcfg']['tc_id']}/error");
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ?? 0);
        $res['currency'] = $cfg['trdcfg']['tc_currency'];
        return $this->ReturnData($code, $msg, $res);
    }

    //结算
    function payout($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $action = 'payout';
        if (!$this->verify($data, $trdobj, $cfg)) {
            $this->PushLog($action, '签名错误', $data);
            return $this->ReturnData(false, 'sign error');
        }
        $redis_key = $this->thirds_name . "_" . $action . "_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game_code'], 'code', 'g_id');
        if (!$gid) {
            $this->PushLog($action, '游戏不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'Invalid argument');
        }
        $this->PushLog($action, 'data', $data);
        $res = [];
        $msg = '';
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['username'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if ($uinfo) {
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['round_id'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid' => $gid, 'gb_sn' => $order_sn, 'gb_winlose' => 2])->find();
                    if ($bet) {
                        $amount = Third::transExchangerate($data['amount'], 0, 2);
                        if ($data['amount'] > 0) {
                            $gb_winlose = 3;
                        } else {
                            $gb_winlose = 2;
                        }
                        if ($amount > 0) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                            User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                        }
                        $bet->gb_overtime = $data['timestamp'];
                        $bet->gb_winprice = $amount;
                        $bet->gb_winlose = $gb_winlose;
                        $bet->gb_betinfo = json_encode($data);
                        $bet->save();
                        $code = true;
                        Db::commit();
                    } else {
                        $code = false;
                        $msg = 'The order does not exist or the status is abnormal';
                        $this->PushLog($action, '订单不存在或状态异常', $data, "{$cfg['trdcfg']['tc_id']}/error");
                        Db::rollback();
                    }
                } else {
                    $code = false;
                    $msg = 'frequent requests';
                    $this->PushLog($action, '请求频繁', $data, "{$cfg['trdcfg']['tc_id']}/error");
                    Db::rollback();
                }
            } else {
                $code = false;
                $msg = 'User information does not exist';
                $this->PushLog($action, '用户信息不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = false;
            $msg = 'Other error';
            $this->PushLog($action, '过程错误', $ex->getMessage(), "{$cfg['trdcfg']['tc_id']}/error");
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ?? 0);
        $res['currency'] = $cfg['trdcfg']['tc_currency'];
        return $this->ReturnData($code, $msg, $res);
    }

    //回滚
    function refund($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $action = 'refund';
        if (!$this->verify($data, $trdobj, $cfg)) {
            $this->PushLog($action, '签名错误', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'sign error');
        }
        $redis_key = $this->thirds_name . "_" . $action . "_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game_code'], 'code', 'g_id');
        if (!$gid) {
            $this->PushLog($action, '游戏不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'Invalid argument');
        }
        $this->PushLog($action, 'data', $data);
        $res = [];
        $msg = '';
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['username'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if ($uinfo) {
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['round_id'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid' => $gid, 'gb_sn' => $order_sn, 'gb_winlose' => 2])->find();
                    if ($bet) {
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                        $rlt = User::accountChange($uid, $bet->gb_price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt) {
                            $bet->gb_overtime = $data['timestamp'];
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $code = true;
                            Db::commit();
//                        } else {
//                            $code = false;
//                            $msg = 'Other error';
//                            $this->PushLog($action, '用户:' . $uid . "注单取消失败", $data, "{$cfg['trdcfg']['tc_id']}/error");
//                            Db::rollback();
//                        }
                    } else {
                        $code = false;
                        $msg = 'Already accepted and cannot be canceled';
                        $this->PushLog($action, '订单不存在或状态异常', $data, "{$cfg['trdcfg']['tc_id']}/error");
                        Db::rollback();
                    }
                } else {
                    $code = false;
                    $msg = 'frequent requests';
                    $this->PushLog($action, '请求频繁', $data, "{$cfg['trdcfg']['tc_id']}/error");
                    Db::rollback();
                }
            } else {
                $code = false;
                $msg = 'User information does not exist';
                $this->PushLog($action, '用户信息不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $code = false;
            $msg = 'Other error';
            $this->PushLog($action, '过程错误', $ex->getMessage(), "{$cfg['trdcfg']['tc_id']}/error");
            Db::rollback();
        }
        $res['balance'] = $this->getBalance($uid ?? 0);
        $res['currency'] = $cfg['trdcfg']['tc_currency'];
        return $this->ReturnData($code, $msg, $res);
    }

    //本局游戏结束后触发
    function settle($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $action = 'settle';
        if (!$this->verify($data, $trdobj, $cfg)) {
            $this->PushLog($action, '签名错误', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'sign error');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['game_code'], 'code', 'g_id');
        if (!$gid) {
            $this->PushLog($action, '游戏不存在', $data, "{$cfg['trdcfg']['tc_id']}/error");
            return $this->ReturnData(false, 'Invalid argument');
        }
        $this->PushLog($action, 'data', $data);
        $bet = GameBets::where(['gb_gid' => $gid, 'gb_sn' => $data['round_id']])->find();
        if($bet){
            $code = true;$msg = '';
        }else{
            $code = false;$msg = 'Note does not exist';
        }
        return $this->ReturnData($code, $msg);
    }
}
