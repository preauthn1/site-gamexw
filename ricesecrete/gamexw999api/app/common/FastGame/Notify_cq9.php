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

class Notify_cq9 extends Notify
{

    function notify($datas, $trdobj, $cfg)
    {
        // TODO: Implement notify() method.
    }

    public function player($datas, $trdobj, $cfg) {
        if (!$this->checkWtoken($datas['header'], $cfg)) {
            return $this->returnResult(false, 3, 'Token invalid.');
        }
        $account = $datas['param']['check'] ?? '';
        $isHas = false;
        if ($account) {
            $isHas = UserModel::where(Meta::UFIELD['uname'], $account)->value(Meta::UFIELD['uid']);
        }
        return $this->returnResult(!!$isHas);
    }

    public function transaction($datas, $trdobj, $cfg) {
        if (!$this->checkWtoken($datas['header'], $cfg)) {
            return $this->returnResult(false, 3, 'Token invalid.');
        }
        $param = $datas['param'];
        $currency = $cfg['trdcfg']['tc_currency'];
        if (isset($param['balance'])) {
            return $this->returnBalance($param['balance'], $currency);
        } elseif (isset($param['game'])) {
            // bet, endround, rollout, takeall, rollin, debit, credit, payoff, refund
            if(method_exists($this, $param['game'])){
                return $this->{$param['game']}($param, $trdobj, $cfg);
            }
        } elseif (isset($param['user'])) {
            if(method_exists($this, $param['user'])){
                return $this->{$param['user']}($param, $trdobj, $cfg);
            }
        }
        return $this->returnResult(false, 1002, '動作錯誤');
    }

