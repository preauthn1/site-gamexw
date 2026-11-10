<?php


namespace app\common\FastGame;

use app\common\lib\Util;
use app\common\model\UserMsgModel;
use Carbon\Carbon;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsex, GameBetsSon, GameCardsSpecial, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use app\common\model\UserFreebetsModel;
use think\Exception;
use think\exception\HttpException;
use think\facade\Db;

class Notify_gametechapi extends Notify
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

    //获取余额
    private function getbalance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $currency = $cfg['trdcfg']['tc_currency'];
            $userId = $param['playerid'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            if (!$uid) {
                //用户不存在
                $this->pushLog($params, '用户名不存在', $trdobj);
                return $this->ReturnData('404', 'PLAYER_NOT_FOUND');
            }
            $userBalance = $this->getUserBalance($uid);

            $arr['cashbalance'] = floatval(Third::transExchangerate($userBalance, 1, 2));
            $arr['currency'] = $currency;
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $param['externalsessionid'];
            return $arr;

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData();
        }
    }

    //多次结算
    private function ReSettle($params, $trdobj, $cfg, $bet){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, '多次结算', $trdobj);
        try {

            $userId = $param['playerid'];
            $gameId = $param['gamecode'];
            $roundId = $param['roundid'];
            $transid = $param['transid'];

            $award = Third::transExchangerate($param['creditamount'], 0, 4);

            $gid = $bet->gb_gid;
            $gb_price = $bet->gb_price;


            $gb_winprice = $award;

            //判断游戏是否结束
            if($param['roundended']){
                $gb_winlose = $gb_winprice > 0 ? 3 : 2;
            }else{
                $gb_winlose = 1;
            }

            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();

            if( !$uinfo ){
                return $this->returnData('404','PLAYER_NOT_FOUND');
            }
            $ts = time();
            //未迁移
            if($bet){
                $userAddAmount = $gb_winprice;
                $bet->gb_winlose = $gb_winlose;
                $bet->gb_overtime = $ts;
                $bet->gb_trans_no = $transid;
                $bet->gb_winprice += $gb_winprice;
                $bet->gb_betinfo = json_encode($params);
                $rlt = $bet->save();
                $gb_id = $bet->gb_id;
                $gb_price = $bet->gb_price;
                $gb_cjprice = $bet->gb_cjprice;
            }
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData();
            }
            //计算该退回给用户多少钱

            if ($userAddAmount > 0) {
                $symbol = '+';
                $type = 3;
            } else {
                $symbol = '-';
                $type = 4;
            }

            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid,'多次结算');
            if ($symbol == '+') {
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $gb_id, $gb_price, $gb_cjprice);
            } else {
                $userAddAmount = abs($userAddAmount);
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg);
                (new GameBets())->where('gb_id', '=', $gb_id)->update([
                    'gb_cjprice' => $betcjprice
                ]);
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $param['externalsessionid'];
            Db::commit();
            return $arr;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData();
        }
    }

    //多次投注
    private function Rebets($params, $trdobj, $cfg, $bet,$bonus,$freebet){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, '多次投注', $trdobj);
        try {

            $userId = $param['playerid'];
            $gameId = $param['gamecode'];
            $roundId = $param['roundid'];
            $transid = $param['transid'];

            $amount = Third::transExchangerate($param['debitamount'], 0, 4);

            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();

            if( !$uinfo ){
                return $this->returnData('404','PLAYER_NOT_FOUND');
            }
            $ts = time();
            if($bet){
                $bet->gb_cjprice += $amount;
                $bet->gb_price += $amount;

                $bet->gb_trans_no = $transid;
                $bet->gb_betinfo = json_encode($params);
                $rlt = $bet->save();
                $gb_id = $bet->gb_id;
            }
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData();
            }
            //计算该退回给用户多少钱

            $symbol = '-';
            $type = 4;

            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gb_id,'多次结算');
            if (!$bonus) {//如果不是免费游戏则扣减用户钱包
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, $symbol, $type, $msg);
            }else{
                $betcjprice = 0;
            }
            (new GameBets())->where('gb_id', '=', $gb_id)->update([
                'gb_cjprice' => $betcjprice
            ]);

            if ($bonus) {//核销免费游戏
                UserFreebetsModel::VerificationFreeBet($freebet,$bet->gb_sn);
                //if (empty($freebet->ufg_usedbygbsn)) $freebet->ufg_usedbygbsn = $order_sn;
                //$freebet->ufg_usedprice += $amount;
                //$freebet->save();
            }

            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $param['externalsessionid'];
            Db::commit();
            return $arr;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData();
        }
    }

    //下注和结算合并接口
    private function debitandcredit($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'set balance', $trdobj);

        try {

            $userId = $param['playerid'];
            $gameId = $param['gamecode'];
            $roundId = $param['roundid'];
            $transid = $param['transid'];

            $bet = GameBets::where('gb_sn', '=', $roundId)->find();//热表注单

            $bet1 = Db::table('dq_game_bets')->where('gb_sn', $roundId)->field('*')->find();//主表注单

            if($bet || $bet1){//表示是多次结算.走重新结算接口
                if((isset($bet->gb_trans_no) && $bet->gb_trans_no == $transid) || (isset($bet1['gb_trans_no']) && $bet1['gb_trans_no'] == $transid)){
                    $uid = $bet->gb_uid??$bet1['gb_uid'];
                    $balance = $this->getUserBalance($uid);
                    //判断是否同一次请求,如果是，直接返回
                    $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                    $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                    $arr['bonusbalance'] = 0;
                    $arr['externaltransid'] = $param['externalsessionid'];
                    return $arr;
                }else if((isset($bet->gb_winlose) && $bet->gb_winlose > 1) || (isset($bet1['gb_winlose']) && $bet1['gb_winlose'] > 1)){//回合结束
                    return $this->returnData('403','ROUND_ENDED');
                }
                return $this->ReSettle($params, $trdobj, $cfg, $bet);
            }

            $amount = Third::transExchangerate($param['debitamount']??0, 0, 4);

            $award = Third::transExchangerate($param['creditamount']??0, 0, 4);

            //判断游戏是否结束

            if($param['roundended']){
                $gb_winlose = $award > 0 ? 3 : 2;
            }else{
                $gb_winlose = 1;
            }

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'code', 'g_id');
            if(!$gid){
                return $this->ReturnData('404', 'GAME_NOT_FOUND');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();

            if( !$uinfo ){
                return $this->ReturnData('404', 'PLAYER_NOT_FOUND');
            }

            //判断是否是免费游戏
            $bonus=false;
            if (isset($param['externalfreebetid']) && !empty($param['externalfreebetid'])){
                $bonusCode = $param['externalfreebetid'];
                $freebet=UserFreebetsModel::find($bonusCode);
                $bonus=!!$freebet;
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( $amount > $balance && $bonus){
                $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                $arr['bonusbalance'] = 0;
                return $this->ReturnData('402', 'NOT_SUFFICIENT_FUNDS',$arr);
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
                'gb_winlose'=> $gb_winlose,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_trans_no'=>$transid,
                'gb_winprice'=> $award,
                'gb_betinfo'=>json_encode($param)
            ];
        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData();
        }
        Db::startTrans();
        try {
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if( !$rlt ){
                Db::rollback();
                return $this->ReturnData();
            }
            //写入下注信息
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            if (!$bonus) {//如果不是免费游戏则扣减用户钱包
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            }else{
                $betcjprice = 0;
            }
            (new GameBets())->where('gb_id', '=', $rlt)->update([
                'gb_cjprice' => $betcjprice
            ]);
            $data['gb_cjprice'] = $betcjprice;
//            if( !$rlt ){
//                $returnData['error'] = 3033;
//                Db::rollback();
//                return $returnData;
//            }
            if( $award > 0){
                //结算
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $data['gb_price'], $data['gb_cjprice']);
//                if( !$rlt ){
//                    $returnData['error'] = 3033;
//                    Db::rollback();
//                    return $returnData;
//                }
            }
            if ($bonus) {//核销免费游戏
                UserFreebetsModel::VerificationFreeBet($freebet,$roundId);
                //if (empty($freebet->ufg_usedbygbsn)) $freebet->ufg_usedbygbsn = $order_sn;
                //$freebet->ufg_usedprice += $amount;
                //$freebet->save();
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['currency'] = $cfg['trdcfg']['tc_currency'];
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $param['externalsessionid'];
            Db::commit();
            return $arr;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            return $this->ReturnData();
        }
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
                return $this->ReturnData('404', 'PLAYER_NOT_FOUND');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if (!$bet) {
                //注单不存在
                $this->pushLog($datas, '注单不存在', $trdobj);
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                $arr['bonusbalance'] = 0;
                $arr['externaltransid'] = $datas['externalsessionid'];

                return $arr;
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
                    $arr['cashbalance'] = floatval(Third::transExchangerate($userBalance, 1, 2));
                    $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                    $arr['bonusbalance'] = 0;
                    return $this->returnData('402', 'NOT_SUFFICIENT_FUNDS',$arr);
                }
            }
            $gid = $bet->gb_gid;

        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData();
        }

        Db::startTrans();
        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                return $this->returnData();
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
            return $this->returnData();
        }

        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
        $arr['currency'] = $cfg['trdcfg']['tc_currency'];
        $arr['bonusbalance'] = 0;
        $arr['externaltransid'] = $datas['externalsessionid'];
        return $arr;
    }

    //常规结算
    private function credit($params, $trdobj, $cfg){

        $param = $this->getParam($params, $cfg);

        $this->pushLog($param, '常规结算', $trdobj);

        try {
            $userId = $param['playerid'];
            $ts = time();
            $betId = $param['roundid'];
            $transid = $param['transid'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            if( !$uinfo ){
                return $this->returnData('404','PLAYER_NOT_FOUND');
            }
            $uid = $uinfo[Meta::UFIELD['uid']];
            $balance = $this->getUserBalance($uid);

            $bet = GameBets::where('gb_sn', '=', $betId)->find();//热表注单
            $bet1 = Db::table('dq_game_bets')->where('gb_sn', $betId)->field('*')->find();//主表注单

            if(!$bet && !$bet1){//注单不存在，相当于走合并结算注单

                return $this->debitandcredit($params, $trdobj, $cfg);

//                $balance = $this->getUserBalance($uid);
//                //判断是否同一次请求,如果是，直接返回
//                $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
//                $arr['currency'] = $cfg['trdcfg']['tc_currency'];
//                $arr['bonusbalance'] = 0;
//                $arr['externaltransid'] = $param['externalsessionid'];
//                return $arr;
            }else{
                //如果幂等，直接返回
                if(isset($bet->gb_trans_no) && $bet->gb_trans_no == $transid){

                    //判断是否同一次请求,如果是，直接返回
                    $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                    $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                    $arr['bonusbalance'] = 0;
                    $arr['externaltransid'] = $param['externalsessionid'];
                    return $arr;
                }else if((isset($bet->gb_winlose) && $bet->gb_winlose > 1) || (isset($bet1['gb_winlose']) && $bet1['gb_winlose'] > 1)){//回合结束
                    return $this->returnData('403','ROUND_ENDED');
                }else{
                    return $this->ReSettle($params, $trdobj, $cfg, $bet);
                }
            }
            $award = Third::transExchangerate($param['creditamount'], 0, 4);
            if($param['roundended']){
                $gb_winlose = $award > 0 ? 3 : 2;
            }else{
                $gb_winlose = 1;
            }


            $gid = $bet->gb_gid;


            $bet->gb_overtime = $ts;
            $bet->gb_winlose = $gb_winlose;
            $bet->gb_winprice += $award;
            $bet->gb_betinfo = json_encode($params);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData();
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
            $this->returnData();
        }
    }

    //下注
    private function debit($datas, $trdobj, $cfg) {
        try {
            $username = $datas['playerid'];
            $betId = $datas['roundid'];
            $gameCode = $datas['gamecode'];
            $amount = $datas['debitamount'];
            $transid = $datas['transid'];
            $amount = Third::transExchangerate($amount, 0, 4);
            $currency = $datas['currency'];
            if ($amount < 0) {
                $this->pushLog($datas, '下注余额为负', $trdobj);
                return $this->returnData('401', 'AUTHENTICATION_FAILED');
            }
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                return $this->ReturnData('404', 'PLAYER_NOT_FOUND');
            }
            //判断是否是免费游戏
            $bonus=false;
            if (isset($datas['externalfreebetid']) && !empty($datas['externalfreebetid'])){
                $bonusCode = $datas['externalfreebetid'];
                $freebet=UserFreebetsModel::find($bonusCode);
                $bonus=!!$freebet;
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( $amount > $balance && $bonus){
                $this->pushLog($datas, '用户余额不足', $trdobj);
                $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                $arr['currency'] = $currency;
                $arr['bonusbalance'] = 0;
                return $this->returnData('402', 'NOT_SUFFICIENT_FUNDS',$arr);
            }
            //判断注单是否存在,存在直接返回
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if ($bet) {
                //如果幂等，直接返回
                if(isset($bet->gb_trans_no) && $bet->gb_trans_no == $transid){
                    $uid = $bet->gb_uid;
                    $balance = $this->getUserBalance($uid);
                    //判断是否同一次请求,如果是，直接返回
                    $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
                    $arr['currency'] = $cfg['trdcfg']['tc_currency'];
                    $arr['bonusbalance'] = 0;
                    $arr['externaltransid'] = $datas['externalsessionid'];
                    return $arr;
                }
                return $this->Rebets($datas, $trdobj, $cfg,$bet,$bonus,$freebet);
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'tag', 'g_id');
            if(!$gid){
                return $this->returnData('404','GAME_NOT_FOUND');
            }
            $ts = time();
            $data = [
                'gb_gid' => $gid,
                'gb_cusfield'=>$datas['roundid'],
                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                'gb_uid' => $uinfo[Meta::UFIELD['uid']],
                'gb_intime' => $ts,
                'gb_price' => $amount,
                'gb_sn' => $betId,
                'gb_winlose' => 1,
                'gb_bettime' => $ts,
                'gb_trans_no'=>$transid,
                'gb_overtime' => null,
                'gb_winprice' => 0,
                'gb_betinfo' => json_encode($datas)
            ];
        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData();
        }
        Db::startTrans();
        try {
            //扣减用户余额
            $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
            if (!$bonus) {//如果不是免费游戏则扣减用户钱包
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            }else{
                $betcjprice = 0;
            }
            //写入注单
            $data['gb_cjprice'] = $betcjprice;
            $res = (new GameBets())->insertGetId($data);
            if (!$res) {
                Db::rollback();
                $this->pushLog($datas, '写入注单错误', $trdobj);
                return $this->returnData('500', 'bet insert error');
            }
            if ($bonus) {//核销免费游戏
                UserFreebetsModel::VerificationFreeBet($freebet,$betId);
                //if (empty($freebet->ufg_usedbygbsn)) $freebet->ufg_usedbygbsn = $order_sn;
                //$freebet->ufg_usedprice += $amount;
                //$freebet->save();
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            $arr['cashbalance'] = floatval(Third::transExchangerate($balance, 1, 2));
            $arr['currency'] = $currency;
            $arr['bonusbalance'] = 0;
            $arr['externaltransid'] = $datas['externalsessionid'];
            return $arr;
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            return $this->returnData();
        }
    }

    //游戏结束确认
    private function endsession($datas, $trdobj, $cfg){
        $param = $this->getParam($datas, $cfg);
        $this->pushLog($param, 'get endsession', $trdobj);

        $arr['httpcode'] = 200;
        $arr['success'] = true;
        return $arr;
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 500, string $msg = 'INTERNAL_ERROR', array $data = []): array
    {
        $arr['httpcode'] = $code;
        $arr['errorcode'] = $msg;
        $arr['errormessage'] = '';
        $arr['currency'] = isset($data['currency'])?$data['currency']:'USD';
        $arr['cashbalance'] = isset($data['cashbalance'])?$data['cashbalance']:0;
        $arr['bonusbalance'] = isset($data['bonusbalance'])?$data['bonusbalance']:0;
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
        //$balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
