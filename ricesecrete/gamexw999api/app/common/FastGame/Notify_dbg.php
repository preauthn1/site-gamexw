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
use app\api\redis\ApiRedis;

class Notify_dbg extends Notify
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
    public function getBalance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $userId = $param['account'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            if(!$uid){
                return [
                    'code' => 102,
                    'balance' => 0
                ];
            }
            $userBalance = $this->getUserBalance($uid);
            return [
                'code' => 0,
                'balance' => $userBalance
            ];

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            return [
                'code' => 501,
                'balance' => 0
            ];
        }
    }

    //设置余额
    public function transfer($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'set balance', $trdobj);
        $returnData = [
            'code' => 0,
            'balance' => 0,
            'id'=> (int)$param['id']
        ];

        try {

            $userId = $param['account'];
            $gameId = $param['gameId'];
            $roundId = $param['id'];
            $amount = abs($param['amount']);
            $amount = Third::transExchangerate($amount, 0, 4);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            if( !$uinfo ){
                $returnData['code'] = 102;
                return $returnData;
            }

            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);

            if(Db::name('game_third_special')->where('gs_sn','=',$roundId)->value('gs_id') ){
                $returnData['balance'] = $userBalance;
                return $returnData;
            }

            if( $param['amount'] < 0 && $amount > $userBalance ){
                $returnData['code'] = 120;#用户余额不足
                return $returnData;
            }

            $apiRedis = new ApiRedis();
            $key = 'dbg_single_wallet_bet_key_'.$userId;

            //写入特殊收支表
            $ts=time();
            $data_bet = [
                'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                'gs_gid'=> $gid,
                'gs_uid'=> $uinfo[Meta::UFIELD['uid']],
                'gs_price'=> $amount,
                'gs_sn'=>$roundId,
                'gs_type'=>$param['amount'] < 0?2:1,
                'gs_type_name'=> $param['amount'] < 0?'transfer_out':'transfer_in',
                'gs_time'=>$ts,
                'gs_info'=>json_encode($params),
                'gs_create_time'=>$ts,
            ];

            Db::startTrans();

            $rlt = Db::name('game_third_special')->insert($data_bet);
            //三方上分
            if($param['amount'] < 0 ){

                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, "dbg充值到三方平台[{$cfg['trdcfg']['tc_id']}]");
                $betcjprice =User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                //设置缓存，设置数组是记录转入金额和彩金转入金额，用于双钱包模式和判断是否重复请求
                $apiRedis->set($key, json_encode([
                    'bet_price' => $amount,
                    'bet_cjprice' => $betcjprice,
                ]), 86400 * 3);
            }
            if( $param['amount'] >= 0 ){
                //下分
                //获取缓存中的下注金额和下注彩金金额
                $betPrice = 0;
                $betCjPrice = 0;
                if( $apiRedis->has($key) ){
                    $betInfo = json_decode($apiRedis->get($key), true);
                    $betPrice = $betInfo['bet_price']??0;
                    $betCjPrice = $betInfo['bet_cjprice']??0;
                }

                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, "dbg提现到三方平台[{$cfg['trdcfg']['tc_id']}]");
                User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $betPrice, $betCjPrice);
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            $returnData['code'] = 501;
            return $returnData;
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
        $balance = (int)Third::transExchangerate($balance,1,0);
        return $balance;
    }
}