    /**
     * 電子遊戲下注 (派彩接口使用Endound API)
     * 遊戲類型 gametype :老虎機[slot] / 街機[arcade]
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function bet($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '投注金额不能为负数');
        }
        $amount = Third::transExchangerate($amount,0);
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }

        $bet = GameBets::where('gb_gid', $gid)->where('gb_trans_no', $data['mtcode'])->find();
        if ($bet) {
            return $this->returnResult(false, 2009, 'MTCode重複');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->find();
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $userBalance = $this->getBalance(null, $uid);
            if ($amount > $userBalance) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:bet','错误:余额不足',$data]);
                Db::rollback();
                return $this->returnResult(false, 1005, 'Insufficient Balance');
            }
            $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

            if( $bet ){
                //判断重复注单累计下注金额
                $rlt = $bet->save([
                    'gb_price' => $bet->gb_price + $amount,
                    'gb_betinfo'=>json_encode($data)
                ]);
                $errMsg = "更新订单失败";
            }else{

                $data=[
                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gb_gid'=>$gid,
                    'gb_uid'=>$uid,
                    'gb_intime'=>$ts,
                    'gb_price'=>$amount,
                    'gb_cjprice'=>$betcjprice,
                    'gb_sn'=>$data['roundid'],
                    'gb_trans_no'=>$data['mtcode'],
                    'gb_winlose'=>1,
                    'gb_bettime'=>$ts,
                    'gb_overtime'=>$ts,
                    'gb_winprice'=>0,
                    'gb_betinfo'=>json_encode($data)
                ];

                //写入注单
                $rlt=(new GameBets())->insertGetId($data);
                $errMsg = "插入订单失败";
            }
            if (!$rlt){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:bet','错误:'.$errMsg,$data]);
                Db::rollback();
                return $this->returnResult(false, 1100, $errMsg);
            }
            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet','过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '服务器错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 電子遊戲派彩
     * 如果該局是輸的，endround會送amount = 0
     * 遊戲類型gametype : 老虎機[slot] / 街機[arcade]
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function endround($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->where('gb_winlose', 1)->find();
        if (empty($bet)) {
            return $this->returnBalance($account, $currency);
        }
        if (!is_array($data['data'])) {
            $data['data'] = json_decode($data['data'], true);
        }
        $amount = 0;
        foreach ($data['data'] as $datum) {
            $redis_key = "cq9_end_round_" . $gid . "_" . $data['roundid'] . "_" .$datum['mtcode'];
            if (Redis::has($redis_key) || $datum['amount']< 0) {
                continue;
            }
            Redis::setex($redis_key, 1, 60);
            $amount += $datum['amount'];
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:endround', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            if($amount > 0){
                $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                if ($rlt){
                    $bet->gb_overtime = $ts;
                    $bet->gb_winlose = 3;
                    $bet->gb_winprice = $amount;
                    $bet->gb_betinfo = json_encode($data);
                    $bet->save();
//                }else{
//                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:endround', '更新用户余额错误', $data]);
//                    Db::rollback();
//                    return $this->returnResult(false, 1100, '更新用户余额错误');
//                }
            } else {
                $bet->gb_overtime = $ts;
                $bet->gb_winlose = 2;
                $bet->gb_betinfo = json_encode($data);
                $bet->save();
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:endround', '过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 轉出玩家『部分』額度至遊戲(轉回額度接口使用Rollin API)
     * 遊戲類型 gametype : 牌桌[table] / 街機[arcade] / 魚機[fish]
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function rollout($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '投注金额不能为负数');
        }
        $amount = Third::transExchangerate($amount,0);
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $redis_key = "cq9_rollout_" . $gid . "_" . $data['roundid'] . "_" .$data['mtcode'];
        $aflag = Redis::get($redis_key);
        if ($aflag) {
            return $this->returnBalance($account, $currency);
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_trans_no', $data['mtcode'])->find();
        if ($bet) {
            return $this->returnResult(false, 2009, 'MTCode重複');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->find();
        if ($bet) {
            return $this->returnBalance($account, $currency);
        }
        Redis::setex($redis_key, 1, 60);//60秒内不接受重得action
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:rollout', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $userBalance = $this->getBalance(null, $uinfo[Meta::UFIELD['uid']]);
            if ($amount > $userBalance) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:rollout','错误:余额不足',$data]);
                Db::rollback();
                return $this->returnResult(false, 1005, 'Insufficient Balance');
            }
            $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, 'Rollout');
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            $data=[
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_gid'=>$gid,
                'gb_uid'=>$uid,
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_cjprice'=>$betcjprice,
                'gb_sn'=>$data['roundid'],
                'gb_trans_no'=>$data['mtcode'],
                'gb_winlose'=>1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=>0,
                'gb_betinfo'=>json_encode($data)
            ];

            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if (!$rlt){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:rollout','错误:插入订单失败',$data]);
                Db::rollback();
                return $this->returnResult(false, 1100, '插入订单失败');
            }
            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:rollout','过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '服务器错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 一場遊戲結束，轉回額度至玩家錢包
     * 遊戲類型 gametype : 牌桌[table] / 街機[arcade] / 魚機[fish]
     * ※ 街機[arcade] / 魚機[fish] : Rollin amount = Rollout amount - bet + win
     * ※ 牌桌[table] : Rollin amount=Rollout amount+win-rake
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function rollin($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '转入金额不能为负数');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->where('gb_winlose', 1)->find();
        if (empty($bet)) {
            return $this->returnBalance($account, $currency);
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:rollin', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            if($amount > 0){
                $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, '结算');
                User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                $bet->gb_overtime = $ts;
                $bet->gb_winlose = 3;
                $bet->gb_winprice = $amount;
                $bet->gb_betinfo = json_encode($data);
                $bet->save();
            } else {
                $bet->gb_overtime = $ts;
                $bet->gb_winlose = 2;
                $bet->gb_betinfo = json_encode($data);
                $bet->save();
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:rollout','过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 轉出玩家『全部』額度至遊戲(轉回額度接口使用Rollin API)
     * 遊戲類型 gametype : 牌桌[table] / 街機[arcade] / 魚機[fish]
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function takeall($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $redis_key = "cq9_rollout_" . $gid . "_" . $data['roundid'] . "_" .$data['mtcode'];
        $aflag = Redis::get($redis_key);
        if ($aflag) {
            return $this->returnResult(false, 1100, '重复请求');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_trans_no', $data['mtcode'])->find();
        if ($bet) {
            return $this->returnResult(false, 2009, 'MTCode重複');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->find();
        if ($bet) {
            return $this->returnBalance($account, $currency);
        }
        Redis::setex($redis_key, 1, 60);
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:takeall', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = $this->getBalance(null, $uid);
            if ($amount <= 0) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:takeall','错误:余额不足',$data]);
                Db::rollback();
                return $this->returnResult(false, 1005, 'Insufficient Balance');
            }
            $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, 'Rollout');
            $cjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            $data=[
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_gid'=>$gid,
                'gb_uid'=>$uid,
                'gb_cjprice'=>$cjprice,
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$data['roundid'],
                'gb_trans_no'=>$data['mtcode'],
                'gb_winlose'=>1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=>0,
                'gb_betinfo'=>json_encode($data)
            ];

            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if (!$rlt){
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:takeall','错误:插入订单失败',$data]);
                Db::rollback();
                return $this->returnResult(false, 1100, '插入订单失败');
            }
            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:takeall','过程错误', $ex->getMessage()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '服务器错误');
        }
        return $this->returnResult([
            'amount' => $amount,
            'balance' => 0,
            "currency" => $currency
        ]);
    }

    /**
     * 針對『已完成』的訂單做扣款。例如，遊戲邏輯錯誤進行修正
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return void
     */
    private function debit($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '转入金额不能为负数');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->where('gb_winlose', 3)->find();
        if (empty($bet)) {
            return $this->returnBalance($account, $currency);
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:debit', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            $userBalance = $this->getBalance($account);
            if ($amount > $userBalance) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:debit','错误:余额不足',$data]);
                Db::rollback();
                return $this->returnResult(false, 1005, 'Insufficient Balance');
            }
            if($amount > 0){
                $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, '游戏逻辑错误进行修正');
                $betcjprice = User::accountChange($uid, $amount, '-', 4, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                $bet->save([
                    'gb_cjprice' => $betcjprice
                ]);
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:debit', '过程错误', $ex->getMessage()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 針對『已完成』的訂單做補款。例如，遊戲邏輯錯誤進行修正
     * @param $param
     * @param $trdobj
     * @param $cfg
     * @return void
     */
    private function credit($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '转入金额不能为负数');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
        if (empty($gid)) {
            return $this->returnResult(false, 1100, '游戏标志错误');
        }
        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $data['roundid'])->where('gb_winlose', 2)->find();
        if (empty($bet)) {
            return $this->returnBalance($account, $currency);
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:credit', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            if($amount > 0){
                $msg = UserModel::betRemarks($data['roundid'], $cfg['trdcfg']['tc_name'], $gid, '游戏逻辑错误进行修正');
                $rlt = User::accountChange($uid, $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                if (!$rlt){
//                    Db::rollback();
//                    return $this->returnResult(false, 1100, '更新用户余额错误');
//                }
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:credit', '过程错误', $ex->getMessage()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance($account, $currency);
    }

    /**
     * 退回 bet/rollout/takeall 金額
     * @param $param
     * @param $trdobj
     * @param $cfg
     * @return void
     */
    private function refund($data, $trdobj, $cfg) {
        $currency = $cfg['trdcfg']['tc_currency'];
        $bet = GameBets::where('gb_trans_no', $data['mtcode'])->where('gb_winlose', 1)->find();
        if (empty($bet)) {
            return $this->returnResult(false, 1006, '用户信息错误');
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $bet['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = $bet['gb_price'];
            if($amount > 0){
                $gid = 0;
                if( isset($data['gamecode']) ){
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['gamecode'], 'tag', 'g_id');
                }
                $msg = UserModel::betRemarks($data['roundid']??$data['mtcode'], $cfg['trdcfg']['tc_name'], $gid, '游戏注单回退');
                $rlt = User::accountChange($uid, $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                if ($rlt){
                    $bet->gb_overtime = $ts;
                    $bet->gb_winlose = 0;
                    $bet->gb_betinfo = json_encode($data);
                    $bet->save();
//                }else{
//                    Db::rollback();
//                    return $this->returnResult(false, 1100, '更新用户余额错误');
//                }
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:credit', '过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance(null, $currency, $uid);
    }

    /**
     * 活动派奖
     * @param $data
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function payoff($data, $trdobj, $cfg) {
        $account = $data['account'];
        $currency = $cfg['trdcfg']['tc_currency'];
        $amount = $data['amount'];
        if ($amount < 0) {
            return $this->returnResult(false, 1003, '转入金额不能为负数');
        }
        $redis_key = "cq9_payoff_" .$data['mtcode'];
        $aflag = Redis::get($redis_key);
        if ($aflag) {
            return $this->returnBalance($account, $currency);
        }
        Redis::setex($redis_key, 1, 3600);
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:payoff', '用户错误', $account]);
                Db::rollback();
                return $this->returnResult(false, 1006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            if($amount > 0){
                $msg = $cfg['trdcfg']['tc_name'] . "_活动奖励";
                if (isset($data['remark']) && strlen($data['remark']) < 80) {
                    $msg .= "_{$data['remark']}";
                }
                $msg = UserModel::betRemarks($data['roundid']??$data['mtcode'], $cfg['trdcfg']['tc_name'], 0, $msg);
                $rlt = User::accountChange($uid, $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id']);
//                if ($rlt){
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:payoff', '派奖成功', $account]);
//                }else{
//                    Db::rollback();
//                    return $this->returnResult(false, 1100, '更新用户余额错误');
//                }
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:credit', '过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnResult(false, 1100, '更新用户余额错误');
        }
        return $this->returnBalance($account, $currency);
    }
    private function getBalance($account, $uid = null) {
        if( $account && !is_numeric($uid) ){
            $uid = UserModel::where(Meta::UFIELD['uname'], $account)->value(Meta::UFIELD['uid']);
        }
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        return floatval(Third::transExchangerate($balance,1));
    }
    private function checkWtoken($header, $cfg) {
        return isset($header['wtoken']) && $header['wtoken'] == $cfg['trdcfg']['tc_api_signkey'];
    }

    private function returnResult($data, $code = 0, $msg = 'Success') {
        return [
            'data' => $data,
            'status' => [
                "code" => strval($code),
                "message" => $msg,
                "datetime" => date("c")
            ]
        ];
    }

    private function returnBalance($account, $currency, $uid = null) {
        $balance = $this->getBalance($account, $uid);
        return $this->returnResult([
            'balance' => $balance,
            "currency" => $currency
        ]);
    }
}