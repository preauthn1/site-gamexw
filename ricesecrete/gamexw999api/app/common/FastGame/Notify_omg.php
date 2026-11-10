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

class Notify_omg extends Notify
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

    public function verify_session($data, $trdobj, $cfg): array
    {
        $arr['data'] = [];
        $msg = '查询余额错误';
        try {
            $uid = UserModel::where('rec_code', $data['operator_player_session'])->value('id');
            if(empty($uid)){
                return $this->ReturnData(4, '无效用户', $arr);
            }

            $balance = $this->getUserBalance($uid);
            $arr['data']['uname'] = $data['operator_player_session'];
            $arr['data']['nickname'] = $data['operator_player_session'];
            $arr['data']['balance'] = Third::transExchangerate($balance, 1, 2);
            $msg = 'ok';
            $code = 1;
        } catch (\Throwable $ex) {
            $msg = 'fail';
            $code = 5;
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //获取余额
    public function get_balance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $userId = $param['uname'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            $userBalance = $this->getUserBalance($uid);
            $arr['data']['balance'] = Third::transExchangerate($userBalance, 1, 2);

            return $this->ReturnData(1, 'ok', $arr);

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
        }
    }

    //设置余额
    public function change_balance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'set balance', $trdobj);
        if($param['type'] == 1){//下注
            return $this->bet($param, $trdobj, $cfg);

        }else if($param['type'] == 2){//取消
            return $this->betRollback($param, $trdobj, $cfg);

        }else if($param['type'] == 3){//结算
            return $this->Settle($param, $trdobj, $cfg);

        }else{//确认流程是否结束
            return $this->isend($param, $trdobj = null, $cfg = null);

        }
    }

    //取消
    private function betRollback($datas, $trdobj, $cfg) {
        try {
            $username = $datas['uname'];
            $betId = $datas['cancel_order_id'];
            $gameCode = $datas['game_id'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData('10001', 'SC_USER_NOT_EXISTS');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if (!$bet) {
                //注单不存在
                $this->pushLog($datas, '注单不存在', $trdobj);
                return $this->returnData('10001', 'bet is exist');
            }
            if ($bet->gb_winlose == 0) {
                $arr['data']['balance'] = Third::transExchangerate($userBalance, 1, 2);

                return $this->returnData('1','ok',$arr);
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
                    return $this->returnData('10001', 'SC_INSUFFICIENT_FUNDS');
                }
            }
            $gid = $bet->gb_gid;

        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'SC_INTERNAL_ERROR');
        }

        Db::startTrans();
        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                return $this->returnData('10001', 'SC_INTERNAL_ERROR');
            }
            if ($userAddAmount > 0) {
                //增加或减少用户余额
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '注单回滚');
                if ($symbol == '+') {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                } else {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'SC_INTERNAL_ERROR');
        }

        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        $arr['data']['balance'] = Third::transExchangerate($balance, 1, 2);

        return $this->returnData('1','ok',$arr);
    }

    //常规结算
    private function Settle($params, $trdobj, $cfg){

        $param = $this->getParam($params, $cfg);

        $this->pushLog($param, '常规结算', $trdobj);

        try {



            $userId = $param['uname'];
            $ts = $param['timestamp'];
            $betId = $param['session_id'];


            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            $award = Third::transExchangerate($param['money'], 0, 2);


            $gid = $bet->gb_gid;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();


            if( !$uinfo ){
                return $this->returnData('10001','user error');
            }



            $bet->gb_overtime = $ts;

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
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

                $bet->gb_cjprice = $betcjprice;
                $bet->save();
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            $arr['data']['balance'] = Third::transExchangerate($balance, 1, 2);
            return $this->returnData('1','ok',$arr);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData('10001','settle error');
        }
    }

    //下注
    private function bet($datas, $trdobj, $cfg) {
        try {
            $username = $datas['uname'];
            $betId = $datas['session_id'];
            $gameCode = $datas['game_id'];
            $amount = $datas['bet'];
            $amount = Third::transExchangerate($amount, 0, 2);
            if ($amount < 0) {
                $this->pushLog($datas, '下注余额为负', $trdobj);
                return $this->returnData('10001', 'amount is negative');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->returnData('10001', 'user error');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if ($amount > $userBalance) {
                $this->pushLog($datas, '用户余额不足', $trdobj);
                return $this->returnData('10001', 'INSUFFICIENT FUNDS');
            }
            //判断注单是否存在,存在直接返回
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if ($bet) {
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $arr['data']['balance'] = Third::transExchangerate($balance, 1, 2);
                return $this->returnData('1','ok',$arr);
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'tag', 'g_id');

            $data = [
                'gb_gid' => $gid,
                'gb_cusfield'=>$datas['session_id'],
                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                'gb_intime' => $datas['timestamp'],
                'gb_price' => $amount,
                'gb_sn' => $betId,
                'gb_winlose' => 1,
                'gb_bettime' => $datas['timestamp'],
                'gb_overtime' => null,
                'gb_winprice' => 0,
                'gb_betinfo' => json_encode($datas)
            ];
        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'system error');
        }
        Db::startTrans();
        try {
            //扣减用户余额
            $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

            //写入注单
            $data['gb_cjprice'] = $betcjprice;
            $res = (new GameBets())->insertGetId($data);
            if (!$res) {
                Db::rollback();
                $this->pushLog($datas, '写入注单错误', $trdobj);
                return $this->returnData('10001', 'bet insert error');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            $arr['data']['balance'] = Third::transExchangerate($balance, 1, 2);

            return $this->returnData('1','ok',$arr);
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData('10001', 'SC_INVALID_REQUEST');
        }
    }

    //结束
    private function isend($datas, $trdobj, $cfg){
        //获取注单
        $bet = GameBets::where('gb_sn', '=', $datas['session_id'])->find();
        if($bet->gb_winlose == 1){

            $bet->gb_winlose = $bet->gb_winprice > $bet->gb_price?3:2;
            $bet->gb_overtime = $datas['timestamp'];


        }

        $bet->gb_betinfo = json_encode($datas);
        $rlt = $bet->save();

        if ($rlt) {
            $userId = $datas['uname'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            $userBalance = $this->getUserBalance($uid);
            $arr['data']['balance'] = Third::transExchangerate($userBalance, 1, 2);
            return $this->returnData(1, 'ok', $arr);
        }else{
            return $this->returnData(10001, 'balance is not enough', []);
        }
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 1, string $msg = 'ok', array $data = []): array
    {
        $arr['code'] = $code;
        $arr['msg'] = $msg;
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
