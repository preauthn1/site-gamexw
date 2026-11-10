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
use app\common\lib\Util;
use app\common\model\UserModel;
use app\model\GameAward;
use app\model\GameBets;
use app\model\GameBetsSon;
use app\model\Games;
use app\model\Users;
use app\model\UserThirds;
use think\facade\Db;

class Notify_fc extends Notify
{
    private $game_code = 'fc_game';

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
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:'.$action,'签名错误',$param]);
            abort(403, 'Signature code');
        }
    }

    function cancelBetInfo($datas, $trdobj,$cfg)
    {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["cancelBetInfo",$datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["cancelBetInfo",$param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $ts=time();
                Db::startTrans();
                try {
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['gameID'], 'code', 'g_id');
                    if ($gid) {
                        $round_id = $param['BankID'];
                        $bet = GameBets::where('gb_gid', $gid)->where('gb_cusfield', $round_id)->find();
                        if (!$bet) {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBetInfo",'交易（下注／派彩结算）不存在', $param]);
                            return ['Result' => 999, 'message' => 'BetID error.'];
                        }
                        if ($bet) {
                            if (in_array($bet->gb_winlose, [0,2,3,4])) {
                                return ['Result' => 799, 'message' => 'BetID success.'];
                            }
                            $amount = bcdiv($bet->gb_price,$cfg['trdcfg']["tc_exchangerate"],2);
                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($utuser, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($param);
                            $bet->save();
                        }
                        $balance = $this->getUserBalance($utuser);
                        $return = ['Result' => 0, 'MainPoints' => $balance];
                        Db::commit();
                        return $return;
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBetInfo",'游戏标志错误', $param]);
                        return ['Result' => 999, 'message' => 'game not fund.'];
                    }
                } catch (\Exception $ex) {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBetInfo",'取消失败', $param, $ex->getMessage(), $ex->getTraceAsString()]);
                    return ['Result' => 999, 'message' => 'server error.'];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBetInfo",'玩家帐号不存在', $param]);
                return ['Result' => 500, 'message' => 'user not fund.'];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBetInfo",'Signature error', $param]);
            return ['Result' => 999, 'message' => 'Signature error.'];
        }
    }
    function betInfo($datas, $trdobj,$cfg)
    {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["betInfo",$datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["betInfo",$param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                // FC API数据时间的时区为UTC-4
                $tz = gettimezone("-4");
                $ts = Util::getTimeZoneConversion(\app\common\Utils::getQueryDateTime($param['CreateDate']), $tz);
                Db::startTrans();
                try {
                    $uinfo = UserModel::where(Meta::UFIELD['uid'], $utuser)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                    if($uinfo){
                        $uinfo=$uinfo->toArray();
                        $round_id = $param['RecordID'];
                        $gb_cusfield = $param['BankID'];
                        $amount = $param['Bet'];
                        $amount = bcdiv($amount,$rate,2);
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['GameID'], 'code', 'g_id');
                        if ($gid) {
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                            $userBalance = $this->getUserBalance($utuser);
                            if ($bet) {
                                $gb_winprice = bcdiv($param['Win'],$rate,2);
                                $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                                $bet->gb_overtime = $ts;
                                $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                                $bet->gb_winprice = $gb_winprice;
                                $bet->gb_betinfo = json_encode($param);
                                $bet->save();
                                $balance = $this->getUserBalance($utuser);
                                $return = ['Result' => 0, 'MainPoints' => $balance];
                            } else {
                                if ($amount < $userBalance) {
                                    $data = [
                                        'gb_gid'=>$gid,
                                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                        'gb_uid'=>$utuser,
                                        'gb_intime'=>$ts,
                                        'gb_price'=>$amount,
                                        'gb_cjprice'=>0,
                                        'gb_sn'=>$round_id,
                                        'gb_cusfield'=>$gb_cusfield,
                                        'gb_winlose'=>0,
                                        'gb_bettime'=>$ts,
                                        'gb_overtime'=>$ts,
                                        'gb_winprice'=>0,
                                        'gb_betinfo'=>json_encode($param)
                                    ];

                                    //写入注单
                                    $rlt=(new GameBets())->insertGetId($data);
                                    if( !$rlt ){
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'插入注单失败', $param]);
                                        $return = [
                                            'Result' => 999,
                                            'MainPoints' => 'server error',
                                        ];
                                        Db::rollback();
                                        return $return;
                                    }
                                    $bet = (new GameBets())->where('gb_id', '=', $rlt)->find();
                                    $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                                    $upd = [];
                                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    $upd['gb_cjprice'] = $betcjprice;
                                    if ($param['Win'] > 0) {
                                        $gb_winlose = 3;
                                        $gb_winprice = $param['Win'];
                                        $gb_winprice = bcdiv($gb_winprice,$rate,2);
                                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                        User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $betcjprice);
                                    } else {
                                        $gb_winlose = 2;
                                        $gb_winprice = 0;
                                    }
                                    if ($param['JPPrize'] > 0) {
                                        $JPPrize = bcdiv($param['JPPrize'],$rate,2);
                                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算爆奖');
                                        User::accountChange($utuser, $JPPrize, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $betcjprice);
                                    }
                                    $upd['gb_winlose'] = $gb_winlose;
                                    $upd['gb_winprice'] = $gb_winprice;
                                    $rlt = $bet->save($upd);
                                    if ($rlt){
                                        $balance = $this->getUserBalance($utuser);
                                        $return = ['Result' => 0, 'MainPoints' => $balance];
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["betInfo",json_encode($return)]);
                                    }else{
                                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'过程错误', $param]);
                                        $return = [
                                            'Result' => 999,
                                            'MainPoints' => 'server error',
                                        ];
                                    }
                                } else {
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'余额不足', $param]);
                                    $return = [
                                        'Result' => 203, 
                                        'MainPoints' => 'Insufficient Balance',
                                    ];
                                }
                            }
                            if($return['Result']!=0){
                                Db::rollback();
                                return $return;
                            }
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'游戏标志错误', $param]);
                            return [
                                'Result' => 999, 
                                'MainPoints' => 'game not fund',
                            ];
                        }
                        Db::commit();
                        return $return;
                    } else {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'用户错误', $param]);
                        Db::rollback();
                        return [
                            'Result' => 500, 
                            'MainPoints' => 'user not fund',
                        ];
                    }
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'过程错误', $param, $ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    return [
                        'Result' => 999, 
                        'MainPoints' => 'server error',
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'Token code error', $param]);
                return [
                    'Result' => 500, 
                    'MainPoints' => 'user not fund',
                ];
            }
        } else {
            return [
                'Result' => 999, 
                'MainPoints' => 'Signature error',
            ];
        }
    }
   
    function getBalance($datas, $trdobj,$cfg) {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["getBalance",$datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["getBalance",$param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $balance = $this->getUserBalance($utuser);
                $return = ['Result' => 0, 'MainPoints' => $balance];
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["balance",json_encode($return)]);
                return $return;
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["betInfo",'Token code error', $param]);
                return ['Result' => 500, 'ErrorText' => 'Account does not exist.'];
            }
        } else {
            return ['Result' => 999, 'message' => 'Signature error.'];
        }
    }
    
    function cancelBet($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["cancelBet",$datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["cancelBet",$param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $ts=time();
                Db::startTrans();
                try {
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['GameID'], 'code', 'g_id');
                    if ($gid) {
                        $round_id = $param['BetID'];
                        $bet_son = GameBetsSon::where('gb_gid', $gid)->where('gb_sn_ac', $round_id)->whereIn('gb_winlose', 1)->find();
                        if (!$bet_son) {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'交易（下注／派彩结算）不存在', $param]);
                            return ['Result' => 999, 'message' => 'BetID error.'];
                        }
                        $bet = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $bet_son->gb_sn)->whereIn('gb_winlose', 1)->find();
                        if ($bet_son && $bet) {
                            $amount = bcdiv($param['Bet'],$cfg['trdcfg']["tc_exchangerate"],2);
                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '取消');
                            $rlt = User::accountChange($utuser, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                            if ($rlt){
                                $bet_son->gb_overtime = $ts;
                                $bet_son->gb_winlose = 0;
                                $bet_son->gb_betinfo = json_encode($param);
                                $bet_son->save();

                                $bet->gb_price -= $amount;
                                $bet->gb_betinfo = json_encode($param);
                                if ($bet->gb_price <= 0) {
                                    $bet->gb_price = 0;
                                    $bet->gb_winlose = 0;
                                    $bet->gb_winprice = 0;
                                }
                                $bet->save();
//                            }else{
//                                Db::rollback();
//                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'取消失败', $param]);
//                                return ['Result' => 999, 'message' => 'server error.'];
//                            }
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'交易（下注／派彩结算）不存在', $param]);
                            return ['Result' => 999, 'message' => 'BetID error.'];
                        }
                        $balance = $this->getUserBalance($utuser);
                        $return = ['Result' => 0, 'MainPoints' => $balance];
                        Db::commit();
                        return $return;
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'游戏标志错误', $param]);
                        return ['Result' => 999, 'message' => 'game not fund.'];
                    }
                } catch (\Exception $ex) {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'取消失败', $param, $ex->getMessage(), $ex->getTraceAsString()]);
                    return ['Result' => 999, 'message' => 'server error.'];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["cancelBet",'玩家帐号不存在', $param]);
                return ['Result' => 500, 'message' => 'user not fund.'];
            }
        } else {
            return ['Result' => 999, 'message' => 'Signature error.'];
        }
    }
    
    function settle($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["settle", $datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["settle", $param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                $ts=time();
                Db::startTrans();
                try {
                    $round_id = $param['RecordID'];
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['GameID'], 'code', 'g_id');
                    if (!$gid) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["settle",'游戏标志错误', $param]);
                        return ['Result' => 999, 'ErrorText' => 'game not fund'];
                    }
                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                    if (!$bet) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["settle",'无效RecordID', $param]);
                        return ['Result' => 999, 'ErrorText' => 'RecordID error'];
                    }

                    $gb_winprice = bcdiv($param['Refund'], $rate,2);
                    $bet->gb_overtime = $ts;
                    $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                    $bet->gb_winprice = $gb_winprice;
                    $bet->gb_betinfo = json_encode($param);
                    $bet->save();
                    if ($gb_winprice > 0) {
                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                        User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                    }
                    $details = $param['SettleBetIDs'];
                    foreach ($details as $key => $val) {
                        $son_round_id = $val['betID'];
                        $bet_son = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_sn_ac', $son_round_id)->where('gb_winlose', 1)->find();
                        if (!$bet_son) {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["settle",'无效状态码', $param]);
                            return ['Result' => 999, 'ErrorText' => 'betID error'];
                        } else {
                            $gb_winprice = bcdiv($val['win'],$rate,2);
                            $bet_son->gb_overtime = $ts;
                            $bet_son->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                            $bet_son->gb_winprice = $gb_winprice;
                            $bet_son->save();
                        }
                        $balance = $this->getUserBalance($utuser);
                        $return = ['Result' => 0, 'MainPoints' => $balance];

                    }
                    Db::commit();
                    return $return;
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["settle",'过程错误', $param, $ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    return ['Result' => 999, 'ErrorText' => 'server error'];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["settle",'Token code error', $param]);
                return ['Result' => 500, 'ErrorText' => 'user not fund'];
            }
        } else {
            return ['Result' => 999, 'ErrorText' => 'Signature error'];
        }
    }

    function activity($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["activity",'notify:'.json_encode($datas)]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["activity",'notify-param:'.json_encode($param)]);
        $sign = $trdobj->CheckSign($cfg, $param);
        if($sign){
            $rate = $cfg['trdcfg']["tc_exchangerate"];
            Db::startTrans();
            try {
                $details = $param['List'];
                foreach ($details as $key => $val) {
                    $username = $param['MemberAccount'];
                    $utuser = UserModel::where('rec_code', $username)->value('id');
                    if ($utuser) {
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $val['gameID'], 'code', 'g_id');
                    if ($gid) {
                        $gb_winprice = bcdiv($val['points'],$rate,2);
                        $msg = UserModel::betRemarks('无订单号', $cfg['trdcfg']['tc_name'], $gid, '活动结算');
                        User::accountChange($utuser, $gb_winprice, '+', 2, $msg);
                        $balance = $this->getUserBalance($utuser);
                        $return = ['Result' => 0, 'MainPoints' => $balance];
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["activity",'游戏标志错误', $param]);
                        return [
                            'Result' => 999,
                            'ErrorText' => 'game not fund', 
                        ];
                    }
                    } else {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["activity",'Token code', $param]);
                        return [
                            'Result' => 500,
                            'ErrorText' => 'user not fund', 
                        ];
                    }
                }
                Db::commit();
                return $return;
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["activity",'过程错误', $param, $ex->getMessage(), $ex->getTraceAsString()]);
                return [
                    'Result' => 999,
                    'ErrorText' => 'server error', 
                ];
            }
            
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["activity",'Signature error', $param]);
            return [
                'Result' => 999, 
                'MainPoints' => 'Signature error',
            ];
        }
    }
    
    function bet($datas, $trdobj,$cfg){        
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["bet", $datas]);
        $param = json_decode(openssl_decrypt(base64_decode($datas['param']['Params']), 'AES-128-ECB', $cfg['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA), true);
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ["bet", $param]);
        $sign = $trdobj->CheckSign($cfg, $param);
        $username = $param['MemberAccount'];
        if($sign){
            $utuser = UserModel::where('rec_code', $username)->value('id');
            if ($utuser) {
                $rate = $cfg['trdcfg']["tc_exchangerate"];
                $ts=time();
                Db::startTrans();
                try {
                    $uinfo = UserModel::where(Meta::UFIELD['uid'], $utuser)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                    if($uinfo){
                        $uinfo=$uinfo->toArray();
                        $round_id = $param['RecordID'];
                        $son_round_id = $param['BetID'];
                        $amount = $param['Bet'];
                        $amount = bcdiv($amount,$rate,2);
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['GameID'], 'code', 'g_id');
                        if ($gid) {
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                            if ($bet && $bet->gb_winlose != 1) {
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'注单已成立', $param]);
                                return [
                                    'Result' => 799,
                                    'MainPoints' => '注单已成立',
                                ];
                            }
                            $bet_son = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_sn_ac', $son_round_id)->find();
                            $balance = $this->getUserBalance($utuser);
                            if ($bet_son) {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'注单号重复', $param]);
                                return [
                                    'Result' => 999, 
                                    'MainPoints' => 'BetID repeat',
                                ];
                            }
                            if ($amount > $balance) {
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'余额不足', $param]);
                                return [
                                    'Result' => 203,
                                    'MainPoints' => 'Insufficient Balance',
                                ];
                            }

                            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
                            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg);
                            if (!$bet) {
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
                                    'gb_betinfo'=>json_encode($param)
                                ];
                                //写入注单
                                $rlt=(new GameBets())->insertGetId($data);
                            } else {
                                $bet->gb_price += $amount;
                                $bet->gb_betinfo = json_encode($param);
                                $bet->save();
                            }

                            $data_son=[
                                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                'gb_gid'=>$gid,
                                'gb_uid'=>$utuser,
                                'gb_intime'=>$ts,
                                'gb_price'=>$amount,
                                'gb_cjprice'=>$betcjprice,
                                'gb_sn'=>$round_id,
                                'gb_sn_ac' => $son_round_id,
                                'gb_winlose'=>1,
                                'gb_winprice' => $amount,
                                'gb_bettime'=>$ts,
                                'gb_overtime'=>$ts,
                                'gb_betinfo'=>json_encode($param)
                            ];
                            //写入注单
                            $rlt=(new GameBetsSon())->insertGetId($data_son);
                            if ($rlt){
                                $balance = $this->getUserBalance($utuser);
                                $return = ['Result' => 0, 'MainPoints' => $balance];
                            } else {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'插入注单失败', $param]);
                                $return = [
                                    'Result' => 999,
                                    'MainPoints' => 'server error',
                                ];
                            }
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'游戏标志错误', $param]);
                            return [
                                'Result' => 999, 
                                'MainPoints' => 'game not fund',
                            ];
                        }
                        Db::commit();
                        return $return;
                    } else {
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'用户错误', $param]);
                        Db::rollback();
                        return [
                            'Result' => 500, 
                            'MainPoints' => 'user not fund',
                        ];
                    }
                } catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    return [
                        'Result' => 999, 
                        'MainPoints' => 'server error',
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'Token code', $param]);
                return [
                    'Result' => 500, 
                    'MainPoints' => 'user not fund',
                ];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["bet",'Signature error', $param]);
            return [
                'Result' => 999, 
                'MainPoints' => 'Signature error',
            ];
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
