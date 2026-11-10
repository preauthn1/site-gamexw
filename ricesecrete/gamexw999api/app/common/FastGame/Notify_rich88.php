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
use think\facade\Db;

class Notify_rich88  extends Notify
{

    function notify($datas, $trdobj, $cfg)
    {
        return $this->returnError(20008);
    }


    public function session_id($datas, $trdobj, $cfg) {
        if (!$trdobj->CheckSign($cfg, $datas)) {
            return $this->returnError(16007);
        }
        return $this->returnResult([
            'sid' => $cfg['trdcfg']["tc_token"]
        ]);
    }

    /**
     * 余额
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function balance($datas, $trdobj, $cfg) {
        if (!$this->checkAuthorization($datas['header'], $cfg)) {
            return $this->returnError(22003);
        }
        $param = $datas['param'];
        $s = array_filter(explode("/", trim($param['s'], '/')));
        $account = $s[3] ?? null;
        return $this->returnBalance($account);
    }

    /**
     * 交易
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function transfer($datas, $trdobj, $cfg) {
        if (!$this->checkAuthorization($datas['header'], $cfg)) {
            return $this->returnError(22003);
        }
        $param = $datas['param'];
        if ( !isset($param['action']) || !in_array($param['action'], ['withdraw', 'rollback', 'deposit']) ) {
            return $this->returnError(20008);
        }
        $account = $param['account'];
        $amount = $param['money'];
        $currency = $param['currency']; // 币种
        if ($amount < 0) {
            return $this->returnError(20008, '金额不能为负数');
        }
        $amount = Third::transExchangerate($amount,0);
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['game_code'], 'tag', 'g_id');
        if (empty($gid)) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '游戏标志错误', $datas]);
            return $this->returnResult(false, 1100, '游戏标志错误');
        }

        $redis_key = "rich88_transfer_{$param['action']}_" . $gid . "_" . $param['round_id'] . "_" .$param['transfer_no'];
        $aflag = Redis::get($redis_key);
        if ($aflag) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '交易已成功', $datas]);
            return $this->returnError(22004, '交易已成功');
        }
        Redis::setex($redis_key, 1, 60);//60秒内不接受重得action
        // 回退
        if ($param['action'] === 'rollback') {
            $bet = GameBets::where('gb_gid', $gid)->where('gb_trans_no', $param['transfer_no'])->find();
            if (empty($bet)) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '交易記錄不存在', $datas]);
                return $this->returnError(22005, '交易記錄不存在');
            }
        }
        // 提款 - 投注
        elseif ($param['action'] == 'withdraw') {
            $bet = GameBets::where('gb_gid', $gid)->where('gb_trans_no', $param['transfer_no'])->find();
            $bet2 = GameBets::where('gb_gid', $gid)->where('gb_sn', $param['round_id'])->find();
            if ($bet || $bet2) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '交易已成功', $datas]);
                return $this->returnError(22004, '交易已成功');
            }
        }
        // 派彩
        elseif ($param['action'] == 'deposit') {
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $param['round_id'])->where('gb_winlose', 1)->find();
            if (empty($bet)) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '交易記錄不存在', $datas]);
                return $this->returnError(22005, '交易記錄不存在');
            }
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}", '用户错误', $param]);
                Db::rollback();
                return $this->returnError(22006, '用户信息错误');
            }
            $userBalance = $this->getBalance($account);
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $msg = "";
//            $msg = "{$cfg['trdcfg']['tc_name']}_{$param['game_code']}";
            // 下注
            if ($param['action'] == 'withdraw') {
                if ($amount > $userBalance) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["notify:{$param['action']}",'错误:余额不足',$param]);
                    Db::rollback();
                    return $this->returnError(22007, 'Insufficient Balance');
                }
                $operation = '-';
                $type = 1;
                $msg .= "下注";

                $bet_data =[
                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gb_gid'=>$gid,
                    'gb_uid'=>$uid,
                    'gb_intime'=>$ts,
                    'gb_price'=>$amount,
                    'gb_sn'=>$param['round_id'],
                    'gb_trans_no'=>$param['transfer_no'],
                    'gb_winlose'=>1,
                    'gb_bettime'=>$ts,
                    'gb_overtime'=>$ts,
                    'gb_winprice'=>0,
                    'gb_betinfo'=>json_encode($param)
                ];

                //写入注单
                $rlt=(new GameBets())->insertGetId($bet_data);
                if (!$rlt){
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["notify:{$param['action']}",'错误:插入订单失败',$param]);
                    Db::rollback();
                    return $this->returnError(10002, '插入订单失败');
                }
                $bet = (new GameBets())->where('gb_id', '=', $rlt)->find();
            }
            // 游戏结算
            elseif ($param['action'] == 'deposit') {
                $operation = '+';
                $type = 2;
                $msg .= "结算";
                $bet->gb_overtime = $ts;
                $bet->gb_winlose = $amount > 0 ? 3 : 2;
                $bet->gb_winprice = max($amount, 0);
                $bet->gb_betinfo = json_encode($param);
                $bet->save();
            }
            // 下注失败
            elseif ($param['action'] == 'rollback') {
                $operation = '+';
                $type = 3;
                $msg .= "取消";
                $bet->gb_overtime = $ts;
                $bet->gb_winlose = 0;
                $bet->gb_betinfo = json_encode($param);
                $bet->save();
            }
            if($amount > 0){
                $msg = UserModel::betRemarks($param['round_id'], $cfg['trdcfg']['tc_name'], $gid, $msg);
                if($operation == '-'){
                    $betcjprice = User::accountChange($uid, $amount, $operation, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                    (new GameBets())->where('gb_id', '=', $rlt)->save([
                        'gb_cjprice' => $betcjprice
                    ]);
                }else{
                    $rlt = User::accountChange($uid, $amount, '+', $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                }
//                if (!$rlt){
//                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ["notify:{$param['action']}",'错误:更新用户余额失败',$param]);
//                    Db::rollback();
//                    return $this->returnError(10002, '更新用户余额失败');
//                }
            }
            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",["notify:{$param['action']}",'过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnError(10002, '服务器错误');
        }
        return $this->returnResult();
    }

    /**
     * 活动派奖
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function  award_activity($datas, $trdobj, $cfg) {
        if (!$this->checkAuthorization($datas['header'], $cfg)) {
            return $this->returnError(22003);
        }
        $param = $datas['param'];
        $account = $param['account'];
        $amount = $param['money'];
        $redis_key = "rich88_award_activity_" .$param['award_id'];
        $aflag = Redis::get($redis_key);
        if ($aflag) {
            return $this->returnBalance($account);
        }
        Redis::setex($redis_key, 1, 3600);
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $account)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if(!$uinfo){
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:award_activity', '用户错误', $param]);
                Db::rollback();
                return $this->returnError(22006, '用户信息错误');
            }
            $uinfo = $uinfo->toArray();
            $uid = $uinfo[Meta::UFIELD['uid']];
            $amount = Third::transExchangerate($amount,0);
            if($amount > 0){
//                $msg = $cfg['trdcfg']['tc_name'] . "_活动奖励_{$param['award_id']}";
                $msg = UserModel::betRemarks($param['award_id'], $cfg['trdcfg']['tc_name'], 0, '活动奖励');
                $rlt = User::accountChange($uid, $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id']);
//                if ($rlt){
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['notify:award_activity', '派奖成功', $param]);
//                }else{
//                    Db::rollback();
//                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['notify:award_activity', '派奖失败', $param]);
//                    return $this->returnError(22006, '用户信息错误');
//                }
            }

            Db::commit();
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:award_activity', '过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return $this->returnError(10002, '更新用户余额错误');
        }
        return $this->returnResult();

    }

    private function checkAuthorization($header, $cfg) {
        return isset($header['authorization']) && $header['authorization'] == $cfg['trdcfg']['tc_token'];
    }

    private function returnResult($data = [], $code = 0, $msg = 'Success') {
        return [
            'data' => $data,
            "code" => $code,
            "msg" => $msg
        ];
    }

    private function returnBalance($account, $uid = null) {
        $balance = $this->getBalance($account, $uid);
        if ($balance === false) {
            return $this->returnError(13002);
        }
        return $this->returnResult([
            'balance' => $balance,
        ]);
    }

    private function returnError($code = 10001, $msg = 'Error') {
        return $this->returnResult([], $code, $msg);
    }

    private function getBalance($account, $uid = null) {
        if( $account && !is_numeric($uid) ){
            $uid = UserModel::where(Meta::UFIELD['uname'], $account)->value(Meta::UFIELD['uid']);
        }
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        return floatval(Third::transExchangerate($balance,1));
    }
}