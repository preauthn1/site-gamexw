<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, Users, UserThirds, GameKmSpecial};
use think\facade\Db;

class Notify_km extends Notify
{
    private $thirds_name = '';

    private $game_code = 'chess';

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
    private function ReturnData(string $code = '0000', string $msg = '', array $data = []): array
    {
        $arr['status'] = $code;
        $arr['desc'] = $msg;
        $arr['balanceTs'] = $this->microtime_format("Y-m-d*H:i:s.xP");
        $arr = array_merge($arr, $data);
        $arr = array_filter($arr); //清除空值
        return $arr;
    }

    private function microtime_float(): float
    {
        [$usec, $sec] = explode(" ", microtime());

        return ((float)$usec + (float)$sec);
    }

    private function microtime_format($format, $time = 0)
    {
        if (!$time) $time = $this->microtime_float();
        [$usec, $sec] = explode(".", $time);
        $date = date($format, $usec);
        $time = str_replace('x', str_pad(substr($sec, 0, -1), 3, 0), $date);
        $time = str_replace('*', 'T', $time);
        return $time;
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
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
            $signflag = $trdobj->CheckSign($cfg, $datas);
            $info = [];
            if (isset($param['userId'])) {
                $u_id = $param['userId'];
            } else {
                $param = $param['txns'];
                $u_id = $param[0]['userId'];
            }
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            $info['uid'] = $utuser->ut_uid;
            $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
            $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            if ($signflag) {
                if (isset($param['userId'])) {
                    $res = $this->$action($param, $trdobj, $cfg, $info);
                } else {
                    foreach ($param as $v) {
                        if($action != 'give'){
                            $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $v['gameCode'], 'code', 'g_id');
                            if($v['platform'] == 'JILI' && $v['gameType'] == 'TABLE'){
                                $v['gb_sn'] = "{$v['roundId']}_{$v['platformTxId']}";
                            }else{
                                $v['gb_sn'] = $v['platformTxId'];
                            }

                        }

                        $v['action'] = $action;
                        //正常情况下 注单id 是platformTxId
                        //当是JILI这平台的时候 识别id 是roundId(局号) 但是又需要用  platformTxId 来识别下注
                        $res = $this->$action($v, $trdobj, $cfg, $info);
                    }
                }
            } else {
                $this->PushLog($action, '签名错误', $datas);
                $res['code'] = '9999';
            }
            $this->PushLog($action, '执行完成', $res);
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($action, '主过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $res['code'] = '9999';
        }

