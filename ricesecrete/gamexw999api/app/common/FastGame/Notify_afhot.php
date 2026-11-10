<?php


namespace app\common\FastGame;

use app\common\lib\Util;
use app\common\model\UserMsgModel;
use Carbon\Carbon;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsex, GameBetsSon, GameCardsSpecial, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\Exception;
use think\exception\HttpException;
use think\facade\Db;

class Notify_afhot extends Notify
{
    private $tcid = 27;

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
        //设置x-mgp-req-id
        if( isset($datas['header']['requestid']) ){
            $this->x_mgp_req_id = $datas['header']['requestid'];
        }
//        s=/game_notify/mg/notify/login
        //获取回调对应方法
        $s = $param['s'];
        $sArr = explode('/', $s);
        $action = $sArr[count($sArr) - 1];
        unset($param['s']);unset($param['action_method']);
//        $action = $param['name'];
//        $signflag = $trdobj->CheckSign($cfg,$param);
//        if($signflag){
        return $this->$action($param, $trdobj, $cfg);
//        } else {
//            Utils::L($param['third'],['notify:'.$action,'签名错误',$cfg]);
//            abort(403, 'Signature code');
//        }
    }

    public function verifySession($data, $trdobj, $cfg): array
    {
        $arr['data'] = [];
        $msg = '验证错误';
        try {
            $uid = UserModel::where('rec_code', $data['player_name'])->value('id');
            if(empty($uid)){
                return $this->ReturnData(10099, '无效请求', $arr);
            }

            $arr['data']['playerName'] = $data['player_name'];
            $arr['data']['nickName'] = $data['player_name'];
            $arr['data']['apiKey'] = $data['apiKey'];
            $arr['data']['currency'] = $cfg['trdcfg']['tc_currency'];
            $msg = '成功';
            $code = 10000;
        } catch (\Throwable $ex) {
            $msg = 'fail';
            $code = 10099;
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //获取余额
    public function getWallet($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $userId = $param['player_name'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);

            $userBalance = $this->getUserBalance($uid);

            $arr['data']['balanceAmount'] = floatval(Third::transExchangerate($userBalance, 1, 2));
            $arr['data']['bonusAmount'] = 0;

            $arr['data']['playerName'] = $param['player_name'];
            $arr['data']['apiKey'] = $param['api_key'];
            $arr['data']['currency'] = $cfg['trdcfg']['tc_currency'];
            $msg = '成功';
            $code = 10000;

        }catch (\Exception $e){
            $code = 10099;
            $msg = 'System Error';
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
        }
        return $this->ReturnData($code, $msg, $arr??[]);
    }

    //下注和结算合并接口
    public function transfer($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'set balance', $trdobj);

        try {

            $userId = $param['player_name'];
            $gameId = $param['game_id'];
            $roundId = $param['round_id'];
            $type = $param['type'];

            $amount = Third::transExchangerate($param['bet_amount'], 0, 4);

            $award = Third::transExchangerate($param['bet_amount'] + $param['transfer_amount'], 0, 4);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->ReturnData('10009', 'user error');
            }
            if( $amount > $userBalance ){
                return $this->ReturnData('10011', 'balance error');
            }

            $ts=time();
            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> $award > 0 ? 3 : 2,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=> $award,
                'gb_betinfo'=>json_encode($param)
            ];
        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData('10012', 'system error');
        }
        Db::startTrans();
        try {

//            0	投注派彩
//            1	投注
//            2	派彩
//            3	取消
//            4	重置(部分游戏会重置游戏，如体育竞技，电子竞技等)
//            5	调整余额
//            6	批量派彩
//            7	批量下注

            //判断是写入还是修改
            if($type == 1 || $type == 0){
                //写入注单
                $rlt=(new GameBets())->insertGetId($data);
                $gbid = $rlt;
            }else{
                $bet = GameBets::where('gb_sn', '=', $roundId)->find();
                if( !$bet ){
                    return $this->returnData('10001','user error');
                }

                $bet->gb_overtime = $ts;
                $bet->gb_winlose = $award > 0 ? 3 : 2;
                $bet->gb_winprice += $award;
                $bet->gb_betinfo = json_encode($params);
                $rlt = $bet->save();
                $gbid = $bet->gb_id;
            }

            if( !$rlt ){
                Db::rollback();
                return $this->ReturnData('10012', 'bet init error');
            }
            //写入下注信息
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            (new GameBets())->where('gb_id', '=', $rlt)->update([
                'gb_cjprice' => $betcjprice
            ]);
            $data['gb_cjprice'] = $betcjprice;
            if( $award > 0){
                //结算
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $gbid, $data['gb_price'], $data['gb_cjprice']);

            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $arr['data']['balanceAmount'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['data']['bonusAmount'] = 0;

            $arr['data']['playerName'] = $param['player_name'];
            $arr['data']['apiKey'] = $param['api_key'];
            $arr['data']['currency'] = $cfg['trdcfg']['tc_currency'];
            $msg = '成功';
            $code = 10000;
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $msg = '系统错误';
            $code = 10012;

        }
        return $this->ReturnData($code, $msg,$arr??[]);
    }

    //取消
    private function reverse($datas, $trdobj, $cfg) {
        try {
            $username = $datas['playerid'];
            $betId = $datas['roundid'];
            $gameCode = $datas['gamecode'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->ReturnData('10001', 'user error');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if (!$bet) {
                //注单不存在
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData('10001', 'bet not exist');
            }
            if ($bet->gb_winlose == 0) {
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                $arr['bonusbalance'] = 0;
                $arr['externaltransid'] = $datas['externalsessionid'];

                return $arr;
            }
            //计算该退回给用户多少钱
            $userAddAmount = $bet->gb_price - $bet->gb_winprice;
            if ($userAddAmount > 0) {
                $symbol = '+';
                $type = 3;
            } else {
                $symbol = '-';
                $type = 4;
                $userAddAmount = abs($userAddAmount);
                if ($userAddAmount > $userBalance) {
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    return $this->returnData('10001', 'balance error');
                }
            }
            $gid = $bet->gb_gid;

        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'system error');
        }

        Db::startTrans();
        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                return $this->returnData('10001', 'bet update error');
            }
            if ($userAddAmount > 0) {
                //增加或减少用户余额
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '注单回滚');
                if ($symbol == '+') {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                } else {
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg);
                    (new GameBets())->where('gb_id', '=', $bet->gb_id)->update([
                        'gb_cjprice' => $betcjprice
                    ]);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'system error');
        }

        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
        $arr['currency'] = $cfg['trdcfg']['tc_currency'];
        $arr['bonusbalance'] = 0;
        $arr['externaltransid'] = $datas['externalsessionid'];
        return $arr;
    }

    //批量结算
    private function batchTransfer($params, $trdobj, $cfg){

        $param = $this->getParam($params, $cfg);

        $this->pushLog($param, '常规结算', $trdobj);

        try {
            $userId = $param['playerid'];
            $ts = time();
            $betId = $param['roundid'];


            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            $award = Third::transExchangerate($param['creditamount'], 0, 2);


            $gid = $bet->gb_gid;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();


            if( !$uinfo ){
                return $this->returnData('10001','user error');
            }



            $bet->gb_overtime = $ts;
            $bet->gb_winlose = $award > 0 ? 3 : 2;
            $bet->gb_winprice += $award;
            $bet->gb_betinfo = json_encode($params);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData('10001', 'update bet error');
            }

            if ($award > 0) {
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid,'注单结算');
                User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $params['externalsessionid'];
            return $arr;

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData('10001','settle error');
        }
    }

    //金额得调整
    private function adjustment($datas, $trdobj, $cfg){
        Db::startTrans();
        try {
            $username = $datas['username'];
            $gameCode = $datas['gameCode'];
            $roundId = $datas['roundId'];#回合id
            $amount = $datas['amount'];#调整金额
            $externalTransactionId = $datas['externalTransactionId'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if( !$uinfo ){
                //用户不存在
                Db::rollback();
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData([], 'SC_USER_NOT_EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取主注单和子注单
            $bet = GameBets::where('gb_trans_no', '=', $externalTransactionId)->find();
            if( !$bet ){
                //注单不存在
                Db::rollback();
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData([], 'SC_TRANSACTION_NOT_EXISTS');
            }
            $betSon = GameBetsSon::where('gb_sn_ac', '=', $roundId)->find();
            $addAmount = $amount;
            if( $amount > 0){
                $symbol = '+';
                $type = 3;
            }else{
                $symbol = '-';
                $type = 4;
                $amount = abs($addAmount);
                if( $amount > $userBalance ){
                    Db::rollback();
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    return $this->returnData([], 'SC_INSUFFICIENT_FUNDS');
                }
            }
            $gid = $this->thirdClass->getGameIdByGameCode($gameCode);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
        try {
            //主注单已结算则修改注单金额和状态
            if( !in_array($bet->gb_winlose, [0, 1]) ){
                $bet->gb_winprice = $bet->gb_winprice + $amount >= 0 ? $bet->gb_winprice + $amount : 0;
                $bet->gb_winlose = $bet->gb_winprice > 0 ? 3 : 2;
                $bet->gb_betinfo = json_encode($datas);
                $rlt = $bet->save();
                if( !$rlt ){
                    Db::rollback();
                    $this->pushLog($datas, '更新主注单错误', $trdobj);
                    return $this->returnData([], 'SC_INVALID_REQUEST');
                }
            }
            if( $betSon ){
                //存在子注单则修改自注单金额
                $betSon->gb_winprice = $betSon->gb_winprice + $amount >= 0 ? $betSon->gb_winprice + $amount : 0;
                $betSon->gb_winlose = $betSon->gb_winprice > 0 ? 3 : 2;
                $betSon->gb_betinfo = json_encode($datas);
                $rlt = $betSon->save();
                if( !$rlt ){
                    Db::rollback();
                    $this->pushLog($datas, '更新子注单错误', $trdobj);
                    return $this->returnData([], 'SC_INVALID_REQUEST');
                }
            }
            if( $addAmount > 0 ){
                //增加或减少用户余额
                $msg = UserModel::betRemarks($bet->gb_sn, $cfg['trdcfg']['tc_name'], $gid, '调整');
                if( $symbol == '+' ){
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $addAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                }else{
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $addAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                    (new GameBets())->where('gb_id', '=', $bet->gb_id)->update([
                        'gb_cjprice' => $betcjprice
                    ]);
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData([
                'balance' => (float)$balance,
                'username' => $username
            ]);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData([], 'SC_INVALID_REQUEST');
        }
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 10099, string $msg = '无效请求', array $data = []): array
    {
        $arr['status'] = $code;
        $arr['msg'] = $msg;
        $arr['timestamp'] = time() * 1000;
        $arr = array_merge($arr, $data);
        return $arr;
    }

    //处理params
    private function getParam($param, $cfg = null){
        //设置平台id
        if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param['param']??$param;
    }

    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
