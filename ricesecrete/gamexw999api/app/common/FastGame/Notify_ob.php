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

class Notify_ob extends Notify
{
    private $game_code = 'ob_game';

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
            Utils::L("{$cfg['trdcfg']['tc_id']}/error",['notify:'.$action,'签名错误',$cfg]);
            abort(403, 'Signature code');
        }
    }

    function GetBalance($datas, $trdobj,$cfg) {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['balance', 'notify:'.json_encode($datas)]);
        $s_arr = explode( 'GetBalance/', $datas['param']['s']);
        $username = $s_arr[1];
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($username, 0, -3))->value('id');
                if ($utuser) {
                    $balance = $this->getUserBalance($utuser);
                    $return = [
                        'resultCode' => 0,
                        'version' => 1, 
                        'balance' => $balance > 0 ? $balance : 0,
                        'message' => '成功',
                    ];
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['balance', json_encode($return)]);
                    return $return;
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['balance', 'Token code']);
                    return ['resultCode' => 10003, 'message' => '玩家帐号不存在', 'version' => 1];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }        
    }

    function Transfer($datas, $trdobj, $cfg)
    {
        switch ($datas['param']['type']) {
            case '10':
                return $this->bet($datas, $trdobj, $cfg);
                break;
            case '20':
                return $this->settle($datas, $trdobj, $cfg);
                break;
            case '21':
                return $this->paysettle($datas, $trdobj, $cfg);
            case '40':
                return $this->activity($datas, $trdobj, $cfg);
                break;
        }
    }
    
    function CancelTransfer($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($datas['param']['player'], 0, -3))->value('id');
                if ($utuser) {
                    $ts=time();
                    Db::startTrans();
                    try {
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                        if ($gid) {
                            if ($datas['param']['isRetry'] == true) {
                                $balance = $this->getUserBalance($utuser);
                                return $return = [
                                    'resultCode' => 0,
                                    'message' => '成功', 
                                    'version' => 1, 
                                    'balance' => $balance, 
                                ];
                            }
                            $detail = $datas['param']['originalDetails'];
                            foreach ($detail as $key => $val) {
                                $round_id = $val['gameRoundId'].$val['betNum'];
                                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->whereIn('gb_winlose', [0, 1])->find();
                                $bet_info_arr = explode('|||', $bet->gb_betinfo);
                                $is_originalTranId = false;
                                foreach ($bet_info_arr as $key => $val) {
                                    $bet_info = json_decode($val, true);
                                    if ($bet_info['tranId'] == $datas['param']['originalTranId']) {
                                        $cancel_bet = $bet_info;
                                        $is_originalTranId = true;
                                    }
                                }
                                if (!$bet || $is_originalTranId == false) {
                                    Db::rollback();
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '交易（下注／派彩结算）不存在']);
                                    return $return = [
                                        'resultCode' => 10006,
                                        'message' => '交易（下注／派彩结算）不存在', 
                                        'version' => 1, 
                                    ];
                                }
                                if ($bet) {
                                    if ($cancel_bet) {
                                        $amount = isset($cancel_bet['amount']) ? bcdiv(abs($cancel_bet['amount']),$cfg['trdcfg']["tc_exchangerate"],2) : $bet->gb_price;
                                    } else {
                                        $amount = $bet->gb_price;
                                    }
                                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '取消');
                                    User::accountChange($utuser, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                                    $bet->gb_betinfo = json_encode($val);
                                    $bet->gb_overtime = $ts;
                                    $bet->gb_winlose = 0;
                                    $bet->save();
                                }
                                $balance = $this->getUserBalance($utuser);
                                $return = [
                                    'resultCode' => 0, 
                                    'message' => '成功',
                                    'version' => 1, 
                                    'balance' => $balance,
                                ];
                                break;
                            }
                            Db::commit();
                            return $return;
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '游戏标志错误']);
                            return $return = [
                                'resultCode' => 10006, 
                                'message' => '游戏不存在',
                                'version' => 1, 
                            ];
                        }
                    } catch (\Exception $ex) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '取消失败']);
                        return $return = [
                            'resultCode' => 10006, 
                            'message' => '取消失败',
                            'version' => 1, 
                        ];
                    }
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '玩家帐号不存在']);
                    return $return = [
                        'resultCode' => 10003, 
                        'message' => '玩家帐号不存在',
                        'version' => 1, 
                    ];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }    
    }
    
    function settle($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($datas['param']['player'], 0, -3))->value('id');
                if ($utuser) {
                    if ($datas['param']['isRetry'] == true) {
                        $balance = $this->getUserBalance($utuser);
                        return $return = [
                            'resultCode' => 0,
                            'message' => '成功', 
                            'version' => 1, 
                            'balance' => $balance, 
                        ];
                    }
                    $rate = $cfg['trdcfg']["tc_exchangerate"];
                    $ts=time();
                    Db::startTrans();
                    try {
                        $details = $datas['param']['details'];
                        foreach ($details as $key => $val) {
                            $round_id = $val['gameRoundId'].$val['betNum'];
                            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                            if ($gid) {
                                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->whereIn('gb_winlose', [0, 1])->find();
                                if (!$bet) {
                                    Db::rollback();
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '无效状态码']);
                                    return $return = [
                                        'resultCode' => 10006,
                                        'message' => '无效状态码', 
                                        'version' => 1, 
                                    ];
                                } else {
                                    $gb_winprice = bcdiv($val['betAmount'] + $val['deposit'] + $val['winOrLossAmount'],$rate,2);

                                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                                    $price = bcdiv($bet->gb_price,$rate,2);
                                    $bet->gb_overtime = $ts;
                                    $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                                    $bet->gb_winprice = $gb_winprice;
                                    $bet->gb_betinfo = json_encode($val);
                                    $bet->save();
                                }
                                $balance = $this->getUserBalance($utuser);
                                $return = [
                                    'resultCode' => 0,
                                    'message' => '成功', 
                                    'version' => 1, 
                                    'balance' => $balance, 
                                ];
                                
                            } else {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '游戏标志错误']);
                                return $return = [
                                    'resultCode' => 10006, 
                                    'message' => '游戏不存在',
                                    'version' => 1, 
                                ];
                            }
                        }
                        Db::commit();
                        return $return;
                    } catch (\Exception $ex) {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '过程错误']);
                        Db::rollback();
                        return $return = [
                            'resultCode' => 10006,
                            'message' => '结算失败', 
                            'version' => 1, 
                        ];
                    }
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'Token code']);
                    return $return = [
                        'resultCode' => 10003, 
                        'message' => '玩家帐号不存在',
                        'version' => 1, 
                    ];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }    
    }

    function paysettle($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($datas['param']['player'], 0, -3))->value('id');
                if ($utuser) {
                    if ($datas['param']['isRetry'] == true) {
                        $balance = $this->getUserBalance($utuser);
                        return $return = [
                            'resultCode' => 0,
                            'message' => '成功', 
                            'version' => 1, 
                            'balance' => $balance, 
                        ];
                    }
                    $rate = $cfg['trdcfg']["tc_exchangerate"];
                    Db::startTrans();
                    try {
                        $details = $datas['param']['details'];
                        foreach ($details as $key => $val) {
                            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                            if ($gid) {
                                $round_id = $val['gameRoundId'].$val['betNum'];
                                $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                                if (!$bet) {
                                    Db::rollback();
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '交易（下注／派彩结算）不存在']);
                                        return $return = [
                                            'resultCode' => 10006,
                                            'message' => '交易（下注／派彩结算）不存在', 
                                            'version' => 1, 
                                        ];
                                } else {
                                    $gb_winprice = bcdiv(($val['betAmount'] + $val['deposit'] + $val['winOrLossAmount']),$rate,2);
                                    $round_id = $val['gameRoundId'].$val['betNum'];
                                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->whereIn('gb_winlose', [1, 2, 3, 4])->find();
                                    if ($bet) {
                                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算回滚');
                                        User::accountChange($utuser, $bet->gb_winprice, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    }
                                    if ($gb_winprice == 0) {
                                        $balance = $this->getUserBalance($utuser);
                                        $return = [
                                            'resultCode' => 0,
                                            'message' => '成功', 
                                            'version' => 1, 
                                            'balance' => $balance, 
                                        ];
                                    }
                                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '手动结算');
                                    $rlt = User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                    if ($rlt){
                                        $price = bcdiv($bet->gb_price,$rate,2);
                                        $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                                        $bet->gb_winprice = $gb_winprice;
                                        $bet->gb_betinfo = json_encode($val);
                                        $bet->save();
                                        $balance = $this->getUserBalance($utuser);
                                        $return = [
                                            'resultCode' => 0,
                                            'message' => '成功', 
                                            'version' => 1, 
                                            'balance' => $balance, 
                                        ];
//                                    }else{
//                                        Db::rollback();
//                                        $return = [
//                                            'resultCode' => 10006,
//                                            'message' => '结算失败',
//                                            'version' => 1,
//                                        ];
//                                    }
                                }
                                
                            } else {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '游戏标志错误']);
                                return $return = [
                                    'resultCode' => 10006, 
                                    'message' => '游戏不存在',
                                    'version' => 1, 
                                ];
                            }
                        }
                        Db::commit();
                        return $return;
                    } catch (\Exception $ex) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '过程错误']);
                        return $return = [
                            'resultCode' => 10006,
                            'message' => '结算失败', 
                            'version' => 1, 
                        ];
                    }
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'Token code']);
                    return $return = [
                        'resultCode' => 10003, 
                        'message' => '玩家帐号不存在',
                        'version' => 1, 
                    ];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }    
    }

    function activity($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($datas['param']['player'], 0, -3))->value('id');
                if ($utuser) {
                    $balance = $this->getUserBalance($utuser);
                    if ($datas['param']['isRetry'] == true) {
                        return $return = [
                            'resultCode' => 0,
                            'message' => '成功', 
                            'version' => 1, 
                            'balance' => $balance, 
                        ];
                    }
                    $rate = $cfg['trdcfg']["tc_exchangerate"];
                    Db::startTrans();
                    try {
                        $details = $datas['param']['details'];
                        foreach ($details as $key => $val) {
                            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                            if ($gid) {
                                $gb_winprice = bcdiv($val['amount'],$rate,2);

                                $msg = UserModel::betRemarks('无订单号', $cfg['trdcfg']['tc_name'], $gid, '活动结算');
                                $rlt = User::accountChange($utuser, $gb_winprice, '+', 2, $msg);
                                $return = [
                                    'resultCode' => 0,
                                    'message' => '成功',
                                    'version' => 1,
                                    'balance' => $balance,
                                ];
                            } else {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '游戏标志错误']);
                                return $return = [
                                    'resultCode' => 10006, 
                                    'message' => '游戏不存在',
                                    'version' => 1, 
                                ];
                            }
                        }
                        Db::commit();
                        return $return;
                    } catch (\Exception $ex) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '过程错误']);
                        return $return = [
                            'resultCode' => 10006,
                            'message' => '结算失败', 
                            'version' => 1, 
                        ];
                    }
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'Token code']);
                    return $return = [
                        'resultCode' => 10003, 
                        'message' => '玩家帐号不存在',
                        'version' => 1, 
                    ];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }  
    }
    
    function bet($datas, $trdobj,$cfg){        
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $authorization = $datas['header']['authorization'];
        $authorization_arr = explode( ':', $authorization);
        if (substr($authorization_arr[0], 2) != $cfg['trdcfg']['tc_tag']) {
            return ['resultCode' => '10000', 'message' => '无效的Operator ID', 'version' => 1];
        } else {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if($signflag){
                $utuser = UserModel::where('rec_code', substr($datas['param']['player'], 0, -3))->value('id');
                if ($utuser) {
                    $balance = $this->getUserBalance($utuser);
                    if ($datas['param']['isRetry'] == true) {
                        return $return = [
                            'resultCode' => 0,
                            'message' => '成功', 
                            'version' => 1, 
                            'balance' => $balance, 
                        ];
                    }
                    $rate = $cfg['trdcfg']["tc_exchangerate"];
                    $ts=time();
                    Db::startTrans();
                    try {
                        $uinfo = UserModel::where(Meta::UFIELD['uid'], $utuser)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                        if($uinfo){
                            $uinfo=$uinfo->toArray();
                            $details = $datas['param']['details'];
                            foreach ($details as $key => $val) {
                                $round_id = $val['gameRoundId'].$val['betNum'];
                                $amount = $val['betAmount'] + $val['deposit'];
                                $amount = bcdiv($amount,$rate,2);
                                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                                if ($gid) {
                                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                                    if ($bet) {
                                        $bet->gb_price = $bet->gb_price + $amount;
                                        $bet->gb_betinfo = json_encode($datas['param']);
                                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '加注');
                                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                        $bet->gb_cjprice = $betcjprice;
                                        $bet->save();
                                        $balance = $this->getUserBalance($utuser);
                                        $return = [
                                            'resultCode' => 0,
                                            'message' => '成功',
                                            'version' => 1,
                                            'balance' => $balance,
                                        ];
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', json_encode($return)]);
                                    } else {
                                        if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {
                                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                                            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                            $data = [
                                                'gb_gid'=>$gid,
                                                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                                'gb_uid'=>$utuser,
                                                'gb_intime'=>$ts,
                                                'gb_price'=>$amount,
                                                'gb_cjprice'=>$betcjprice,
                                                'gb_sn'=>$round_id,
                                                'gb_winlose'=>1,
                                                'gb_bettime'=>$ts,
                                                'gb_overtime'=>$ts,
                                                'gb_winprice'=>0,
                                                'gb_betinfo'=>json_encode($datas['param'])
                                            ];

                                            //写入注单
                                            $rlt=(new GameBets())->insertGetId($data);
                                            if ($rlt){
                                                $balance = $this->getUserBalance($utuser);
                                                $return = [
                                                    'resultCode' => 0,
                                                    'message' => '成功',
                                                    'version' => 1,
                                                    'balance' => $balance,
                                                ];
                                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', json_encode($return)]);
                                            } else {
                                                Db::rollback();
                                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '插入注单失败']);
                                                $return = [
                                                    'resultCode' => 10006,
                                                    'message' => '下注失败',
                                                    'version' => 1,
                                                ];
                                            }
                                        } else {
                                            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '余额不足']);
                                            $return = [
                                                'resultCode' => 10101, 
                                                'message' => '额度不足',
                                                'version' => 1, 
                                            ];
                                        }
                                    }
                                    
                                } else {
                                    Db::rollback();
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '游戏标志错误']);
                                    return $return = [
                                        'resultCode' => 10006, 
                                        'message' => '游戏不存在',
                                        'version' => 1, 
                                    ];
                                }
                            }
                            Db::commit();
                            return $return;
                        } else {
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '用户错误']);
                            Db::rollback();
                            return $return = [
                                'resultCode' => 10003, 
                                'message' => '玩家帐号不存在',
                                'version' => 1, 
                            ];
                        }
                    } catch (\Exception $ex) {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', '过程错误']);
                        Db::rollback();
                        return $return = [
                            'resultCode' => 50000, 
                            'message' => '服务器错误',
                            'version' => 1, 
                        ];
                    }
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'Token code']);
                    return $return = [
                        'resultCode' => 10003, 
                        'message' => '玩家帐号不存在',
                        'version' => 1, 
                    ];
                }
            } else {
                return ['resultCode' => '10001', 'message' => '	无效的Signature', 'version' => 1];
            }
        }  
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
        $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $balance = bcmul($balance, $rate, 2);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => "USD"]];
        return $return;
    }
    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
