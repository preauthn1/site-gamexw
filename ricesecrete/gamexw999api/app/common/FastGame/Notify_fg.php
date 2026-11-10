<?php

namespace app\common\FastGame;

use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\common\model\UserModel;
use app\model\{GameBets, GameBetsHot, Games, ThirdConfigs, UserGames};
use think\facade\Db;
use app\common\lib\Util;
use think\facade\Log;

class Notify_fg extends Notify
{
    private $tcid;

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @throws \Exception
     * @author twt326
     * @date 2022-10-04 18:33
     */
    public function notify($datas, $trdobj,$cfg)
    {
        $param = $datas['param'];
        Log::info($param);
        $this->tcid = $cfg['trdcfg']['tc_id'];
        // 判断有没有 sign 参数
        if (!isset($param['sign'])){
            $this->PushLog($param['action_method'], '缺少参数 sign', $datas, 'error');
            return $this->ReturnData(9999, '缺少参数 sign');
        }
        // 校验签名
        $sign_flag = $trdobj->CheckSign($cfg, $datas);
        if (!$sign_flag) {
            $this->PushLog($param['action_method'], '签名错误', $datas, 'error');
            return $this->ReturnData(108, '签名错误');
        }
        //获取回调对应方法
        $s = $param['s'];
        $sArr = array_values(array_filter(explode('/', $s)));
        $action = $sArr[count($sArr) - 1];
        unset($param['s']);unset($param['action_method']);unset($param['third']);

        //TODO  bet和winLoss不是单笔订单结算，而是余额转入/转出三方，注单需要根据转账钱包模式处理
        switch($action) {
            case 'playerBalance': // 3.1.1 获取钱包余额
                return $this->getBalance($param, $trdobj, $cfg);

            case 'bet': // 3.2 转入
                return $this->bet($param, $trdobj, $cfg);

            case 'winLoss': // 3.3结算
                return $this->settlement($param, $trdobj, $cfg);
        }
    }

    //查询余额
    private function getBalance($param, $trdobj, $cfg){
        $param = $this->getParam($param, $cfg);
        $username = $param['username'];
        $uinfo = UserModel::where(Meta::UFIELD['uname'], $username)->find();
        $data = array (
            'username' => $username,
            'balance' => $this->getUserBalance($uinfo[Meta::UFIELD['uid']]),
            'walletTime' => date("Y-m-d\TH:i:sO", time()),
        );
        return $this->ReturnData(0, 'OK',$data);
    }

    // 转入三方
    private function bet($params, $trdobj = null, $cfg = null): array
    {
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'bet', $trdobj);

