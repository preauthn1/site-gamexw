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

class Notify_evo extends Notify
{
    private $game_code = 'evo_game';

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

    function check($datas, $trdobj,$cfg) {
        Utils::L('evo',['notify:'.json_encode($datas)]);
        $username = $datas['param']['userId'];
        if ($datas['param']['sid'] != md5($username)) {
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            return ['status' => 'ok', 'sid' => md5($username), 'uuid' => $datas['param']['uuid']];
        }
        return ['status' => '1049', 'uuid' => $datas['param']['uuid']];
    }

    function balance($datas, $trdobj,$cfg) {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        $username = $datas['param']['userId'];
        if ($datas['param']['sid'] != md5($username)) {
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $balance = $this->getBalance($utuser);
            $return = ['status' => 'ok', 'balance' => $balance, 'bonus' => '0.00', 'uuid' => $datas['param']['uuid']];
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",[json_encode($return)]);
            return $return;
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Token code']);
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
    }
    
    function cancel($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        $username = $datas['param']['userId'];
        if ($datas['param']['sid'] != md5($username)) {
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $ts=time();
            Db::startTrans();
            try {
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $datas['param']['game']['details']['table']['id'], 'tag', 'g_id');
                if ($gid) {
                    $round_id = $datas['param']['transaction']['refId'];
                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->whereIn('gb_winlose', [0, 1])->find();
                    if (!$bet) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error",['交易（下注／派彩结算）不存在']);
                        return ['status' => '10002', 'uuid' => $datas['param']['uuid']];
                    }
                    if ($bet) {
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $datas['param']['game']['details']['table']['id'], 'tag', 'g_id');
                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '取消');
                        $rlt = User::accountChange($utuser, $bet->gb_price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt){
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($datas['param']);
                            $bet->save();
//                        }else{
//                            Db::rollback();
//                            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['取消失败']);
//                            $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
//                        }
                    }
                    $balance = $this->getBalance($utuser);
                    $return = ['status' => 'ok', 'balance' => $balance, 'bonus' => '0.00', 'uuid' => $datas['param']['uuid']];
                    Db::commit();
                    return $return;
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['游戏标志错误']);
                    return $return = ['status' => '10002', 'uuid' => $datas['param']['uuid']];
                }
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['取消失败']);
                return $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['玩家帐号不存在']);
            return $return = ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
    }
    
    function credit($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        $username = $datas['param']['userId'];
        if ($datas['param']['sid'] != md5($username)) {
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $rate = $cfg['trdcfg']["tc_exchangerate"];
            $ts=time();
            Db::startTrans();
            try {
                $round_id = $datas['param']['transaction']['refId'];
                $gb_trans_no = $datas['param']['game']['id']; // GameId
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $datas['param']['game']['details']['table']['id'], 'tag', 'g_id');
                if ($gid) {
                    $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 1)->find();
                    if (!$bet) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error",['无效状态码', $datas]);
                        return $return = ['status' => '10005', 'uuid' => $datas['param']['uuid']];
                    } else {
                        $gb_winprice = bcdiv($datas['param']['transaction']['amount'],$rate,2);
                        $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                        $rlt = User::accountChange($utuser, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt){
                            $price = bcdiv($bet->gb_price,$rate,2);
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                            $bet->gb_winprice = $gb_winprice;
                            $bet->gb_betinfo = json_encode($datas['param']);
                            $bet->save();
                            // 同步更新同局其他注单
                            $otherBet = (new GameBets())->where('gb_gid', $gid)
                                ->where('gb_uid', $bet->gb_uid)
                                ->where('gb_id', '<>', $bet->gb_id)
                                ->where('gb_trans_no', $gb_trans_no)
                                ->where('gb_winlose', 1)->select();
                            foreach ($otherBet as $_otherBet) {
                                //TODO 必须循环然后每条处理，否则会造成有效流水没有走模型里面的方法
                                $_otherBet->gb_overtime = $ts;
                                $_otherBet->gb_winlose = $_otherBet->gb_winprice > 0 ? 3 : 2;
                                $_otherBet->save();
                            }
//                            (new GameBets())->where('gb_gid', $gid)
//                                ->where('gb_uid', $bet->gb_uid)
//                                ->where('gb_id', '<>', $bet->gb_id)
//                                ->where('gb_trans_no', $gb_trans_no)
//                                ->where('gb_winlose', 1)->update([
//                                    'gb_overtime' => $ts,
////                                    'gb_winlose' => $gb_winprice > 0 ? 3 : 2,
//                                    'gb_winlose' => Db::raw('if(gb_winprice>0,3,2)'),
//                                    'gb_otherwin' => Db::raw('gb_winprice-gb_price'),
//                                    'gb_validwater' => Db::raw('if(gb_winprice>gb_price,gb_price,gb_price-gb_winprice)'),
//                                ]);
//                        }else{
//                            Db::rollback();
//                            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['操作余额失败', $datas]);
//                            $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
//                        }
                    }
                    $balance = $this->getBalance($utuser);
                    $return = ['status' => 'ok', 'balance' => $balance, 'bonus' => '0.00', 'uuid' => $datas['param']['uuid']];
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['游戏标志错误', $datas]);
                    return $return = ['status' => '10002', 'uuid' => $datas['param']['uuid']];
                }
                Db::commit();
                return $return;
            } catch (\Exception $ex) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['过程错误', $datas]);
                Db::rollback();
                return $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['Token code', $datas]);
            return $return = ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
    }

    function debit($datas, $trdobj,$cfg){        
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:'.json_encode($datas)]);
        $username = $datas['param']['userId'];
        if ($datas['param']['sid'] != md5($username)) {
            return ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $rate = $cfg['trdcfg']["tc_exchangerate"];
            $ts=time();
            Db::startTrans();
            try {
                $uinfo = UserModel::where(Meta::UFIELD['uid'], $utuser)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                if($uinfo){
                    $uinfo=$uinfo->toArray();
                    $round_id = $datas['param']['transaction']['refId'];
                    $gb_trans_no = $datas['param']['game']['id']; // GameId
                    $amount = $datas['param']['transaction']['amount'];
                    $amount = bcdiv($amount,$rate,2);
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $datas['param']['game']['details']['table']['id'], 'tag', 'g_id');
                    if ($gid) {
                        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
                        $balance = $this->getBalance($utuser);
                        if ($bet) {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['注单号重复', $datas]);
                            $return = ['status' => '10002', 'uuid' => $datas['param']['uuid']];
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
                                    'gb_betinfo'=>json_encode($datas['param']),
                                    'gb_trans_no' => $gb_trans_no
                                ];

                                //写入注单
                                $rlt=(new GameBets())->insertGetId($data);
                                if ($rlt){
                                    $balance = $this->getBalance($utuser);
                                    $return = ['status' => 'ok', 'balance' => $balance, 'bonus' => '0.00', 'uuid' => $datas['param']['uuid']];
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",[json_encode($return)]);
                                } else {
                                    Db::rollback();
                                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['插入注单失败', $datas]);
                                    $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
                                }
                            } else {
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['余额不足', $datas]);
                                $return = ['status' => '10008', 'uuid' => $datas['param']['uuid']];
                            }
                        }
                    } else {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error",['游戏标志错误', $datas]);
                        return $return = ['status' => '10002', 'uuid' => $datas['param']['uuid']];
                    }
                    Db::commit();
                    return $return;
                } else {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['用户错误', $datas]);
                    Db::rollback();
                    return $return = ['status' => '10003', 'uuid' => $datas['param']['uuid']];
                }
            } catch (\Exception $ex) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['过程错误', $datas]);
                Db::rollback();
                return $return = ['status' => '1049', 'uuid' => $datas['param']['uuid']];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['Token code', $datas]);
            return $return = ['status' => '10003', 'uuid' => $datas['param']['uuid']];
        }
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
        $balance = $this->getBalance($uid);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => "USD"]];
        return $return;
    }

    function getBalance($uid, $cfg = null){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