        return $res;
    }

    //查询余额
    private function getBalance($data, $trdobj, $cfg, $info): array
    {
        $msg = '查询余额错误';
        $arr = [];
        try {
            $balance = $this->getUserBalance($info['uid']);
            $arr['balance'] = $balance;
            $arr['userId'] = $data['userId'];
            $msg = '';
            $code = '0000';
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
        }
        return $this->ReturnData($code, $msg, $arr);
    }


    //下注
    private function bet($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '下注错误';
        $arr = [];
        Db::startTrans();
        try {
            $gb_sn = "{$data['platform']}_{$data['gb_sn']}";//游戏平台名称 + 局号 + 交易单号 为唯一单号
            $money = $data['betAmount']; //操作金额
//            $aflag = Redis::get("km_bet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("km_bet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            /* @GameBets $GameBets */
            $GameBetsModel = app()->make(GameBets::class);
            $wh['gb_sn'] = $gb_sn;
            $GameBets = $GameBetsModel->where($wh)->find();
            //写入注单的时候 需要先查询是否存在该注单。因为网络问题 可能会导致 先写入了取消订单 再写入下注
            if (isset($GameBets['gb_id'])) {
                //如果已存在了 判断状态是否是无效注单  等于0就是无效注单 就是先写入了取消的 更新下注内容
                $balance = $this->getUserBalance($info['uid']);
                $arr['balance'] = $balance;
                if ($GameBets['gb_winlose'] == 0) {
                    $upd['gb_bettime'] = time();
                    $upd['gb_betinfo'] = json_encode($data);
                    $GameBets->save($upd);
                    $msg = '';
                    $code = '0000'; //测试取消订单的时候改成9999
                    $this->PushLog($data['action'], '已取消注单写入成功', $data);
                    Db::commit();
                } else {
                    //等于1 就是正常注单。忽略即可
                    $msg = '';
                    $code = '0000'; //测试取消订单的时候改成9999
                    $this->PushLog($data['action'], '订单存在,无更新 返回成功', $data);
                    Db::commit();
                }
            } else {
                //不存在就正常写入
                $amount = Third::transExchangerate($money, 0);
                if ($amount < $userBalance) {
                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid']);
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                    $bet = [
                        'gb_gid' => $info['gid'],
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_uid' => $info['uid'],
                        'gb_intime' => $ts,
                        'gb_price' => $amount,
                        'gb_cjprice' => $betcjprice,
                        'gb_sn' => $gb_sn,
                        'gb_winlose' => 1,
                        'gb_bettime' => $ts,
                        'gb_overtime' => '',
                        'gb_winprice' => 0,
                        'gb_cusfield' => $data['roundId'], //把局号携带进去 在结算的时候  用局号来结算
                        'gb_betinfo' => json_encode($data)
                    ];

                    $res = $GameBetsModel->insertGetId($bet);
                    if ($res) {
                        $balance = $this->getUserBalance($info['uid']);
                        $arr['balance'] = $balance;
                        $msg = '';
                        $code = '0000'; //测试取消订单的时候改成9999
                        $this->PushLog($data['action'], '注单写入成功', $bet);
                        Db::commit();
                    } else {
                        $this->PushLog($data['action'], '注单写入失败', $bet);
                        $code = '9999';
                        Db::rollback();
                    }
                } else {
                    $this->PushLog($data['action'], "下注失败,余额不足", $data);
                    $code = '1018';
                    $msg = 'Not Enough Balance';
                    Db::rollback();
                }
            }
//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '0000';
//                $msg = 'Duplicate transaction.';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
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
            $gb_sn = "{$data['platform']}_{$data['gb_sn']}";
//            $aflag = Redis::get("km_cancelBet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("km_cancelBet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            /* @GameBets $GameBets */
            $GameBetsModel = app()->make(GameBets::class);
            $wh['gb_sn'] = $gb_sn;
            $GameBets = $GameBetsModel->where($wh)->find();
            //存在就从原始注单退钱 不存在就返回取消成功 并记录取消注单
            if (isset($GameBets['gb_id'])) {
                //如果当前注单是未结算的时候 那就退回注金。如果是无效那就直接返回执行成功
                if ($GameBets['gb_winlose'] == 1) {
                    $gameName = explode('_', $GameBets['gb_sn'])[0];
                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid']??0, '取消');
                    User::accountChange($uinfo[Meta::UFIELD['uid']], $GameBets['gb_price'], '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                    $upd['gb_overtime'] = time();
                    $upd['gb_winlose'] = 0;
                    $upd['gb_betinfo'] = json_encode($data);
                    $GameBets->save($upd);
                    $balance = $this->getUserBalance($info['uid']);
                    $this->PushLog($data['action'], "取消注单成功", $data);
                    $arr['balance'] = $balance;
                    $code = '0000';
                    $msg = '';
                    Db::commit();
                } else {
                    $balance = $this->getUserBalance($info['uid']);
                    $this->PushLog($data['action'], "取消注单成功", $data);
                    $arr['balance'] = $balance;
                    $code = '0000';
                    $msg = '';
                    Db::commit();
                }
            } else {
                $bet = [
                    'gb_gid' => $info['gid'],
                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
                    'gb_uid' => $info['uid'],
                    'gb_intime' => $ts,
                    'gb_price' => 0,
                    'gb_sn' => $gb_sn,
                    'gb_winlose' => 0,
                    'gb_bettime' => $ts,
                    'gb_overtime' => $ts,
                    'gb_winprice' => 0,
                    'gb_cusfield' => $data['roundId'],
                    'gb_betinfo' => json_encode($data)
                ];
                $res = $GameBetsModel->insertGetId($bet);
                $balance = $this->getUserBalance($info['uid']);
                if ($res) {
                    $this->PushLog($data['action'], "无注单取消注单成功", $data);
                    $arr['balance'] = $balance;
                    $code = '0000';
                    $msg = '';
                    Db::commit();
                } else {
                    $this->PushLog($data['action'], '无注单取消注单失败', $data);
                    $code = '9999';
                    $msg = 'Fail';
                    Db::rollback();
                }
            }

//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '0000';
//                $msg = 'Duplicate transaction.';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
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
            $gb_sn = "{$data['platform']}_{$data['gb_sn']}";
            $money = $data['betAmount']; //操作金额
//            $aflag = Redis::get("km_voidBet_" . $info['gid'] . "_" . $gb_sn);
//            if (!$aflag) {
//                Redis::setex("km_voidBet_" . $info['gid'] . "_" . $gb_sn, 1, 60);//60秒内不接受重复得action
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            /* @GameBets $GameBets */
            $GameBetsModel = app()->make(GameBets::class);
            $wh['gb_sn'] = $gb_sn;
//                $wh['gb_winlose'] = 1; //将未结算的订单退回
            $GameBets = $GameBetsModel->where($wh)->find();
            //存在就退回余额 不存在就直接返回成功
            if (isset($GameBets['gb_id'])) {
                if($GameBets['gb_winlose'] != 1){
                    //如果出现了极端情况 没有写入注单 就收到作废 直接返回作废成功 没有注单的情况下 就是没有扣到用户的钱 直接不做处理
                    $this->PushLog($data['action'], '注单已处理', $data);
                    $code = '0000';
                    $msg = '';
                    Db::commit();
                }else{
                    $amount = Third::transExchangerate($money, 0);
                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $info['gid']??0, '交易作废');
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);

                    $upd['gb_overtime'] = time();
                    $upd['gb_winlose'] = 0;
                    $upd['gb_betinfo'] = json_encode($data);
                    $GameBets->save($upd);
                    $this->PushLog($data['action'], "交易作废成功", $data);
                    $arr['balance'] = '';
                    $code = '0000';
                    $msg = '';
                    Db::commit();
                }
            } else {
                $this->PushLog($data['action'], '无订单可作废', $data);
                $code = '0000';
                $msg = '';
                Db::commit();
            }
//            } else {
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '0000';
//                $msg = 'Duplicate transaction.';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
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
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();

            /* @GameBets $GameBets */
            $GameBetsModel = app()->make(GameBets::class);
            $money = $data['winAmount']; //操作金额

            //当是JILI平台的时候 用gb_cusfield 去获取注单
            if($data['platform'] == 'JILI' && $data['gameType'] == 'TABLE'){
                $wh['gb_cusfield'] = $data['roundId'];
            }else{
                $gb_sn = "{$data['platform']}_{$data['gb_sn']}";
                $wh['gb_sn'] = $gb_sn;
            }

            $GameBets = $GameBetsModel->where($wh)->find();

            if (isset($GameBets['gb_id'])) {
                if($GameBets['gb_winlose'] > 1){
                    $this->PushLog($data['action'], '注单已结算', $data);
                    $code = '0000';
                    $msg = 'Duplicate transaction.';
                    Db::rollback();
                }else{
                    $amount = Third::transExchangerate($money, 0);
                    // winAmount 等于0 必然是输 大于0 就是赢/和 不会出现小于0
                    $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
                    $upd['gb_overtime'] = time();
                    $upd_res = false;
                    if ($amount == 0) {
                        //结算为输
                        $upd['gb_winlose'] = 2; //将未结算的订单结算
//                        if($data['platform'] == 'JILI' && $data['gameType'] == 'TABLE'){
//                            $upd_res = $GameBetsModel->where($wh)->update($upd);
//                        }else{
                            $upd_res = $GameBets->save($upd);
//
//                        }
                    } else {
                        $upd['gb_winlose'] = 3;
                        $upd['gb_winprice'] = $amount;
                        $mx = $cfg['trdcfg']['tc_name'] . "_ [ " . $data['gameName'] . " ] _游戏结算";
                        $msg = UserModel::betRemarks($wh['gb_sn']??$wh['gb_cusfield'], $cfg['trdcfg']['tc_name'], $info['gid']??0, '结算');
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                        //LIJI平台的时候 注单可能是多条的 先将注单结算 再修改剩余注单状态
                        if($data['platform'] == 'JILI' && $data['gameType'] == 'TABLE'){
                            $up['gb_winlose'] = 3;
//                            $GameBetsModel->where($wh)->update($up);
                        }
                        $upd_res = $GameBets->save($upd);
                    }
                    if ($upd_res) {
                        $this->PushLog($data['action'], '注单结算成功', $data);
                        $code = '0000';
                        $msg = '';
                        Db::commit();
                    } else {
                        $this->PushLog($data['action'], '注单结算失败', $data);
                        $code = '9999';
                        $msg = 'Fail';
                        Db::rollback();
                    }
                }
            } else {
                $this->PushLog($data['action'], '注单不存在', $data);
                $code = '0000';
                $msg = '注单不存在';
                Db::commit();
            }
//            }else{
//                $this->PushLog($data['action'], '订单重复请求', $data);
//                $code = '0000';
//                $msg = 'Duplicate transaction.';
//                Db::rollback();
//            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //活动派彩
    public function give($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        $msg = '活动派彩错误';
        $arr = [];
        Db::startTrans();
        try {
            /* @GameKmSpecial $GameKmSpecial */
            $GameKmSpecialModel = app()->make(GameKmSpecial::class);
//            promotionTxId + promotionId is an unique ID.
//            promotionTxId + promotionId 为唯一值
            $gs_sn = "{$data['platform']}_{$data['promotionTxId']}_{$data['promotionId']}";
//            $aflag = Redis::get("km_give_" . $info['gid'] . "_" . $gs_sn);
            $wh['gs_sn'] = $gs_sn;
            $aflag = $GameKmSpecialModel->where($wh)->find();
            $money = $data['amount']; //操作金额
            if (!isset($aflag['gs_id'])) {
//                Redis::setex("km_give_" . $info['gid'] . "_" . $gs_sn, 1, 60);//60秒内不接受重复得action
                $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();

                $amount = Third::transExchangerate($money, 0);

                $mx = $cfg['trdcfg']['tc_name'] . "_ [ " . $this->give_type($data['promotionTypeId']) . " ] _活动派彩";
                $msg = UserModel::betRemarks($gs_sn, $cfg['trdcfg']['tc_name'], $info['gid']??0, '活动派彩');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg);
//                if ($rlt) {
                    $spec = [
                        'gs_tcid' => $cfg['trdcfg']['tc_id'],
//                        'gs_gid' => $info['gid'],
                        'gs_uid' => $info['uid'],
                        'gs_price' => $amount,
                        'gs_sn' => $gs_sn,
                        'gs_type' => 1,
                        'gs_type_name' => '活动派彩',
                        'gs_time' => $ts,
                        'gs_info' => json_encode($data),
                        'gs_create_time' => $ts,
                    ];
                    $res = $GameKmSpecialModel->save($spec);
                    if($res){
                        $this->PushLog($data['action'], '活动派彩成功', $data);
                        $code = '0000';
                        $msg = '';
                        Db::commit();
                    }else{
                        $this->PushLog($data['action'], '活动派彩失败', $data);
                        $code = '9999';
                        Db::rollback();
                    }
//                }else{
//                    $this->PushLog($data['action'], '余额加点失败', $data);
//                    $code = '9999';
//                    Db::rollback();
//                }
            }else{
                $this->PushLog($data['action'], '订单重复请求', $data);
                $code = '0000';
                $msg = 'Duplicate transaction.';
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $code = '9999';
            $msg = 'Fail';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    private function give_type($key): string
    {
        $arr = [
            '3' => 'Red Packet Rain',
            '5' => 'FC Promotion',
            '14' => 'Military God of Wealth',
            '15' => 'Civil God of Wealth',
            '17' => 'Leaderboard',
            '18' => 'Progression Giveaways',
        ];
        return $arr[$key];
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