        try {
            $username = $param['username'];
            $transId = $param['betId'];
            $transAmount = Third::transExchangerate($param['betAmount'] / 100, 0, 2);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $username)->find();
            if( !$uinfo ) {
                return $this->ReturnData(105, 'user not exist');
            }
            $userBalance = $uinfo['balance'];
            if( $transAmount > $userBalance ){
                return $this->ReturnData(100, '余额不足');
            }
            // TODO 三方没有游戏相关参数 获取最新的游戏id
            $gid = UserGames::where('ug_tcid', $cfg['trdcfg']['tc_id'])
                ->where('ug_uid', $uinfo[Meta::UFIELD['uid']])
                ->order('ug_lastupdate', 'desc')
                ->value('ug_gid')?:0;
            if (!$gid) {
                return $this->ReturnData(105,'用户不存在');
            }
            $apiRedis = new ApiRedis();
            $key = 'fg_single_wallet_bet_key_'.$transId.'_'.$username;
            //判断是否重复请求
            $_condition = [];
            $_condition[] = ['gs_tcid', '=', $cfg['trdcfg']['tc_id']];
            $_condition[] = ['gs_uid', '=', $uinfo[Meta::UFIELD['uid']]];
            $_condition[] = ['gs_sn', '=', $transId];
            $_condition[] = ['gs_type', '=', 1];
            if($apiRedis->has($key) || Db::name('game_third_special')->where($_condition)->value('gs_id') ){
                $data = array (
                    'username' => $username,
                    'balance' => $this->getUserBalance($uinfo[Meta::UFIELD['uid']]),
                    'walletTime' => date("Y-m-d\TH:i:sO", time()),
                );
                return $this->ReturnData(0, 'OK', $data);
            }


        }catch (\Exception $e){
            $this->pushLog($param, 'bet error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(2, 'bet error');
        }
        Db::startTrans();
        try {


            //写入特殊收支表
            $ts=time();
            $data_bet = [
                'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                'gs_gid'=> $gid,
                'gs_uid'=> $uinfo[Meta::UFIELD['uid']],
                'gs_price'=> $transAmount,
                'gs_sn'=>$transId,
                'gs_type'=>1,
                'gs_type_name'=> 'transfer_out',
                'gs_time'=>$ts,
                'gs_info'=>json_encode($params),
                'gs_create_time'=>$ts,
            ];

            //写入下注信息
            $betcjprice = 0;
            if( $transAmount > 0 ){
                $msg = UserModel::betRemarks($transId, $cfg['trdcfg']['tc_name'], $gid, "充值到三方平台[{$cfg['trdcfg']['tc_id']}]");
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $transAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);            //直接扣减用户余额，下面settlement结算会加上
            }
            Db::name('game_third_special')->insert($data_bet);
            Db::commit();
            $data = array (
                'username' => $username,
                'balance' => $this->getUserBalance($uinfo[Meta::UFIELD['uid']]),
                'walletTime' => date("Y-m-d\TH:i:sO", time()),
            );
            //设置缓存，设置数组是记录转入金额和彩金转入金额，用于双钱包模式和判断是否重复请求
            $apiRedis->set($key, json_encode([
                'bet_price' => $transAmount,
                'bet_cjprice' => $betcjprice,
            ]), 86400 * 3);
            return $this->ReturnData(0, 'OK', $data);
        }catch (\Exception $e){
            $this->pushLog($param, 'settlement error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(2, 'settlement error', $param);
        }
    }

    /**
     * 结算
     * 检查方式：余额是否大于投注(bet 参数)
     * @param $params
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function settlement($params, $trdobj = null, $cfg = null): array
    {
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'settlement', $trdobj);

        try {
            $winLossList = json_decode($param['winLossList'], true);# TODO 该参数三方只会结算一笔注单
            $transId = $winLossList[0]['betId'];
            // 获取转入金额
            $transAmount =  Third::transExchangerate($winLossList[0]['prize'] / 100, 0, 2);
            $username = $winLossList[0]['username'];
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $username)->find();
            if( !$uinfo ){
                $this->pushLog($param, 'user not exist', $trdobj, 'error');
                return $this->ReturnData(105,'用户不存在');
            }
            // TODO 三方没有游戏相关参数 获取最新的游戏id
            $gid = UserGames::where('ug_tcid', $cfg['trdcfg']['tc_id'])
                ->where('ug_uid', $uinfo[Meta::UFIELD['uid']])
                ->order('ug_lastupdate', 'desc')
                ->value('ug_gid')?:0;
            if (!$gid) {
                $this->pushLog($param, 'winLoss error：not game id', $trdobj, 'error');
                return $this->ReturnData(105,'用户不存在');
            }
            //获取缓存中的下注金额和下注彩金金额
            $betPrice = 0;
            $betCjPrice = 0;
            $apiRedis = new ApiRedis();
            $key = 'fg_single_wallet_bet_key_'.$transId.'_'.$username;
            if( $apiRedis->has($key) ){
                $betInfo = json_decode($apiRedis->get($key), true);
                $betPrice = $betInfo['bet_price']??0;
                $betCjPrice = $betInfo['bet_cjprice']??0;
            }


        }catch (\Exception $e){
            $this->pushLog($param, 'settlement error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(2, 'settlement error', $param);
        }
        Db::startTrans();
        try {
            //写入特殊收支表
            $ts=time();
            $data_bet = [
                'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                'gs_gid'=> $gid,
                'gs_uid'=> $uinfo[Meta::UFIELD['uid']],
                'gs_price'=> $transAmount,
                'gs_sn'=>$transId.'_'.$username,
                'gs_type'=>1,
                'gs_type_name'=> 'transfer_out',
                'gs_time'=>$ts,
                'gs_info'=>json_encode($params),
                'gs_create_time'=>$ts,
            ];
            $_condition = [];
            $_condition[] = ['gs_tcid', '=', $cfg['trdcfg']['tc_id']];
            $_condition[] = ['gs_uid', '=', $uinfo[Meta::UFIELD['uid']]];
            $_condition[] = ['gs_sn', '=', $data_bet['gs_sn']];
            $_condition[] = ['gs_type', '=', 1];
            if($transAmount > 0 && !Db::name('game_third_special')->where($_condition)->value('gs_id') ){
                //直接转入金额,已经结算的不再处理
                $msg = UserModel::betRemarks($transId, $cfg['trdcfg']['tc_name'], $gid, "三方平台[{$cfg['trdcfg']['tc_id']}]提现");
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $transAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, 0, $betPrice, $betCjPrice);
            }
            Db::name('game_third_special')->insert($data_bet);
            Db::commit();
            //清除缓存
            $apiRedis->delete($key);
            $data = array (
                "betId" =>  $transId,
                "uuid" => $transId, //结算流水号
                'balance' => $this->getUserBalance($uinfo[Meta::UFIELD['uid']]),
                "msg" => "该注单结算成功", //结算信息
                'state' => 0,
                'walletTime' => date("Y-m-d\TH:i:sO", time()),
            );
            return $this->ReturnData(0, 'OK', $data);
        }catch (\Exception $e){
            $this->pushLog($param, 'settlement error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(2, 'settlement error');
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

    /**
     * @param  int  $state
     * @param  string  $msg
     * @param  array  $data
     * @return array
     */
    private function ReturnData(int $state = 0, string $msg = 'OK', array $data = []): array
    {
        $arr['state'] = $state;
        $arr['msg'] = $msg;
        $arr['data'] = $data;
        return $arr;
    }

    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid) * 100;
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
