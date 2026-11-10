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

class Notify_sabasw extends Notify
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
        //获取回调对应方法
        // $s = $param['s'];
        // $sArr = explode('/', $s);
        // $action = $sArr[count($sArr) - 1];

        $content = file_get_contents('php://input');
        if(isset($_GET['sd'])){
            $data = json_decode($content,true);
        }else{
            $data = json_decode(gzdecode($content),true);
        }
        $param['data'] = $data['message'];
        $action = $param['data']['action'];

        //$param = $this->getParam($param, $cfg);
        //$this->pushLog($param, 'get balance：pm', $trdobj);
        //$this->pushLog($data, 'get balance：data', $trdobj);

        //unset($param['s']);unset($param['action_method']);
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
    public function GetBalance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $userId = explode('_',$param['userId']);
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId['1'])->value(Meta::UFIELD['uid']);
            $userBalance = $this->getUserBalance($uid);
            return [
                'status' => '0',
                'userId' => $param['userId'],
                'balance' => floatval(Third::transExchangerate($userBalance, 1, 2)),
                'balanceTs' => date('Y-m-d\TH:i:s.vP', time()),
                'msg' => null
            ];

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
        }
    }

    //常规下注
    private function PlaceBet($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '常规下注', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            $gameId = $param['sportType'];
            $roundId = $param['refId'];

            //扣钱
            $amount = $param['debitAmount']??0;
            $amount = Third::transExchangerate($amount, 0, 2);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id')??1;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->returnData([],'904','Request Timeout');
            }
            if( $amount > $userBalance ){
                return $this->returnData([],'904','Request Timeout');
            }
            $ts = time();
            //下分（下注）
            $data = [
                'gb_gid'=>$gid??0,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> 1,
                'gb_bettime'=>$this->__datetime($param['betTime']),
                'gb_overtime'=>null,
                'gb_winprice'=> 0,
                'gb_betinfo'=>json_encode($param)
            ];
        }catch (\Exception $e){
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            return $this->returnData([],'904','Request Timeout');
        }
        Db::startTrans();
        try {
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            //echo GameBets::getLastSql();die;
            if( !$rlt ){

                Db::rollback();
                $this->returnData([],'904','Request Timeout');
            }

            Db::commit();
            $redata = array(
                "refId" => $roundId,
                "licenseeTxId" => md5(date("YmdHis") . Utils::random(16))
            );
            return $this->returnData($redata,'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //下注vgaming
    private function PlaceBetEnt($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '下注vgaming', $trdobj);

        try {

            $userId = explode('_',$param['userId']);
            $gameId = $param['productId']??1;

            if(isset($param['ticketList'][0])){
                foreach ($param['ticketList'] as $k=>$v){

                    $roundId = $v['refId'];

                    //扣钱
                    $amount = $v['actualStake'];
                    $amount = Third::transExchangerate($amount, 0, 2);

                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id')??1;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();
                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $amount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    $ts = time();
                    //下分（下注）
                    $data = [
                        'gb_gid'=>$gid??0,
                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                        'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                        'gb_intime'=>$ts,
                        'gb_price'=>$amount,
                        'gb_sn'=>$roundId,
                        'gb_winlose'=> 1,
                        'gb_bettime'=>$this->__datetime($param['betTime']),
                        'gb_overtime'=>null,
                        'gb_winprice'=> 0,
                        'gb_betinfo'=>json_encode($param)
                    ];

                    //写入注单
                    $rlt=(new GameBets())->insertGetId($data);
                    $txns[] = array(
                        "refId" => $roundId,
                        "licenseeTxId" => md5(date("YmdHis") . Utils::random(16))
                    );
                    if ($amount > 0) {
                        $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        (new GameBets())->where('gb_id', '=', $rlt)->update([
                            'gb_cjprice' => $betcjprice
                        ]);

                    }
                }

            }

            if( !$rlt ){

                Db::rollback();
                $this->returnData([],'904','Request Timeout');
            }

            Db::commit();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $redata = array(
                "userId" => $param['userId'],
                "balance" => $userBalance,
                "ticketList" => $txns
            );
            return $this->returnData($redata,'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //下注，本方法支持快乐彩、彩票、桌面游戏产品。
    private function PlaceBet3rd($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '下注，本方法支持快乐彩、彩票、桌面游戏产品', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            $gameId = $param['productId'];
            if(isset($param['ticketList'][0])){
                foreach ($param['ticketList'] as $k=>$v){

                    $roundId = $v['refId'];

                    //扣钱
                    $amount = $v['debitAmount'];
                    $amount = Third::transExchangerate($amount, 0, 2);

                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id')??1;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();
                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $amount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    $ts = time();
                    //下分（下注）
                    $data = [
                        'gb_gid'=>$gid??0,
                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                        'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                        'gb_intime'=>$ts,
                        'gb_price'=>$amount,
                        'gb_sn'=>$roundId,
                        'gb_winlose'=> 1,
                        'gb_bettime'=>$this->__datetime($param['betTime']),
                        'gb_overtime'=>null,
                        'gb_winprice'=> 0,
                        'gb_betinfo'=>json_encode($param)
                    ];

                    //写入注单
                    $rlt=(new GameBets())->insertGetId($data);
                    $txns[] = array(
                        "refId" => $roundId,
                        "licenseeTxId" => md5(date("YmdHis") . Utils::random(16))
                    );
                }

            }
            if( !$rlt ){

                Db::rollback();
                $this->returnData([],'904','Request Timeout');
            }

            Db::commit();
            $redata = array(
                "txns" => $txns
            );
            return $this->returnData($redata,'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //下注，仅支援欧洲盘。
    private function PlaceBetParlay($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '下注，仅支援欧洲盘。', $trdobj);
        Db::startTrans();
        $rlt = false;
        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $gameId = $param['ticketDetail'][$k]['sportType']??1;
                    $roundId = $v['refId'];

                    //扣钱
                    $amount = $v['debitAmount'];
                    $amount = Third::transExchangerate($amount, 0, 2);

                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id')??1;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();
                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $amount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    $ts = time();
                    //下分（下注）
                    $data = [
                        'gb_gid'=>$gid??0,
                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                        'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                        'gb_intime'=>$ts,
                        'gb_price'=>$amount,
                        'gb_sn'=>$roundId,
                        'gb_winlose'=> 1,
                        'gb_bettime'=>$this->__datetime($param['betTime']),
                        'gb_overtime'=>null,
                        'gb_winprice'=> 0,
                        'gb_betinfo'=>json_encode($param)
                    ];

                    //写入注单
                    $rlt=(new GameBets())->insertGetId($data);
                    $txns[] = array(
                        "refId" => $roundId,
                        "licenseeTxId" => md5(date("YmdHis") . Utils::random(16))
                    );
                }

            }
            if( !$rlt ){

                Db::rollback();
                $this->returnData([],'904','Request Timeout');
            }

            Db::commit();
            $redata = array(
                "txns" => $txns
            );
            return $this->returnData($redata,'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //常规确认下注
    private function ConfirmBet($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '常规确认下注', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();


                    //扣钱
                    $betAmount = $v['actualAmount']??0;
                    $betAmount = Third::transExchangerate($betAmount, 0, 2);
                    if($betAmount > 0 and $bet->gb_price != $betAmount){

                    }else{
                        $betAmount = $bet->gb_price;
                    }

                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $betAmount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }

                    $bet->gb_price = $betAmount;
                    $bet->gb_winprice = 0;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($betAmount > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                        $bet->gb_cjprice = $betcjprice;
                        $bet->save();
                    }
                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //确认下注，仅支援欧洲盘。
    private function ConfirmBetParlay($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '确认下注，仅支援欧洲盘', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();


                    //扣钱
                    $betAmount = $v['actualAmount']??0;
                    $betAmount = Third::transExchangerate($betAmount, 0, 2);
                    if($betAmount > 0 and $bet->gb_price != $betAmount){

                    }else{
                        $betAmount = $bet->gb_price;
                    }

                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $betAmount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }

                    $bet->gb_price = $betAmount;
                    $bet->gb_winprice = 0;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($betAmount > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                        $bet->gb_cjprice = $betcjprice;
                        $bet->save();
                    }
                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //确认下注，快乐彩，彩票，桌游等
    private function ConfirmBet3rd($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '确认下注，快乐彩，彩票，桌游等', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();


                    //扣钱
                    $betAmount = $v['debitAmount']??0;
                    $betAmount = Third::transExchangerate($betAmount, 0, 2);
                    if($betAmount > 0 and $bet->gb_price != $betAmount){

                    }else{
                        $betAmount = $bet->gb_price;
                    }

                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $betAmount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }

                    $bet->gb_price = $betAmount;
                    $bet->gb_winprice = 0;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($betAmount > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                        $bet->gb_cjprice = $betcjprice;
                        $bet->save();
                    }
                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //常规取消下注
    private function CancelBet($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '常规取消下注', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();


                    //钱
                    $Amount = $v['creditAmount'];
                    $Amount = Third::transExchangerate($Amount, 0, 2);



                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();


                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }


                    $bet->gb_winlose = 0;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($Amount > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '取消');

                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $Amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

                    }
                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }

    }

    //取消下注，快乐彩，彩票，桌游等
    private function CancelBet3rd($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '取消下注，快乐彩，彩票，桌游等', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();


                    //扣钱
                    $betAmount = $v['actualAmount']??0;
                    $betAmount = Third::transExchangerate($betAmount, 0, 2);



                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    if( $betAmount > $userBalance ){
                        return $this->returnData([],'904','Request Timeout');
                    }

                    $bet->gb_price = $betAmount;
                    $bet->gb_winprice = 0;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($betAmount > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                        User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

                    }
                }
            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }

    }


    //取消下注，本方法支持 Vgaming
    private function CancelBetEnt($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '取消下注，本方法支持 Vgaming', $trdobj);

        try {
            $userId = explode('_',$param['userId']);

            $betId = $param['refId'];
            $bet = GameBets::where('gb_sn', '=', $betId)->find();


            //扣钱
            $betAmount = $param['creditAmount']??0;
            $betAmount = Third::transExchangerate($betAmount, 0, 2);



            $gid = $bet->gb_gid;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->returnData([],'904','Request Timeout');
            }
            if( $betAmount > $userBalance ){
                return $this->returnData([],'904','Request Timeout');
            }


            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($params);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData([], '904','Request Timeout');
            }

            if ($betAmount > 0) {
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                User::accountChange($uinfo[Meta::UFIELD['uid']], $betAmount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }

    }

    //结算确认，本方法支持 Vgaming
    private function GetTicketInfo($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '结算确认，本方法支持 Vgaming', $trdobj);

        try {
            $betId = $param['refId'];
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if($bet){
                $re = array(
                    "ticketStatus"=>$bet->gb_winlose=1?"running":'',
                    "actualStake"=>$bet->gb_price,
                    "winlostAmount"=>0.0
                );
                return $this->returnData($re,0,'Success');
            }else {
                return $this->returnData([], '904', 'Request Timeout');
            }
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }

    }

    //常规结算
    private function Settle($params, $trdobj, $cfg){

        $param = $this->getParam($params['data'], $cfg);

        $this->pushLog($param, '常规结算', $trdobj);

        try {

            if(isset($param['txns'][0])){

                foreach ($param['txns'] as $k=>$v){

                    $userId = explode('_',$v['userId']);
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();
                    $award = Third::transExchangerate($v['creditAmount'], 0, 2);

                    if($award >= 0){
                        $gb_winprice = $award;
                    }else{
                        $gb_winprice = $bet->gb_price + $award;
                    }
                    $gid = $bet->gb_gid;
                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();


                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }

                    $ts = $this->__datetime($v['updateTime']);
                    $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                    $bet->gb_overtime = $ts;

                    $bet->gb_winprice = $gb_winprice;
                    $bet->gb_betinfo = json_encode($params);
                    $rlt = $bet->save();
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','Request Timeout');
                    }

                    if ($gb_winprice > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                        User::accountChange($uinfo[Meta::UFIELD['uid']], $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

                    }
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //结算，本方法支持 Vgaming
    private function SettleEnt($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '结算，本方法支持 Vgaming', $trdobj);

        try {
            $userId = explode('_',$param['userId']);

            $betId = $param['refId'];
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            $award = Third::transExchangerate($param['creditAmount'], 0, 2);

            if($award >= 0){
                $gb_winprice = $award;
            }else{
                $gb_winprice = $bet->gb_price + $award;
            }
            $gid = $bet->gb_gid;
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();


            if( !$uinfo ){
                return $this->returnData([],'904','Request Timeout');
            }

            $ts = time();
            $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
            $bet->gb_overtime = $ts;

            $bet->gb_winprice = $gb_winprice;
            $bet->gb_betinfo = json_encode($params);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                return $this->returnData([], '904','Request Timeout');
            }

            if ($gb_winprice > 0) {
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                User::accountChange($uinfo[Meta::UFIELD['uid']], $gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //重新结算
    private function ReSettle($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '重新结算', $trdobj);
        try {
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $userId = explode('_',$v['userId']);
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();
                    $bet1 = Db::table('dq_game_bets')->where('gb_sn', $betId)->field('*')->find();

                    if(!$bet && !$bet1){
                        return $this->returnData([],'904','bet error');
                    }
                    //echo json_encode($bet->gb_winprice);die;
                    $award = Third::transExchangerate($v['creditAmount'], 0, 2);
                    $gid = $bet->gb_gid??$bet1['gb_gid'];
                    $gb_price = $bet->gb_price??$bet1['gb_price'];

                    if($award >= 0){
                        $gb_winprice = $award;
                    }else{
                        $gb_winprice = $gb_price + $award;
                    }

                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    $ts = $this->__datetime($v['updateTime']);
                    //未迁移
                    if($bet){
                        $userAddAmount = $gb_winprice - $bet->gb_winprice;
                        //$ts = time();
                        $bet->gb_winlose = $gb_winprice > 0 ? 3 : 2;
                        $bet->gb_overtime = $ts;

                        $bet->gb_winprice = $gb_winprice;
                        $bet->gb_betinfo = json_encode($params);
                        $rlt = $bet->save();
                        $gb_id = $bet->gb_id;
                        $gb_price = $bet->gb_price;
                        $gb_cjprice = $bet->gb_cjprice;
                    }else if($bet1){//注单已迁移

                        $userAddAmount = $gb_winprice - $bet1['gb_winprice'];
                        $data = [
                            'gb_gid'=>$gid,
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                            'gb_intime'=>time(),
                            'gb_price'=>$bet1['gb_price'],
                            'gb_cjprice'=>$bet1['gb_cjprice'],
                            'gb_sn'=>$v['refId'],
                            'gb_winlose'=> $gb_winprice > 0 ? 3 : 2,
                            'gb_bettime'=>$bet1['gb_bettime'],
                            'gb_overtime'=>$ts,
                            'gb_winprice'=> $gb_winprice,
                            'gb_betinfo'=>json_encode($param)
                        ];

                        //写入注单
                        $rlt=(new GameBets())->insertGetId($data);
                        $gb_id = $rlt;
                        $gb_price = $data['gb_price'];
                        $gb_cjprice = $data['gb_cjprice'];
                    }
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','update bet error');
                    }
                    //计算该退回给用户多少钱

                    if ($userAddAmount > 0) {
                        $symbol = '+';
                        $type = 3;
                    } else {
                        //$userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                        $symbol = '-';
                        $type = 4;
                        // $userAddAmount = abs($userAddAmount);
                        // if ($userAddAmount > $userBalance) {
                        //     $this->pushLog($param, '用户余额不足', $trdobj);
                        //     return $this->returnData([], '904', 'INSUFFICIENT FUNDS');
                        // }
                    }

                    $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid,'沙巴重新结算');
                    if ($symbol == '+') {
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $gb_id, $gb_price, $gb_cjprice);
                    } else {
                        $userAddAmount = abs($userAddAmount);
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg);
                        (new GameBets())->where('gb_id', '=', $gb_id)->update([
                            'gb_cjprice' => $betcjprice
                        ]);
                    }
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //撤销结算
    private function UnSettle($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '撤销结算', $trdobj);
        try {
            if(isset($param['txns'][0])){
                foreach ($param['txns'] as $k=>$v){
                    $userId = explode('_',$v['userId']);
                    $betId = $v['refId'];
                    $bet = GameBets::where('gb_sn', '=', $betId)->find();
                    $bet1 = Db::table('dq_game_bets')->where('gb_sn', $betId)->field('*')->find();

                    if(!$bet && !$bet1){
                        return $this->returnData([],'904','bet error');
                    }

                    // if($bet->gb_winlose == 1 || $bet1['gb_winlose'] == 1){
                    //     return $this->returnData([],'904','bet is unsettled');
                    // }

                    //echo json_encode($bet->gb_winprice);die;
                    $award = Third::transExchangerate($v['debitAmount'], 0, 2);
                    $gid = $bet->gb_gid??$bet1['gb_gid'];
                    $gb_price = $bet->gb_price??$bet1['gb_price'];

                    if($award >= 0){
                        $gb_winprice = $award;
                    }else{
                        $gb_winprice = $gb_price + $award;
                    }

                    //获取用户数据
                    $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();

                    if( !$uinfo ){
                        return $this->returnData([],'904','Request Timeout');
                    }
                    $ts = time();
                    //未迁移
                    if($bet){

                        //$ts = time();
                        $bet->gb_winlose = 1;
                        $bet->gb_overtime = null;
                        $bet->gb_winprice = 0;
                        $bet->gb_betinfo = json_encode($params);
                        $rlt = $bet->save();
                        $gb_id = $bet->gb_id;
                    }else if($bet1){//注单已迁移
                        //修改主表注单未未结算
                        Db::table('dq_game_bets')->where('gb_sn', $betId)->update(['gb_winlose' => 1]);
                        $data = [
                            'gb_gid'=>$gid,
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                            'gb_intime'=>$ts,
                            'gb_price'=>$bet->gb_price??$bet1['gb_price'],
                            'gb_sn'=>$v['refId'],
                            'gb_winlose'=> 1,
                            'gb_bettime'=>$bet1['gb_bettime'],
                            'gb_overtime'=>null,
                            'gb_winprice'=> 0,
                            'gb_betinfo'=>json_encode($param)
                        ];
                        //写入注单
                        $rlt=(new GameBets())->insertGetId($data);
                        $gb_id = $rlt;
                    }
                    if( !$rlt ){
                        Db::rollback();
                        $this->pushLog($params, '修改注单失败', $trdobj);
                        return $this->returnData([], '904','update bet error');
                    }


                    if ($gb_winprice > 0) {
                        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid,'沙巴撤销结算');
                        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $gb_winprice, '-', 4, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                        (new GameBets())->where('gb_id', '=', $gb_id)->update([
                            'gb_cjprice' => $betcjprice
                        ]);
                    }
                }
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->returnData(["balance" => $balance],'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
    }

    //沙巴检查商户是否联通
    private function HealthCheck($datas, $trdobj, $cfg){
        return [
            'status' => '0',
            'msg' => null
        ];
    }



    //活着赠送结算，这种是没有投注的
    private function AdjustBalance($params, $trdobj, $cfg){
        $param = $this->getParam($params['data'], $cfg);
        $this->pushLog($param, '送钱或者扣钱', $trdobj);

        try {
            $userId = explode('_',$param['userId']);
            $gameId = 'sbsw_other';
            $roundId = $param['refId'];

            //扣钱
            $amount = $param['balanceInfo']['debitAmount']??0;
            $amount = Third::transExchangerate($amount, 0, 2);

            //加钱

            $award = $param['balanceInfo']['creditAmount']??0;
            $award = Third::transExchangerate($award, 0, 2);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'code', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId[1])->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->returnData([],'904','Request Timeout');
            }
            if( $amount > $userBalance ){
                return $this->returnData([],'904','Request Timeout');
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
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            return $this->returnData([],'904','Request Timeout');
        }
        Db::startTrans();
        try {
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if( !$rlt ){

                Db::rollback();
                $this->returnData([],'904','Request Timeout');
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
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $data['gb_price'], $data['gb_cjprice']);

            }

            Db::commit();

            return $this->returnData([],'0',null);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $this->returnData([],'904','Request Timeout');
        }
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

    //通用返回
    private function returnData($data=[], $status = '0',$msg){
        $returnData = [
            'status' => $status,
            'balanceTs' => date('Y-m-d\TH:i:s.vP', time()),
            'msg' => $msg
        ];
        $arr = array_merge($returnData,$data);

        return $arr;
    }
    private function status($winlost_amount,$gb_price,$status){
        if($winlost_amount >= 0){
            $gb_winprice = $winlost_amount;
        }else{
            $gb_winprice = $gb_price + $winlost_amount;
        }


        if(in_array($status,['refund','reject'])){

            $gb_winlose = 0;

        }elseif(in_array($status,['waiting','running'])){//waiting,

            $gb_winlose = 1;

        }elseif($gb_winprice > $gb_price){//赢

            $gb_winlose = 3;

        }elseif($gb_winprice < $gb_price){//赢

            $gb_winlose = 2;

        }else{

            $gb_winlose = 0;

        }
        return ['winlose'=>$gb_winlose,'winprice'=>$gb_winlose];
    }
    private function __datetime($datetame){
        //API数据时间的时区为UTC-4或者GMT-4
        $tz = gettimezone("-4");
        $ts = Util::getTimeZoneConversion(\app\common\Utils::getQueryDateTime($datetame), $tz);
    }
}
