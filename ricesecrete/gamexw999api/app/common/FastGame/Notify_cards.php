<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, Users, UserThirds, GameCardsSpecial};
use think\facade\Db;

class Notify_cards extends Notify
{
    private $thirds_name = '';

    private $game_code = 'cards';

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
    private function ReturnData(string $code = '200', string $msg = '', array $data = []): array
    {
        $arr['status'] = $code;
        $arr['msg'] = $msg;
        $arr = array_merge($arr, $data);
        return array_filter($arr);//清除空值
    }




    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action . $msg, $data]);
    }

    public function chess($datas, $trdobj, $cfg)
    {
        try {
            $param = json_decode($datas['param']['message'], true);
            $action = $param['action'];
            $this->thirds_name = strtolower($cfg['trdcfg']['tc_code']);
            $signflag = $trdobj->CheckSign($cfg, $datas); // ??
            $info = [];
            $u_id = $param['userId'];
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            $info['uid'] = $utuser->ut_uid;
            $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
            $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            if(isset($param['gameId'])){
                $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['gameId'], 'tag', 'g_id');
                $info['g_name'] = $this->get_game_name($param['gameId']);
            }
            //die;
            if ($signflag) {
                $res = $this->$action($param, $trdobj, $cfg, $info);
            } else {
                $this->PushLog($action, '签名错误', $datas);
                $res['code'] = '1001';
            }
            $this->PushLog($action, '执行完成', $res);
        } catch (\Throwable $e) {
            $this->PushLog($action, '主过程错误', $e->getMessage());
            $res['code'] = '500';
        }

        return $res;
    }

    //查询余额
    private function getBalance($data, $trdobj, $cfg, $info): array
    {
        $msg = '查询余额错误';
        $arr = [];
        try {
            $balance = UserModel::getBalanceForDoubleWalletByGid($info['uid']);
            $balance = Third::transExchangerate($balance, 1);
            $arr['balance'] = $balance;
            $arr['userId'] = $data['userId'];
            $msg = '';
            $code = '200';
        } catch (\Throwable $e) {
            $this->PushLog($data['action'], $msg, $e->getMessage());
            $code = '500';
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }


    //下注
    private function bet($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '下注错误';
        $arr = [];
        Db::startTrans();
        try {
            $balance = $this->getUserBalance($info['uid']);
            $balance = Third::transExchangerate($balance, 1);
            $arr['balance'] = $balance;
            $msg = '';
            $code = '200'; //测试取消订单的时候改成9999
            $this->PushLog($data['action'], '进入游戏成功', $data);
//            $gb_sn = $data['gameNumber'];//游戏局号 为唯一单号
//            $money = $data['betAmount']; //操作金额
//            $aflag = Redis::get("cards_bet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("cards_bet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
//
//
//            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
//            /* @GameBets $GameBets */
//            $GameBetsModel = app()->make(GameBets::class);
//            $wh['gb_sn'] = $gb_sn;
//            $GameBets = $GameBetsModel->where($wh)->find();
//            //写入注单的时候 需要先查询是否存在该注单。因为网络问题 可能会导致 先写入了取消订单 再写入下注
//            if (isset($GameBets['gb_id'])) {
//                //如果已存在了 判断状态是否是无效注单  等于0就是无效注单 就是先写入了取消的 更新下注内容
//                $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                $arr['balance'] = $balance;
//                if ($GameBets['gb_winlose'] == 0) {
//                    $upd['gb_bettime'] = time();
//                    $upd['gb_betinfo'] = json_encode($data);
//                    $GameBets->save($upd);
//                    $msg = '';
//                    $code = '200'; //测试取消订单的时候改成9999
//                    $this->PushLog($data['action'], '已取消注单写入成功', $data);
//                    Db::commit();
//                } else {
//                    //等于1 就是正常注单。忽略即可
//                    $msg = '';
//                    $code = '200'; //测试取消订单的时候改成9999
//                    $this->PushLog($data['action'], '订单存在,无更新 返回成功', $data);
//                    Db::commit();
//                }
//            } else {
//                //不存在就正常写入
//                $amount = Third::transExchangerate($money, 0);
//                if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {
//                    $mx = $cfg['trdcfg']['tc_name'] . "_ [ " . $info['g_name'] . " ] _下注";
//                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $mx);
//                    if ($rlt) {
//                        $bet = [
//                            'gb_gid' => $info['gid'],
//                            'gb_tcid' => $cfg['trdcfg']['tc_id'],
//                            'gb_uid' => $info['uid'],
//                            'gb_intime' => $ts,
//                            'gb_price' => $amount,
//                            'gb_sn' => $gb_sn,
//                            'gb_winlose' => 1,
//                            'gb_bettime' => $ts,
//                            'gb_overtime' => '',
//                            'gb_winprice' => 0,
//                            'gb_betinfo' => json_encode($data)
//                        ];
//
//                        $res = $GameBetsModel->save($bet);
//                        if ($res) {
//                            $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                            $balance = Third::transExchangerate($balance, 1);
//                            $arr['balance'] = $balance;
//                            $msg = '';
//                            $code = '200'; //测试取消订单的时候改成9999
//                            $this->PushLog($data['action'], '注单写入成功', $bet);
//                            Db::commit();
//                        } else {
//                            $this->PushLog($data['action'], '注单写入失败', $bet);
//                            $code = '500';
//                            Db::rollback();
//                        }
//                    }else{
//                        $this->PushLog($data['action'], '下注扣点失败', $data);
//                        $code = '500';
//                        Db::rollback();
//                    }
//                } else {
//                    $this->PushLog($data['action'], "下注失败,余额不足", $data);
//                    $code = '500';
//                    $msg = 'Not Enough Balance';
//                    Db::rollback();
//                }
//            }
//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '500';
//                $msg = 'Duplicate order request';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->PushLog($data['action'], '过程错误', $e->getMessage());
            $code = '500';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    //取消下注
    private function cancelBet($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '取消下注错误';
        $arr = [];
        Db::startTrans();
        try {
//            $gb_sn = $data['gameNumber'];//游戏局号 为唯一单号
//            $aflag = Redis::get("cards_cancelBet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("cards_cancelBet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
//                $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
//                /* @GameBets $GameBets */
//                $GameBetsModel = app()->make(GameBets::class);

                $balance = $this->getUserBalance($info[Meta::UFIELD['uid']]);
                $balance = Third::transExchangerate($balance, 1);
                $arr['balance'] = $balance;
                $msg = '';
                $code = '200'; //测试取消订单的时候改成9999
                $this->PushLog($data['action'], '取消下注成功', $data);


//                //直接写入一条无效注单
//                $bet = [
//                    'gb_gid' => $info['gid'],
//                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
//                    'gb_uid' => $info['uid'],
//                    'gb_intime' => $ts,
//                    'gb_price' => 0.00,
//                    'gb_winprice' => 0.00,
//                    'gb_winlose' => 0,
//                    'gb_sn' => $gb_sn,
//                    'gb_bettime' => $ts,
//                    'gb_overtime' => $ts,
//                    'gb_betinfo' => json_encode($data)
//                ];
//                $res = $GameBetsModel->save($bet);
//                if ($res) {
//                    $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                    $balance = Third::transExchangerate($balance, 1);
//                    $this->PushLog($data['action'], "取消下注成功", $data);
//                    $arr['balance'] = $balance;
//                    $code = '200';
//                    $msg = '';
//                    Db::commit();
//                } else {
//                    $this->PushLog($data['action'], '交易作废订单状态修改失败', $data);
//                    $code = '500';
//                    $msg = 'Fail';
//                    Db::rollback();
//                }

//            $wh['gb_sn'] = $gb_sn;
//            $GameBets = $GameBetsModel->where($wh)->find();
//            //存在就从原始注单退钱 不存在就返回取消成功 并记录取消注单
//            if (isset($GameBets['gb_id'])) {
//                //如果当前注单是未结算的时候 那就退回注金。如果是无效那就直接返回执行成功
//                if ($GameBets['gb_winlose'] == 1) {
//                    $mx = $cfg['trdcfg']['tc_name'] . "_ [ " . $info['g_name'] . " ] _取消注单";
//                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $GameBets['gb_price'], '+', 3, $mx);
//                    if ($rlt) {
//                        $upd['gb_overtime'] = time();
//                        $upd['gb_winlose'] = 0;
//                        $upd['gb_betinfo'] = json_encode($data);
//                        $GameBets->save($upd);
//                        $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                        $balance = Third::transExchangerate($balance, 1);
//                        $this->PushLog($data['action'], "取消注单成功", $data);
//                        $arr['balance'] = $balance;
//                        $code = '200';
//                        $msg = '';
//                        Db::commit();
//                    } else {
//                        $this->PushLog($data['action'], '取消注单失败', $data);
//                        $code = '500';
//                        $msg = 'Fail';
//                        Db::rollback();
//                    }
//                } else {
//                    $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                    $this->PushLog($data['action'], "取消注单成功", $data);
//                    $arr['balance'] = $balance;
//                    $code = '200';
//                    $msg = '';
//                    Db::commit();
//                }
//            } else {
//                $bet = [
//                    'gb_gid' => $info['gid'],
//                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
//                    'gb_uid' => $info['uid'],
//                    'gb_intime' => $ts,
//                    'gb_price' => 0,
//                    'gb_sn' => $gb_sn,
//                    'gb_winlose' => 0,
//                    'gb_bettime' => $ts,
//                    'gb_overtime' => $ts,
//                    'gb_winprice' => 0,
//                    'gb_betinfo' => json_encode($data)
//                ];
//                $res = $GameBetsModel->save($bet);
//                $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                $balance = Third::transExchangerate($balance, 1);
//                if ($res) {
//                    $this->PushLog($data['action'], "无注单取消注单成功", $data);
//                    $arr['balance'] = $balance;
//                    $code = '200';
//                    $msg = '';
//                    Db::commit();
//                } else {
//                    $this->PushLog($data['action'], '无注单取消注单失败', $data);
//                    $code = '500';
//                    $msg = 'Fail';
//                    Db::rollback();
//                }
//            }

//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '500';
//                $msg = 'Duplicate order request.';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->PushLog($data['action'], '过程错误', $e->getMessage());
            $code = '500';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    //交易作废
    private function voidBet($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '执行交易作废错误';
        $arr = [];
        Db::startTrans();
        try {
//            $gb_sn = $data['gameNumber'];//游戏局号 为唯一单号
//            $aflag = Redis::get("cards_voidBet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("cards_voidBet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action

                $balance = $this->getUserBalance($info[Meta::UFIELD['uid']]);
                $balance = Third::transExchangerate($balance, 1);
                $arr['balance'] = $balance;
                $msg = '';
                $code = '200'; //测试取消订单的时候改成9999
                $this->PushLog($data['action'], '交易作废成功', $data);

//            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
//            /* @GameBets $GameBets */
//            $GameBetsModel = app()->make(GameBets::class);
//            /* @GameCardsSpecial $GameCardsSpecialModel */
//            $GameCardsSpecialModel = app()->make(GameCardsSpecial::class);
//
//            $wh['gb_sn'] = $gb_sn;
////                $wh['gb_winlose'] = 1; //将未结算的订单退回
//            $GameBets = $GameBetsModel->where($wh)->find();
//            //存在就退回余额 不存在就直接返回成功
//            if (isset($GameBets['gb_id'])) {
//                //如果出现了极端情况 没有写入注单 就收到作废 直接返回作废成功 没有注单的情况下 就是没有扣到用户的钱 直接不做处理
//                if($GameBets['gb_winlose'] != 1){
//                    //状态不是 1 的情况下 就是处理过了这个订单
//                    $this->PushLog($data['action'], '注单已处理', $data);
//                    $code = '200';
//                    $msg = '';
//                    Db::commit();
//                }else{
//                    $money = $GameBets['gb_price']; //操作金额 因为是数据库中的金额 所以不用汇率转换 直接加回去即可
////                    $amount = Third::transExchangerate($money, 0);
//                    $mx = $cfg['trdcfg']['tc_name'] . "_ [ " . $info['g_name'] . " ] _交易作废";
//                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $money, '+', 3, $mx);
//
//                    if ($rlt) {
//                        $upd['gb_overtime'] = time();
//                        $upd['gb_winlose'] = 0;
//                        $upd['gb_betinfo'] = json_encode($data);
//                        $res = $GameBets->save($upd);
//                        if ($res) {
//                            $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
//                            $balance = Third::transExchangerate($balance, 1);
//                            $this->PushLog($data['action'], "交易作废成功", $data);
//                            $wh['gs_type'] = 1;
//                            $GameCardsSpecial = $GameCardsSpecialModel->where($wh)->select();
//                            foreach($GameCardsSpecial as $k => $v){
//                                if(isset($v['gs_id'])){
//                                    $whe['gs_id'] = $v['gs_id'];
//                                    $spec_upd['gs_type'] = 3;
//                                    $spec_upd['gb_betinfo'] = json_encode($data);
//                                    if($v['gs_type_name'] == '彩头派彩'){
//
//                                    }
//                                    if($v['gs_type_name'] == '红包派彩'){
//
//                                    }
//                                }
//                            }
//                            $arr['balance'] = $balance;
//                            $code = '200';
//                            $msg = '';
//                            Db::commit();
//                        } else {
//                            $this->PushLog($data['action'], '交易作废订单状态修改失败', $data);
//                            $code = '500';
//                            $msg = 'Fail';
//                            Db::rollback();
//                        }
//
//
//                    } else {
//                        $this->PushLog($data['action'], '交易作废失败', $data);
//                        $code = '500';
//                        $msg = 'Fail';
//                        Db::rollback();
//                    }
//                }
//            } else {
//                $this->PushLog($data['action'], '无订单可作废', $data);
//                $code = '200';
//                $msg = '';
//                Db::commit();
//            }

//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '500';
//                $msg = 'Duplicate order request';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->PushLog($data['action'], '过程错误', $e->getMessage());
            $code = '500';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    //结算
    private function settle($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '结算错误';
        $arr = [];
        Db::startTrans();
        try {
            $gb_sn = $data['gameNumber'];//游戏局号 为唯一单号
            $bet_Money =  $data['realBetMoney']; //下注金额  realBetMoney 是0的时候 就是没有下注
            $settle_money = $data['profit']; //结账金额 决定输赢 如果大于0 就是赢
            $validbet =  $data['validbet']; //真实下注金额 如果该局是输 下注金额要改成这个
            $bonus =  $data['bonus']; //彩头派彩
            $reward =  $data['reward']; //红包派彩

            $aflag = Redis::get("cards_settle_" . $info['gid'] . "_" . $gb_sn);
            if (!$aflag) {
                Redis::setex("cards_settle_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();

            /* @GameBets $GameBets */
            $GameBetsModel = app()->make(GameBets::class);
            /* @GameCardsSpecial $GameCardsSpecialModel */
                $GameCardsSpecialModel = app()->make(GameCardsSpecial::class);

            $w['gb_sn'] = $gb_sn;
//                //查询有没有写入该注单 有的话 直接返回
            $GameBets = $GameBetsModel->where($w)->find();
            if(isset($GameBets['gb_id'])){
                $this->PushLog($data['action'], '订单已存在', $data);
                $code = '500';
                $msg = 'Duplicate order request';
                Db::rollback();
            }else{
                if($settle_money > 0){
                    //如果赢的时候 要把本金一起加回去
                    $settle_money += $bet_Money;
                }

                $bet_Money = Third::transExchangerate($bet_Money, 0);
                $settle_money = Third::transExchangerate($settle_money, 0);
                $validbet = Third::transExchangerate($validbet, 0);
//                    $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
//                    $upd['gb_overtime'] = time();
                $user_balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                //当下注金额大于用户余额的时候 直接扣到0
                if($bet_Money > $user_balance){
                    $bet_Money = $user_balance;
                }
                $bet = [
                    'gb_gid' => $info['gid'],
                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
                    'gb_uid' => $info['uid'],
                    'gb_intime' => $ts,
                    'gb_price' => $bet_Money,
                    'gb_winprice' => $settle_money,
                    'gb_sn' => $gb_sn,
                    'gb_bettime' => $ts,
                    'gb_overtime' => $ts,
                    'gb_betinfo' => json_encode($data)
                ];
                $betId = (new GameBets())->insertGetId($bet);
                if( !$betId ){
                    $this->PushLog($data['action'], '注单结算失败', $data);
                    $code = '500';
                    $msg = 'Fail';
                    Db::rollback();
                    return $this->ReturnData($code, $msg, $arr);
                }
                $bet['id'] = $betId;
                //游戏每次开局算是一局 不下注的时候 注金是0 写入空注单 状态为和 不改动余额
                $betcjprice = 0;
                if($bet_Money  == 0){
                    $bet['gb_winlose'] = 4;
                }else{

                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid']);
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $bet_Money, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                    if ($settle_money > 0) {
                        //结算为赢
                        $bet['gb_winlose'] = 3;
                        $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid'], '结算');
                        User::accountChange($uinfo[Meta::UFIELD['uid']], $settle_money, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $betId, $bet_Money, $betcjprice);
                    } else {
                        //结算为输 输的时候 输赢金额为0
                        $bet['gb_winlose'] = 2;
                        $bet['gb_price'] = $validbet;
                        $bet['gb_winprice'] = 0;
                    }
                }

                $upd_res = $GameBetsModel->save($bet);
                if($upd_res){
                    if(isset($data['bonus']) && $data['bonus'] > 0){
                        //彩头派彩
                        $bonus = Third::transExchangerate($bonus, 0);
                        $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid'], '彩头派彩');
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $bonus, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $betId, $bet_Money, $betcjprice);
                        $bonus_arr = [
                            'gs_tcid' => $cfg['trdcfg']['tc_id'],
                            'gs_gid' => $info['gid'],
                            'gs_uid' => $info['uid'],
                            'gs_price' => $bonus,
                            'gs_sn' => 'bonus_' . $gb_sn,
                            'gs_type' => 1,
                            'gs_type_name' => '彩头派彩',
                            'gs_time' => $ts,
                            'gs_info' => json_encode($data),
                            'gs_create_time' => $ts,
                        ];
                        $bonus_res = $GameCardsSpecialModel->save($bonus_arr);
                        if ($bonus_res) {
                            $this->PushLog($data['action'], '彩头派彩成功', $data);
                        } else {
                            $this->PushLog($data['action'], '彩头派彩失败', $data);
                        }

                    }

                    if(isset($data['reward']) && $data['reward'] > 0){
                        //红包派彩
                        $reward = Third::transExchangerate($reward, 0);
                        $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid'], '红包派彩');
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $reward, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $betId, $bet_Money, $betcjprice);
                        $reward_arr = [
                            'gs_tcid' => $cfg['trdcfg']['tc_id'],
                            'gs_gid' => $info['gid'],
                            'gs_uid' => $info['uid'],
                            'gs_price' => $reward,
                            'gs_sn' => 'reward_' . $gb_sn,
                            'gs_type' => 1,
                            'gs_type_name' => '红包派彩',
                            'gs_time' => $ts,
                            'gs_info' => json_encode($data),
                            'gs_create_time' => $ts,
                        ];
                        $reward_res = $GameCardsSpecialModel->save($reward_arr);
                        if ($reward_res) {
                            $this->PushLog($data['action'], '红包派彩成功', $data);
                        } else {
                            $this->PushLog($data['action'], '红包派彩失败', $data);
                        }

                    }
                }
                if ($upd_res) {
                    $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                    $balance = Third::transExchangerate($balance, 1);
                    $this->PushLog($data['action'], '注单结算成功', $data);
                    $code = '200';
                    $msg = '';
                    $arr['balance'] = $balance;
                    Db::commit();
                } else {
                    $this->PushLog($data['action'], '注单结算失败', $data);
                    $code = '500';
                    $msg = 'Fail';
                    Db::rollback();
                }

            }



            }else{
                $this->PushLog($data['action'], '订单重复请求', $data);
                $code = '500';
                $msg = 'Duplicate order request';
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->PushLog($data['action'], '过程错误', $e->getMessage());
            $code = '500';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    private function get_game_name($key){
        $arr = [
            "555" => "Ox Banker",
            "556" => "Star Ox",
            "666" => "Happy Ox",
            "705" => "Casino Ox",
            "567" => "Bursting QX",
            "563" => "Three Pictures",
            "566" => "Casino  3   Pictures  ",
            "569" => "Rapid 3 Pictures",
            "703" => "Bursting 3 Pictures",
            "706" => "3 Pictures Special",
            "565" => "Texas Poker",
            "557" => "Battle Blackjack",
            "570" => "2-plyr Fight Landlord",
            "710" => "Rummy",
            "559" => "Run Fast",
            "701" => "365 Poker",
            "918" => "Jhandi Munda",
            "916" => "Vietnam Tài-Xỉu",
            "917" => "Thai Hi-Lo",
            "912" => "Vietnam Fish Prawn Crab",
            "913" => "Thai Fish Prawn Crab",
            "449" => "2-plyr Mahjong",
            "445" => "Red Mahjong",
            "452" => "Guangdong Mahjong",
            "564" => "Mahjong Titan",
            "561" => "3 Cards",
            "562" => "13 Cards",
            "549" => "Bursting Baccarat",
            "702" => "8 Cards",
            "707" => "3 Patti Classic",
            "921" => "Andar Bahar",
            "789" => "Pok Deng",
        ];
        return $arr[$key];
    }

}
