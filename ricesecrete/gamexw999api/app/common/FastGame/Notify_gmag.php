<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Notify;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\User;
use app\common\FastGame\Base\Utils;
use app\common\model\UserModel;
use app\model\GameAward;
use app\model\GameBets;
use app\model\Games;
use app\model\Users;
use app\model\UserThirds;
use think\facade\Db;

class Notify_gmag extends Notify
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
        $action = $param['name'];
        $token = $param['token'];
        list($t, $uid) = explode(':',$token);
        $signflag = $trdobj->CheckSign($cfg,$param);
        if($signflag){
            $res = $this->$action($param, $trdobj, $cfg, $uid);
            return $res;
        } else {
            Utils::L($param['third'],['notify:'.$action,'签名错误',$cfg]);
            abort(403, 'Signature error');
        }
    }

    function auth($datas, $trdobj,$cfg) {
        $sign = $datas['param']['hash'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",'notify:'.json_encode($datas)]);
        unset($datas['param']['s'],$datas['param']['third'],$datas['param']['action_method'],$datas['param']['hash']);
        $hash = $trdobj->MakeSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserThirds::where('ut_token', $datas['param']['token'])->find();
            if ($utuser) {
                $user_info = User::getUserData($utuser->ut_uid);
                $return = [
                    'requestId' => $datas['param']['requestId'], 
                    "playerSessionId" => Utils::random(20), 
                    'playerId' => $utuser->ut_trdname, 
                    'currency' => 'USD', 
                    'balance' => $this->getUserBalance($user_info[Meta::UFIELD['uid']]),
                    'country' => 'US', 
                    'error' => 0, 
                    'message' => 'success'
                ];
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",json_encode($return)]);
                return $return;
            } else {
                return ['requestId' => $datas['param']['requestId'], 'error' => 'P_06', 'message' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",'Signature error']);
            return ['requestId' => $datas['param']['requestId'], 'error' => 'P_02', 'message' => 'Invalid hash'];
        }
    }

    function transaction($datas, $trdobj,$cfg) {
        $sign = $datas['param']['hash'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",'notify:'.json_encode($datas)]);
        unset($datas['param']['s'],$datas['param']['action_method'],$datas['param']['hash']);
        $hash = $trdobj->MakeSign($cfg, $datas['param']);
        if ($hash = $sign) {
            $utuser = UserThirds::where('ut_trdname', $datas['param']['playerId'])->find();
            if ($utuser) {
                foreach ($datas['param']['trans'] as $key => $val) {
                    if ($val['transType'] == 'bet') {
                        return $this->bet($val, $trdobj, $cfg, $utuser->ut_uid, $datas['param']['gameCode'], $datas['param']['requestId']);
                    } else if ($val['transType'] == 'win') {
                        return $this->win($val, $trdobj, $cfg, $utuser->ut_uid, $datas['param']['gameCode'], $datas['param']['requestId']);
                    } else if ($val['transType'] == 'cancel') {
                        return $this->refund($val, $trdobj, $cfg, $utuser->ut_uid, $datas['param']['gameCode'], $datas['param']['requestId']);
                    }
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",'Token error']);
                return ['requestId' => $datas['param']['requestId'], 'error' => 'P_06', 'message' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",'Signature error']);
            return ['requestId' => $datas['param']['requestId'], 'error' => 'P_02', 'message' => 'Invalid hash'];
        }
    }
    
    function balance($datas, $trdobj,$cfg) {
        $sign = $datas['param']['hash'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["balance",'notify:'.json_encode($datas)]);
        unset($datas['param']['s'],$datas['param']['third'],$datas['param']['action_method'],$datas['param']['hash']);
        $hash = $trdobj->MakeSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserThirds::where('ut_trdname', $datas['param']['playerId'])->find();
            if ($utuser) {
                $user_info = User::getUserData($utuser->ut_uid);
                $return = [
                    'requestId' => $datas['param']['requestId'], 
                    'currency' => 'USD', 
                    'balance' => $this->getUserBalance($user_info[Meta::UFIELD['uid']]),
                    'error' => 0, 
                    'message' => 'success'
                ];
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["balance",json_encode($return)]);
                return $return;
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["balance",'Token error']);
                return ['requestId' => $datas['param']['requestId'], 'error' => 'P_06', 'message' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["balance",'Signature error']);
            return ['requestId' => $datas['param']['requestId'], 'error' => 'P_02', 'message' => 'Invalid hash'];
        }
    }

    function payUp($datas, $trdobj,$cfg) {
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $ts=time();
        $sign = $datas['param']['hash'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["payUp",'notify:'.json_encode($datas)]);
        unset($datas['param']['s'],$datas['param']['third'],$datas['param']['action_method'],$datas['param']['hash']);
        $hash = $trdobj->MakeSign($cfg, $datas['param']);
        if ($hash == $sign) {
            Db::startTrans();
            try {
                $utuser = UserThirds::where('ut_trdname', $datas['param']['playerId'])->find();
                if ($utuser) {
                    $award = GameAward::where('gaw_uid', $utuser->ut_uid)->where('gaw_sn', $datas['param']['transId'])->find();
                    if (!$award) {
                        $amount = bcdiv($datas['param']['amount'],$rate,2);
                        $msg = UserModel::betRemarks($datas['param']['requestId'], $cfg['trdcfg']['tc_name'], 0, '游戏奖金');
                        $rlt = User::accountChange($utuser->ut_uid, $amount, '+', 2, $msg);
                        $data=[
                            'gaw_uid'=>$utuser->ut_uid,
                            'gaw_intime'=>$ts,
                            'gaw_sn'=>$datas['param']['transId'],
                            'gaw_winprice'=>$amount,
                        ];

                        $rlt1=(new GameAward())->insertGetId($data);
                        if ($rlt1){
                            $balance = $this->getUserBalance($utuser->ut_uid);
                            $return = [
                                'requestId' => $datas['param']['requestId'],
                                'currency' => 'USD',
                                'balance' => $balance,
                                'error' => 0,
                                'message' => 'success'
                            ];
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",json_encode($return)]);
                        } else {
                            Db::rollback();
                            $return = [
                                'requestId' => $datas['param']['requestId'],
                                'error' => 'P_01',
                                'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
                            ];
                        }
                    } else {
                        $balance = $this->getUserBalance($utuser->ut_uid);
                        $return = [
                            'requestId' => $datas['param']['requestId'],
                            'currency' => 'USD', 
                            'balance' => $balance, 
                            'error' => 0, 
                            'message' => 'success'
                        ];
                    }
                    Db::commit();
                    return $return;
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["payUp",'Token error']);
                    return ['requestId' => $datas['param']['requestId'], 'error' => 'P_06', 'message' => 'Invalid token or token expired'];
                }
            } catch (\Exception $ex) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:refund','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
                Db::rollback();
                return [
                    'requestId' => $datas['param']['requestId'],
                    'error' => 'P_01', 
                    'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
                ];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["payUp",'Signature error']);
            return ['requestId' => $datas['param']['requestId'], 'error' => 'P_02', 'message' => 'Invalid hash'];
        }
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
        $balance = $this->getUserBalance($uid);
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $balance = bcmul($balance, $rate, 2);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => "USD"]];
        return $return;
    }
    
    function refund($data, $trdobj, $cfg, $uid, $game_code, $requestId){
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $ts=time();
        Db::startTrans();
        try {
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$game_code, 'tag', 'g_id');
            if ($gid) {
                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['referenceId'])->where('gb_winlose', 1)->find();
                if ($bet) {
                    $amount = bcdiv($data['amount'],$rate,2);
                    $msg = UserModel::betRemarks($data['referenceId'], $cfg['trdcfg']['tc_name'], $gid, '取消');
                    $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                    $bet->gb_betinfo = json_encode($data);
                    $bet->gb_overtime = $ts;
                    if ($bet->gb_price - $amount > 0) {
                        $bet->gb_price = $bet->gb_price - $amount;
                    } else {
                        $bet->gb_winlose = 0;
                    }
                    $bet->gb_betinfo = json_encode($data);
                    $bet->save();
                }
                $balance = $this->getUserBalance($uid);
        
                $return = [
                    'requestId' => $requestId,
                    'currency' => 'USD', 
                    'balance' => $balance, 
                    'error' => 0, 
                    'message' => 'success'
                ];
                Db::commit();
                return $return;
            } else {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:refund','游戏标志错误',$game_code]);
                return [
                    'requestId' => $requestId,
                    'error' => 'G_01', 
                    'message' => 'Game not found'
                ];
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:refund','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return [
                'requestId' => $requestId,
                'error' => 'P_01', 
                'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
            ];
        }
    }
    
    function win($data, $trdobj, $cfg, $uid, $game_code, $requestId){
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $ts=time();
        Db::startTrans();
        try {
            $round_id = $data['roundId'];
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$game_code, 'tag', 'g_id');
            if ($gid) {
                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                if (!$bet) {
                    Db::rollback();
                    $return = [
                        'requestId' => $requestId,
                        'error' => 'P_08', 
                        'message' => 'Invalid session, the session does not exist'
                    ];
                } else {
                    $gb_winprice = bcdiv($data['amount'],$rate,2);

                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                    $rlt = User::accountChange($uid, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                    $bet->gb_overtime = $ts;
                    $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                    $bet->gb_winprice = $gb_winprice;
                    $bet->gb_betinfo = json_encode($data);
                    $bet->save();
                }
                $balance = $this->getUserBalance($uid);
                $return = [
                    'requestId' => $requestId,
                    'currency' => 'USD', 
                    'balance' => $balance, 
                    'error' => 0, 
                    'message' => 'success'
                ];
                Db::commit();
                return $return;
            } else {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:win','游戏标志错误',$game_code]);
                return [
                    'requestId' => $requestId,
                    'error' => 'G_01', 
                    'message' => 'Game not found'
                ];
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:win','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return [
                'requestId' => $requestId,
                'error' => 'P_01', 
                'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
            ];
        }
    }
    
    function bet($data, $trdobj, $cfg, $uid, $game_code, $requestId){
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uinfo=$uinfo->toArray();
                $round_id = $data['roundId'];
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$game_code, 'tag', 'g_id');
                if ($gid) {
                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                    $balance = $this->getUserBalance($uid);
                    if ($bet) {
                        return [
                            'requestId' => $requestId,
                            'currency' => 'USD', 
                            'balance' => $balance,
                            'bonusBalance' => 0,
                            'error' => 0, 
                            'message' => 'success'
                        ];
                    }
                    
                    $amount = $data['amount'];
                    $amount = bcdiv($amount,$rate,2);
                    if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {
                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        $datas=[
                            'gb_gid'=>$gid,
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
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
                        $rlt=(new GameBets())->insertGetId($datas);
                        if ($rlt){
                            $return = [
                                'requestId' => $requestId,
                                'currency' => 'USD',
                                'balance' => $this->getUserBalance($uid),
                                'error' => 0,
                                'message' => 'success'
                            ];
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["auth",json_encode($return)]);
                        } else {
                            Db::rollback();
                            $return = [
                                'requestId' => $requestId,
                                'error' => 'P_01',
                                'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
                            ];
                        }
                    } else {
                        $return = [
                            'requestId' => $requestId,
                            'error' => 'T_01', 
                            'message' => 'Player Insufficient Funds'
                        ];
                    }
                    
                    Db::commit();
                    return $return;
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','游戏标志错误',$game_code]);
                    return [
                        'requestId' => $requestId,
                        'error' => 'G_01', 
                        'message' => 'Game not found'
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','用户错误',$uid]);
                Db::rollback();
                return [
                    'requestId' => $requestId,
                    'error' => 'P_04', 
                    'message' => 'Player not found'
                ];
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return [
                'requestId' => $requestId,
                'error' => 'P_01', 
                'message' => 'Invalid request. This error can be returned if required parameters are missing or they have incorrect format'
            ];
        }
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
