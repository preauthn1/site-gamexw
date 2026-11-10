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

class Notify_smallgames extends Notify
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
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:'.$action,'签名错误', $param]);
            abort(403, 'Signature code');
        }
    }

    function getBalance($datas, $trdobj,$cfg) {
        $sign = $datas['param']['sign'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        unset($datas['param']['s'],$datas['param']['third'],$datas['param']['action_method'],$datas['param']['sign']);
        $hash = $trdobj->CheckSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserModel::where('rec_code', $datas['param']['username'])->value('id');
            if ($utuser) {
                $user_info = User::getUserData($utuser);
                $return = [
                    'msg' => 'success',
                    'code' => 0, 
                    'data' => [
                        'balance' => $this->getUserBalance($utuser),
                    ]
                ];
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",[json_encode($return)]);
                return $return;
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['Token code', $datas]);
                return ['code' => 1, 'msg' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['Signature code', $datas]);
            return ['code' => 1, 'msg' => 'Invalid hash'];
        }
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
        $balance = $this->getUserBalance($uid);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => "USDT"]];
        return $return;
    }
    
    function unbet($datas, $trdobj,$cfg){
        $sign = $datas['param']['sign'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        unset($datas['param']['third'],$datas['param']['s'],$datas['param']['action_method'],$datas['param']['sign']);
        $hash = $trdobj->CheckSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserModel::where('rec_code', $datas['param']['username'])->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                $ts=time();
                Db::startTrans();
                try {
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$datas['param']['game_code'], 'tag', 'g_id');
                    if ($gid) {
                        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $datas['param']['refId'])->where('gb_winlose', 1)->find();
                        if ($bet) {
                            $amount = $bet->gb_price;
                            $msg = UserModel::betRemarks($datas['param']['refId'], $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($utuser, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                            if ($rlt){
                                $bet->gb_betinfo = json_encode($datas['param']);
                                $bet->gb_overtime = $ts;
                                $bet->gb_winlose = 0;
                                $bet->save();
//                            }else{
//                                Db::rollback();
//                                $return = [
//                                    'code' => 1,
//                                    'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
//                                ];
//                            }
                        }
                        $balance = $this->getUserBalance($utuser);
                
                        $return = [
                            'msg' => 'success',
                            'code' => 0, 
                            'data' => [
                                'balance' => $balance, 
                            ]
                        ];
                        Db::commit();
                        return $return;
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:refund','游戏标志错误',$datas['param']['channelId']]);
                        return [
                            'code' => 1, 
                            'msg' => 'Game not found'
                        ];
                    }
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:refund','过程错误',$ex->getmsg()]);
                    Db::rollback();
                    return [
                        'code' => 1, 
                        'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Token code']);
                return ['code' => 1, 'msg' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Signature code']);
            return ['code' => 1, 'msg' => 'Invalid hash'];
        }
    }
    
    function settle($datas, $trdobj,$cfg){
        $sign = $datas['param']['sign'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        unset($datas['param']['third'],$datas['param']['s'],$datas['param']['action_method'],$datas['param']['sign']);
        $hash = $trdobj->CheckSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserModel::where('rec_code', $datas['param']['username'])->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                $ts=time();
                Db::startTrans();
                try {
                    $round_id = $datas['param']['refId'];
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$datas['param']['game_code'], 'tag', 'g_id');
                    if ($gid) {
                        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                        if (!$bet) {
                            Db::rollback();
                            $return = [
                                'code' => 1, 
                                'msg' => 'Invalid session, the session does not exist'
                            ];
                        } else {
                            $gb_winprice = bcdiv($datas['param']['rewardAmount'],$rate,2);

                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                            $rlt = User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                            if ($rlt){
                                $bet->gb_overtime = $ts;
                                $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                                $bet->gb_winprice = $gb_winprice;
                                $bet->gb_betinfo = json_encode($datas['param']);
                                $bet->save();
//                            }else{
//                                Db::rollback();
//                                $return = [
//                                    'code' => 1,
//                                    'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
//                                ];
//                            }
                        }
                        $balance = $this->getUserBalance($utuser);
                        $return = [
                            'msg' => 'success',
                            'code' => 0, 
                            'data' => [
                                'balance' => $balance, 
                            ]
                        ];
                        Db::commit();
                        return $return;
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:win','游戏标志错误',$datas['param']['channelId']]);
                        return [
                            'code' => 1, 
                            'msg' => 'Game not found'
                        ];
                    }
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:win','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    return [
                        'code' => 1, 
                        'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Token code']);
                return ['code' => 1, 'msg' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Signature code']);
            return ['code' => 1, 'msg' => 'Invalid hash'];
        }
    }
    
    function bet($datas, $trdobj,$cfg){
        $sign = $datas['param']['sign'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        unset($datas['param']['third'],$datas['param']['s'],$datas['param']['action_method'],$datas['param']['sign']);
        $hash = $trdobj->CheckSign($cfg, $datas['param']);
        if ($hash == $sign) {
            $utuser = UserModel::where('rec_code', $datas['param']['username'])->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                $ts=time();
                Db::startTrans();
                try {
                    $uinfo = UserModel::where(Meta::UFIELD['uid'], $utuser)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                    if($uinfo){
                        $uinfo=$uinfo->toArray();
                        $round_id = $datas['param']['refId'];
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$datas['param']['game_code'], 'tag', 'g_id');
                        if ($gid) {
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                            $balance = $this->getUserBalance($utuser);
                            if ($bet) {
                                $return = [
                                    'msg' => 'success',
                                    'code' => 0, 
                                    'data' => [
                                        'balance' => $balance, 
                                    ]
                                ];
                            }
                            
                            $amount = $datas['param']['amount'];
                            $amount = bcdiv($amount,$rate,2);
                            if ($amount < $balance) {
                                $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//                                if ($rlt){
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
                                            'msg' => 'success',
                                            'code' => 0, 
                                            'data' => [
                                                'balance' => $balance, 
                                            ]
                                        ];
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",[json_encode($return)]);
                                    } else {
                                        Db::rollback();
                                        $return = [
                                            'code' => 1, 
                                            'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
                                        ];
                                    }
//                                } else {
//                                    Db::rollback();
//                                    $return = [
//                                        'code' => 1,
//                                        'msg' => 'Player Insufficient Funds'
//                                    ];
//                                }
                            } else {
                                $return = [
                                    'code' => 1, 
                                    'msg' => 'Player Insufficient Funds'
                                ];
                            }
                            
                            Db::commit();
                            return $return;
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','游戏标志错误',$datas['param']['channelId']]);
                            return [
                                'code' => 1, 
                                'msg' => 'Game not found'
                            ];
                        }
                    } else {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','用户错误',$utuser]);
                        Db::rollback();
                        return [
                            'code' => 1, 
                            'msg' => 'Player not found'
                        ];
                    }
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    return [
                        'code' => 1, 
                        'msg' => 'Invalid request. This code can be returned if required parameters are missing or they have incorrect format'
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Token code']);
                return ['code' => 1, 'msg' => 'Invalid token or token expired'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Signature code']);
            return ['code' => 1, 'msg' => 'Invalid hash'];
        }
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
