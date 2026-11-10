<?php


namespace app\common\FastGame;

use app\common\model\UserModel;
use app\model\GameBetsex;
use app\common\FastGame\Base\{Game, Meta, Notify, Redis, Third, User, Utils};
use app\model\GameBets;
use think\facade\Db;

class Notify_bgaming extends Notify
{
    private $tcid = 0;
    private $dealid = '';

    private $uname = '';

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
            $this->uname = $param['user'];
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
        $api_token = $cfg['trdcfg']['tc_tag'];
        if ($token == $api_token) {
            return true;
        } else {
            return false;
        }
    }

    //获取用户余额
    public function CallBalance($datas, $trdobj = null, $cfg = null)
    {
        $param = $this->getParam($datas, $cfg);
        $this->pushLog($param, 'balance', $trdobj);

        try {
            //获取用户数据
            $userDetail = UserModel::where(Meta::UFIELD['uname'], '=', $this->uname)->find();
            $userBalance = $this->getUserBalance($userDetail[Meta::UFIELD['uid']]);

            return $this->returnData([
                'cash' => $userBalance,
            ]);
        } catch (\Exception $e) {
            $this->pushLog($param, 'balance error：'.$e->getMessage(), [$datas, $trdobj], 'error');
        }
    }

    // 加扣点

    /**
     * @throws \Exception
     */
    public function PointInout($datas, $trdobj = null, $cfg = null)
    {
        // code 加扣点类型 1:加点 2:扣点 code == 2 下注 code == 1 结算
        $param = $this->getParam($datas, $cfg);
        if (isset($param['dealid'])) {
            $this->dealid = $param['dealid'];
        }
        if ($param['code'] == 2) {
            return $this->bet($datas, $trdobj, $cfg);
        } else {
            if ($param['code'] == 1) {
                return $this->settle($datas, $trdobj, $cfg);
            }
        }
    }

    /**
     * 下注
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function bet($datas, $trdobj, $cfg): array
    {
        $time = time();
        Db::startTrans();
        try {
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $this->uname)->find();
            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $datas['gameinfo'];
            $round_id = $datas['roundid'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData([], 1001, 'Game not found');
            }
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
            $balance = $this->getUserBalance($user_id);

            if ($bet) { // 存在注单
                return $this->returnData([
                    'balance' => $balance,
                ]);
            }
            $amount = $datas['money'];
            $amount = Third::transExchangerate($amount, 0);
            if ($amount > $balance) { // 余额不足
                return $this->returnData([], 1085, '转账失败，该账号余额不足');
            }

            $msg = UserModel::betRemarks($round_id, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true,
                $cfg['trdcfg']['tc_id'], $gid);
            $data = array(
                'gb_gid'      => $gid,
                'gb_tcid'     => $cfg['trdcfg']['tc_id'],
                'gb_uid'      => $user_id,
                'gb_intime'   => $time,
                'gb_price'    => $amount,
                'gb_cjprice'  => $betcjprice,
                'gb_sn'       => $round_id,
                'gb_winlose'  => GameBets::UNSETTLED,
                'gb_bettime'  => $time,
                'gb_overtime' => $time,
                'gb_winprice' => 0,
                'gb_betinfo'  => json_encode($datas)
            );
            //写入注单
            $bet_id = (new GameBets())->insertGetId($data);

            $balance = UserModel::where(Meta::UFIELD['uid'], $user_id)->value(Meta::UFIELD['ubalance']);
            $balance = Third::transExchangerate($balance, 1);
            DB::commit();
            return $this->returnData([
                'money' => Third::transExchangerate($amount, 1),
                'cash'  => $balance,
            ]);
        } catch (\Exception $ex) {
            Db::rollback();
            $this->pushLog('notify:bet', '过程错误', $ex->getMessage(), 'error');
            return $this->returnData([], 1001, 'Something Error');
        }
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
        Db::startTrans();
        try {
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $this->uname)->find();
            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $datas['gameinfo'];
            $round_id = $datas['roundid'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('notify:settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData([], 1001, 'Game not found');
            }
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $round_id)->find();
            $balance = $this->getUserBalance($user_id);
            $balance = Third::transExchangerate($balance, 1);

            $amount = $datas['money'];
            $amount = Third::transExchangerate($amount, 0);

            if (!$bet) { // 不存在注单
                return $this->returnData([
                    'money' => Third::transExchangerate($amount, 1),
                    'cash'  => $balance,
                ]);
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
            return $this->returnData([
                'money' => Third::transExchangerate($amount, 1),
                'cash'  => $balance,
            ]);
        } catch (\Exception $ex) {
            Db::rollback();
            $this->pushLog('notify:settle', '过程错误', $ex->getMessage(), 'error');
            return $this->returnData([], 1001, 'Something Error');
        }
    }

    /**
     * 回滚
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    function TimeoutBetReturn($datas, $trdobj, $cfg)
    {
        $param = $this->getParam($datas, $cfg);

        Db::startTrans();
        try {
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $this->uname)->find();
            $user_id = $uinfo[Meta::UFIELD['uid']];
            $game_code = $datas['gameinfo'];
            $round_id = $datas['roundid'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $game_code, 'code', 'g_id');
            if (!$gid) {
                $this->pushLog('settle', '游戏标志错误', $game_code, 'error');
                return $this->returnData([], 1001, 'Game not found');
            }
            $bet = GameBets::where('gb_gid', $gid)
                ->where('gb_sn', $datas['param']['refId'])
                ->find();
            $balance = $this->getUserBalance($user_id);
            $balance = Third::transExchangerate($balance, 1);

            $amount = $datas['money'];
            $amount = Third::transExchangerate($amount, 0);
            if (!$bet || $bet->gb_winlose == 0) { // 不存在注单 || 已经回滚（gb_winlose == 0）
                return $this->returnData([
                    'money' => Third::transExchangerate($amount, 1),
                    'cash'  => $balance,
                ]);
            }
            // 判断
            //1. 當扣點(PointInOut&code=2)成功時--->若有增点(code=1)，同意回滾(取消訂單，並增加點數)。
            //2. 當扣點(PointInOut&code=2)失敗時--->若有增点(code=1)，无需处理。
            //3. 當派彩(PointInOut&code=1)成功時--->若有增点(code=1)，无需处理。若有扣点(code=2)，同意回滚(取消訂單，並扣除點數)
            //4. 當派彩(PointInOut&code=1)失敗時--->若有扣点(code=2)，无需处理。
            //5. 已回滾過--->若再收到同筆訂單的回滾，則无需处理。
            $betinfo = GameBetsex::where('gbe_id', $bet->gb_id)->value('gbe_betinfo');
            if (!$betinfo) {
                return $this->returnData([
                    'money' => Third::transExchangerate($amount, 1),
                    'cash'  => $balance,
                ]);
            }
            $betinfo = json_decode($betinfo, true);
            // 加扣点类型 1:加点 2:扣点
            if (!($betinfo['code'] == 2 && $datas['code'] == 1) && !($betinfo['code'] == 1 && $datas['code'] == 2)) {
                return $this->returnData([
                    'money' => Third::transExchangerate($amount, 1),
                    'cash'  => $balance,
                ]);
            }

            $amount = $bet->gb_price;
            $msg = UserModel::betRemarks($datas['param']['refId'], $cfg['trdcfg']['tc_name'], $gid, '取消');
            User::accountChange($user_id, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            $bet->gb_betinfo = json_encode($datas['param']);
            $bet->gb_overtime = time();
            $bet->gb_winlose = GameBets::INVALID;
            $bet->save();
            Db::commit();
            $balance = $this->getUserBalance($user_id);
            return $this->returnData([
                'money' => Third::transExchangerate($amount, 1),
                'cash'  => $balance,
            ]);
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
    private function returnData($data, $code = 0, $msg = 'success'): array
    {
        $returnData = [
            'code' => $code,
            'msg'  => $msg
        ];
        if ($data) {
            $returnData['data'] = $data;
            if ($this->dealid) {
                $returnData['data']['dealid'] = $this->dealid;
            }
            $returnData['data']['user'] = $this->uname;
            $returnData['data']['timestamp'] = time();
        }

//        $this->pushLog('notify:return', '', $returnData);
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
