<?php


namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\common\model\UserModel;
use app\model\GameBets;
use app\model\GameBetsex;
use app\model\UserThirds;
use think\facade\Db;

class Notify_whitecliff extends Notify
{
    private $tcid = 0;

    private $user_id = '';

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
        $this->tcid = $cfg['trdcfg']['tc_id']."/log";
        $param = $datas['param'];
        $verify = array_key_exists('api_token', $param) && $this->verify($param['api_token'], $cfg);

        if ($verify) {
            $action = $param['cmd'];
            return $this->$action($param, $trdobj, $cfg);
        } else {
            $this->tcid = $cfg['trdcfg']['tc_id']."/error";
            $this->PushLog('notify', 'api_token 验证失败', $datas);
            return [
                'code' => 1001,
                'msg'  => 'api_token 验证失败'
            ];
        }
    }

    //验证
    function verify($token, $cfg)
    {
        $api_token = $cfg['trdcfg']['tc_api_signkey'];
        if ($token == $api_token) {
            return true;
        } else {
            return false;
        }
    }

    //获取用户余额
    public function balance($datas, $trdobj = null, $cfg = null)
    {
        // 校验secret-key
        $header = $datas['header'];
        $param = $this->getParam($datas, $cfg);
        $verify = array_key_exists('secret-key', $header) && $this->verify($header['secret-key'], $cfg);
        if (!$verify) {
            $this->tcid = $cfg['trdcfg']['tc_id']."/error";
            $this->PushLog('notify', 'api_token 验证失败', $datas);
            $this->returnData(0, 0, 'ACCESS_DENIED');
        }
        $this->pushLog($param, 'balance', $trdobj);

        try {
            $u_id = $param['user_id'];
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            if (!$utuser) {
                return $this->returnData(0, 0, 'INVALID_USER');
            }
            //获取用户数据
            $userDetail = UserModel::where(Meta::UFIELD['uid'], '=', $utuser->ut_uid)->find();
            $userBalance = $this->getUserBalance($userDetail[Meta::UFIELD['uid']]);

            return $this->returnData($userBalance);
        } catch (\Exception $e) {
            $this->pushLog($param, 'balance error：'.$e->getMessage(), [$datas, $trdobj], 'error');
        }
    }

    /**
     * 下注
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function debit($datas, $trdobj, $cfg): array
    {
        // 校验secret-key
        $header = $datas['header'];
        $param = $this->getParam($datas, $cfg);
        $verify = array_key_exists('secret-key', $header) && $this->verify($header['secret-key'], $cfg);
        if (!$verify) {
            $this->tcid = $cfg['trdcfg']['tc_id']."/error";
            $this->PushLog('notify', 'api_token 验证失败', $datas);
            $this->returnData(0, 0, 'ACCESS_DENIED');
        }

        $time = time();
        Db::startTrans();
        try {
            $u_id = $param['user_id'];
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            if (!$utuser) {
                return $this->returnData(0, 0, 'INVALID_USER');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uid'], '=', $utuser->ut_uid)->find();

            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $param['table_id'];
            $round_id = $param['txn_id'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData(0, 0, 'GAME_NOT_FOUND');
            }
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
            $balance = $this->getUserBalance($user_id);

            if ($bet) { // 存在注单
                return $this->returnData(0, 0, 'DUPLICATE_DEBIT');
            }
            $amount = $param['amount'];
            $amount = Third::transExchangerate($amount, 0);
            if ($amount > $balance) { // 余额不足
                return $this->returnData(0, 0, 'INSUFFICIENT_FUNDS');
            }

            $gb_winlose = GameBets::UNSETTLED;
            $gb_winprice = 0;

            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($user_id, $amount, '-', 1, $msg, true,
                $cfg['trdcfg']['tc_id'], $gid);

            // 如果 param['credit_amount'] 存在，直接走结算
            if (array_key_exists('credit_amount', $param)) {
                $gb_winprice = $param['credit_amount'];
                $gb_winlose =  $gb_winprice > 0 ? GameBets::WIN : GameBets::LOSE;

                $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
                User::accountChange($user_id, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id,
                    $bet->gb_price, $bet->gb_cjprice);
            }

            $data = array(
                'gb_gid'      => $gid,
                'gb_tcid'     => $cfg['trdcfg']['tc_id'],
                'gb_uid'      => $user_id,
                'gb_intime'   => $time,
                'gb_price'    => $amount,
                'gb_cjprice'  => $betcjprice,
                'gb_sn'       => $round_id,
                'gb_winlose'  => $gb_winlose,
                'gb_bettime'  => $time,
                'gb_overtime' => $time,
                'gb_winprice' => $gb_winprice,
                'gb_betinfo'  => json_encode($datas)
            );
            //写入注单
            $bet_id = (new GameBets())->insertGetId($data);

            $balance = $this->getUserBalance($user_id);
            DB::commit();
            return $this->returnData($balance);
        } catch (\Exception $ex) {
            Db::rollback();
            $this->pushLog('notify:bet', '过程错误', $ex->getMessage(), 'error');
            return $this->returnData('', 0, 'UNKNOWN_ERROR');
        }
    }


    /**
     * 盈利
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function bonus($datas, $trdobj, $cfg)
    {
        return $this->settle($datas, $trdobj, $cfg);
    }


    /**
     * 奖金 No Limit City 没有奖金
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    public function credit($datas, $trdobj, $cfg)
    {
        return $this->settle($datas, $trdobj, $cfg);
    }

    /**
     * 结算
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function settle($datas, $trdobj, $cfg): array
    {
        // 校验secret-key
        $header = $datas['header'];
        $param = $this->getParam($datas, $cfg);
        $verify = array_key_exists('secret-key', $header) && $this->verify($header['secret-key'], $cfg);
        if (!$verify) {
            $this->tcid = $cfg['trdcfg']['tc_id']."/error";
            $this->PushLog('notify', 'api_token 验证失败', $datas);
            $this->returnData(0, 0, 'ACCESS_DENIED');
        }
        Db::startTrans();
        try {
            $u_id = $param['user_id'];
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            if (!$utuser) {
                return $this->returnData(0, 0, 'INVALID_USER');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uid'], '=', $utuser->ut_uid)->find();

            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $param['table_id'];
            $round_id = $param['txn_id'];

            $is_cancel = $param['is_cancel']; // 0 正常,无需处理, 1 取消未结算注单
            if ($is_cancel) {
                $this->unbet($datas, $trdobj, $cfg);
            }

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData(0, 0, 'GAME_NOT_FOUND');
            }
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
            $balance = $this->getUserBalance($user_id);
            $balance = Third::transExchangerate($balance, 1);

            $amount = $param['amount'];
            $amount = Third::transExchangerate($amount, 0);

            if (!$bet) { // 不存在注单
                return $this->returnData($balance, 0, 'INVALID_DEBIT');
            }

            $gb_winprice = $amount;

            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid, '结算');
            User::accountChange($user_id, $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id,
                $bet->gb_price, $bet->gb_cjprice);
            $bet->gb_overtime = time();
            $bet->gb_winlose = $gb_winprice > 0 ? GameBets::WIN : GameBets::LOSE;
            $bet->gb_winprice = $gb_winprice;
            $bet->gb_betinfo = json_encode($datas);
            $bet->save();
            Db::commit();
            $balance = $this->getUserBalance($user_id);
            return $this->returnData($balance);
        } catch (\Exception $ex) {
            Db::rollback();
            $this->pushLog('notify:settle', '过程错误', $ex->getMessage(), 'error');
            return $this->returnData('', 0, 'UNKNOWN_ERROR');
        }
    }

    /**
     * 回滚
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    function unbet($datas, $trdobj, $cfg): array
    {
        $param = $this->getParam($datas, $cfg);
        Db::startTrans();
        try {
            $u_id = $param['user_id'];
            $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
            if (!$utuser) {
                return $this->returnData(0, 0, 'INVALID_USER');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uid'], '=', $utuser->ut_uid)->find();

            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $datas['table_id'];
            $round_id = $datas['txn_id'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData([], 1001, 'GAME_NOT_FOUND');
            }
            $bet = GameBets::where('gb_gid', $gid)
                ->where('gb_sn', $datas['param']['refId'])
                ->find();
            $balance = $this->getUserBalance($user_id);
            $balance = Third::transExchangerate($balance, 1);

//            $amount = $datas['amount'];
//            $amount = Third::transExchangerate($amount, 0);
            if (!$bet || $bet->gb_winlose == 0) { // 不存在注单 || 已经回滚（gb_winlose == 0）
                return $this->returnData($balance);
            }

            $betinfo = GameBetsex::where('gbe_id', $bet->gb_id)->value('gbe_betinfo');
            if (!$betinfo) {
                return $this->returnData(0, 0, 'INVALID_DEBIT');
            }

            // 获取注单金额，还原注单金额
            $amount = $bet->gb_price;
            $msg = UserModel::betRemarks($datas['param']['refId'], $cfg['trdcfg']['tc_name'], $gid, '取消');
            User::accountChange($user_id, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id,
                $bet->gb_price, $bet->gb_cjprice);
            $bet->gb_betinfo = json_encode($datas['param']);
            $bet->gb_overtime = time();
            $bet->gb_winlose = GameBets::INVALID;
            $bet->save();
            Db::commit();
            $balance = $this->getUserBalance($user_id);
            return $this->returnData($balance);
        } catch (\Exception $ex) {
            Db::rollback();
            $this->pushLog('notify:settle', '过程错误', $ex->getMessage(), 'error');
            return $this->returnData([], 1001, 'Something Error');
        }
    }

    //获取用户邀请码
    private function getUserRecCode($playername)
    {
        return explode('_', $playername)[1];
    }

    //处理params
    private function getParam($param, $cfg = null)
    {
        //设置平台id
        if (isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id']) {
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param['param'] ?? $param;
    }

    //通用返回

    /**
     * @param $balance
     * @param $status int 0 error 1 success
     * @param  string  $error
     * @return array
     */
    private function returnData($balance, int $status = 1, string $error = 'UNKNOWN_ERROR'): array
    {
        $returnData = [
            'status' => $status,
        ];
        if ($status == 1) {
            $returnData['balance'] = $balance;
        } else {
            $returnData['error'] = $error;
        }

        return $returnData;
    }

    private function pushLog($action, $msg, $data, $dir = 'log')
    {
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid)
    {
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance, 1);
        return round($balance, 4);
    }
}
