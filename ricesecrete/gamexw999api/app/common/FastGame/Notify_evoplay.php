<?php

namespace app\common\FastGame;

use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Notify;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\User;
use app\common\FastGame\Base\Utils;
use app\common\model\UserModel;
use app\model\GameBets;
use app\model\GameBetsSon;
use think\facade\Db;

class Notify_evoplay extends Notify
{

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
        $param = $datas['param'];
        unset($param['s']);unset($param['action_method']);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify-param:'.json_encode($param)]);
        $action = $param['name'];
        $token = $param['token'];
        list($t, $uid) = explode(':',$token);
        $signflag = $trdobj->CheckSign($cfg,$param);
        if($signflag){
            $res = $this->$action($param, $trdobj, $cfg, $uid);
            return $res;
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:'.$action, '签名错误', $param]);
            abort(403, 'Signature error');
        }
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
        return $this->getBalance($uid, $cfg);
    }

    function refund($data, $trdobj, $cfg, $uid){
        $order = $data['data'];
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $details = json_decode($order['details'], true);
        if(!isset($details['game'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:refund','错误GAME',$data]);
            abort(403, 'refund error');
        }
        $game = $details['game'];
        if(!isset($game['game_id'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:refund','错误:game_id',$data]);
            abort(403, 'refund error');
        }
        $evoplayame = $game['game_id'];
        $ts=time();
        Db::startTrans();
        try {
            $round_id = $order['refund_round_id'];
            $action_id = $order['refund_action_id'];
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$evoplayame, 'tag', 'g_id');
            if ($gid) {
                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                if ($bet) {
                    $amount = $bet['gb_price'];
                    if($amount > 0){
                        $aflag = Redis::get("evoplay_betactionid_" . $gid . "_" . $round_id . "_" .$action_id);
                        if (!$aflag) {
                            Redis::setex("evoplay_betactionid_" . $gid . "_" . $round_id . "_" .$action_id, 1, 60);//60秒内不接受重得action
                            $amount = Third::transExchangerate($amount,0);
                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                            if ($rlt){
                                $bet->gb_betinfo = json_encode($data);
                                $bet->gb_overtime = $ts;
                                $bet->gb_winlose = 0;
                                $bet->save();
//                            }else{
//                                Db::rollback();
//                                abort(403, 'run error');
//                            }
                        }
                    }
                }
                Db::commit();
            } else {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:refund','游戏标志错误',$evoplayame]);
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:refund','过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $return = $this->getBalance($uid, $cfg);
        return $return;
    }

    function win($data, $trdobj, $cfg, $uid){
        $order = $data['data'];
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $details = json_decode($order['details'], true);
        if(!isset($details['game'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:win','错误GAME',$data]);
            return $this->getBalance($uid, $cfg);
        }
        $game = $details['game'];
        if(!isset($game['game_id'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:win','错误:game_id',$data]);
            return $this->getBalance($uid, $cfg);
        }
        $evoplayame = $game['game_id'];
        $action = isset($game['action'])?$game['action']:"";
        $ts=time();
        Db::startTrans();
        try {
            $round_id = $order['round_id'];
            $action_id = $order['action_id'];
            $final_action = $order['final_action']??0;
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$evoplayame, 'tag', 'g_id');
            if ($gid) {
                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                if ($bet) {
                    $amount = $order['amount'];
                    $amount = Third::transExchangerate($amount,0);

                    /* 更新子订单*/
                    $bet_son = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_sn_ac', $action_id)->find();
                    if ($bet_son) {
                        $amounts = $bet_son->gb_winprice + $amount;
                        $bet_son->gb_overtime = $ts;
                        if($amounts > 0){
                            $bet_son->gb_winlose = 3;
                            $bet_son->gb_winprice = $amounts;
                        } else {
                            $bet_son->gb_winlose = 2;
                        }
                        $bet_son->save();
                    } else {
                        $data_son=[
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_sn'=>$round_id,
                            'gb_sn_ac' => $action_id,
                            'gb_winlose'=>($amount > 0 ?3:2),
                            'gb_winprice' => $amount,
                            'gb_bettime'=>$ts,
                            'gb_overtime'=>$ts,
                            'gb_action'=>$action,
                            'gb_betinfo'=>json_encode($data)
                        ];
                        (new GameBetsSon())->insertGetId($data_son);
                    }
                    $count = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where("gb_action","setdouble")->count();
                    if($count > 0){
                        $money = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where("gb_action","setdouble")->order("gb_id desc")->value('gb_winprice');
                    } else {
                        $money = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 3)->sum('gb_winprice');
                        $gb_price = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where([["gb_action", "like","%double%"]])->sum('gb_price');
                        $money = $money - $gb_price;
                    }
                    /* 本轮结束时 更新总订单 给用户返钱 */
                    if($final_action == 1){
//                        $gb_price_total = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 2)->sum('gb_price');
//                        if ($gb_price_total != $bet->gb_price) {
//                            $bet->gb_price = $gb_price_total;
//                        }
                        if($money > 0){
                            $aflag = Redis::get("evoplay_betactionid_win_" . $gid . "_" . $round_id . "_" .$action_id);
                            if (!$aflag) {
                                Redis::setex("evoplay_betactionid_win_" . $gid . "_" . $round_id . "_" .$action_id, 1, 60);//60秒内不接受重得action
                                $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                $rlt = User::accountChange($uid, $money, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                if ($rlt){
                                    $bet->gb_overtime = $ts;
                                    $bet->gb_winlose = 3;
                                    $bet->gb_winprice = $money;
                                    $bet->gb_betinfo = json_encode($data);
                                    $bet->save();
//                                }else{
//                                    Db::rollback();
//                                }
                            }
                        } else {
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 2;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                        }
                    }
                }
                Db::commit();
            } else {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:win','游戏标志错误',$evoplayame]);
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:win','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }

        $return = $this->getBalance($uid, $cfg);
        if (isset($money) && $money > 0 && $final_action != 1) {
            $return['data']['balance'] += $money;
        }
        return $return;
    }

    function bet($data, $trdobj, $cfg, $uid){
        $order = $data['data'];
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $third = $data['third'];
        $details = json_decode($order['details'], true);
        if(!isset($details['game'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误GAME',$data]);
            return $this->getBalance($uid, $cfg);
        }
        $game = $details['game'];
        if(!isset($game['game_id'])){
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误:game_id',$data]);
            return $this->getBalance($uid, $cfg);
        }
        $is_flag = 0;
        $evoplayame = $game['game_id'];
        $action = $game['action'];
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uinfo=$uinfo->toArray();
                $round_id = $order['round_id'];
                $action_id = $order['action_id'];
                $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$evoplayame, 'tag', 'g_id');
                if ($gid) {
                    $aflag = Redis::get("evoplay_betactionid_" . $gid . "_" . $round_id . "_" .$action_id);
                    if (!$aflag) {
                        Redis::setex("evoplay_betactionid_" . $gid . "_" . $round_id . "_" .$action_id, 1, 60);//60秒内不接受重得action
                        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                        $amount = $order['amount'];
                        $amount = Third::transExchangerate($amount,0);
                        if (!$bet) {
                            if ($amount < $userBalance) {
                                $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                $data=[
                                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                    'gb_gid'=>$gid,
                                    'gb_uid'=>$uid,
                                    'gb_intime'=>$ts,
                                    'gb_price'=>$amount,
                                    'gb_cjprice'=>$betcjprice,
                                    'gb_sn'=>$round_id,
                                    'gb_winlose'=>1,
                                    'gb_bettime'=>$ts,
                                    'gb_overtime'=>$ts,
                                    'gb_winprice'=>0,
                                    'gb_betinfo'=>json_encode($data)
                                ];

                                //写入注单
                                $rlt=(new GameBets())->insertGetId($data);
                                if (!$rlt){
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误:插入订单失败',$data]);
                                } else {
                                    $data_son = $data;
                                    $data_son['gb_sn_ac'] = $action_id;
                                    $data_son['gb_action'] = $action;
                                    (new GameBetsSon())->insertGetId($data_son);
                                }
                            } else {
                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误:余额不足',$data]);
                                $is_flag = 1;
                            }
                        } else {
                            $bet_son = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_sn_ac', $action_id)->find();
                            if (!$bet_son) {
                                if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {
                                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    $data_son=[
                                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                        'gb_gid'=>$gid,
                                        'gb_uid'=>$uid,
                                        'gb_intime'=>$ts,
                                        'gb_price'=>$amount,
                                        'gb_cjprice'=>$betcjprice,
                                        'gb_sn'=>$round_id,
                                        'gb_sn_ac' => $action_id,
                                        'gb_winlose'=>1,
                                        'gb_bettime'=>$ts,
                                        'gb_overtime'=>$ts,
                                        'gb_winprice'=>0,
                                        'gb_action'=>$action,
                                        'gb_betinfo'=>json_encode($data)
                                    ];
                                    $rlt = (new GameBetsSon())->insertGetId($data_son);
                                    if (!$rlt){
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误:插入订单失败',$data]);
                                    }
                                    $bet->gb_cjprice += $betcjprice;
                                    $bet->gb_price += $amount;
                                    $bet->gb_betinfo = json_encode($data);
                                    $bet->save();
                                } else {
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:bet','错误:余额不足',$data]);
                                    $is_flag = 1;
                                }
                            }
                        }
                    }
                    Db::commit();
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','游戏标志错误',$evoplayame]);
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','用户错误',$uid]);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        if($is_flag == 0){
            $return = $this->getBalance($uid, $cfg);
        } else {
            $return = ['status' => "error", "error" => ['scope' => "user", "no_refund" => 1, "message" => "Not enough money"]];
        }
        return $return;
    }

    function getBalance($uid, $cfg){
        $balance = $this->getUserBalance($uid);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => $cfg['trdcfg']['tc_currency']]];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['return-param:'.json_encode($return)]);
        return $return;
    }

    function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}