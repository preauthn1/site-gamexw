<?php

namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\common\model\UserModel;
use app\model\{GameBets, GameBetsHot, Games, ThirdConfigs, UserGames};
use think\facade\Db;

class Notify_kl extends Notify
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
        $this->tcid = $cfg['trdcfg']['tc_id'];
        // 判断有没有 sign 参数
        if (!isset($param['Sign'])){
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
            case 'get_point': // 3.1.1 获取钱包余额
                return $this->getBalance($param, $trdobj, $cfg);

            case 'change_point': // 3.3结算
                return $this->settlement($param, $trdobj, $cfg);
        }
    }

    //查询余额
    private function getBalance($param, $trdobj, $cfg){

        $param = $this->getParam(json_decode($param['Data'],true), $cfg);
        $username = $param['acc'];
        $uid = UserModel::where(Meta::UFIELD['uname'], '=', $username)->value(Meta::UFIELD['uid']);
        $userBalance = $this->getUserBalance($uid);

        $data = array (
            'balance' => $userBalance
        );
        return $this->ReturnData(0, 'success',json_encode($data));
    }


    /**
     * 结算
     * 检查方式：余额是否大于投注(bet 参数)
     * @param $params
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function settlement($param, $trdobj = null, $cfg = null): array
    {
        $param = $this->getParam(json_decode($param['Data'],true), $cfg);
        $this->pushLog($param, 'set balance', $trdobj);

        try {
            $type = $param['type'];
            if($type == 3){

                $amount = Third::transExchangerate($param['balance'] / 1000, 0, 2);
                $award = 0;
            }else{
                $amount = 0;
                $award = Third::transExchangerate($param['balance'] / 1000, 0, 2);
            }
            $gameId = $param['game_id'];
            $roundId = $param['order_id'];
            $buyRoundId = $param['buy_order_id']??$roundId;
            $username = $param['acc'];

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'tag', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->ReturnData(101, 'NO_User');
            }
            if( $type == 3 && $amount > $userBalance ){
                return $this->ReturnData(1, 'balance error');
            }

            $ts=time();

            //判断是否存在原注单
            $bet = null;
            if( $buyRoundId ){
                $bet = GameBets::where('gb_sn', '=', $buyRoundId)->find();
            }

            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$roundId,
                'gb_bettime'=>$ts,
                'gb_cusfield'=>$this->getBetType($gameId),
                'gb_overtime'=>$ts,
                'gb_winprice'=> $award,
                'gb_betinfo'=>json_encode($param)
            ];
            if($type == 3){
                $data['gb_winlose'] = 1;
            }else{
                $data['gb_winlose'] = $award>0?3:2;
            }
        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(1, 'fail1');
        }
        Db::startTrans();
        try {
            if( $bet ){
                //更新注单
                $bet->gb_overtime = time();
                $bet->gb_winprice = $award;
                $bet->gb_winlose = $award > 0 ? 3 : 2;
                $bet->gb_trans_no = $roundId;
                $bet->gb_betinfo = json_encode($data);
                $rlt = $bet->save();
                $gbid = $bet->gb_id;
                $data = $bet->toArray();
            }else{
                //写入注单
                $rlt=(new GameBets())->insertGetId($data);
                $gbid = $rlt;
                //写入下注信息
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                (new GameBets())->where('gb_id', '=', $rlt)->update([
                    'gb_cjprice' => $betcjprice
                ]);
                $data['gb_cjprice'] = $betcjprice;
            }
            if( !$rlt ){
                Db::rollback();
                return $this->ReturnData(1, 'fail2');
            }
//            if( !$rlt ){
//                $returnData['error'] = 3033;
//                Db::rollback();
//                return $returnData;
//            }
            if( $award ){
                //结算
                $msg = UserModel::betRemarks($buyRoundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $gbid, $data['gb_price'], $data['gb_cjprice']);
//                if( !$rlt ){
//                    $returnData['error'] = 3033;
//                    Db::rollback();
//                    return $returnData;
//                }
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->ReturnData(0, 'success',json_encode(['balance' => $balance]));
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(1, 'fail3');
        }
    }


    //处理params
    private function getParam($param, $cfg = null){
        //设置平台id
        if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param??$param;
    }

    /**
     * @param  int  $state
     * @param  string  $msg
     * @param  array  $data
     * @return array
     */
    private function ReturnData(int $state = 0, string $msg = 'OK', string $data = ''): array
    {
        $arr['code'] = $state;
        $arr['msg'] = $msg;
        $arr['data'] = $data;
        return $arr;
    }

    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1,2) * 1000;
        return $balance;
    }

    /**
     * 根据id获取注单类型
     * @param $ThirdGameId
     * @return string
     */
    public function getBetType($ThirdGameId){
        //三方规定
        //1~14      kerala
        //15        pick3
        //16~18     matka
         if( $ThirdGameId >= 1 && $ThirdGameId <= 14 ){
             return Third_kl::KERALA;
         }elseif( $ThirdGameId == 15 ) {
             return Third_kl::PICK3;
         }elseif( $ThirdGameId >= 16 && $ThirdGameId <= 18 ){
             return Third_kl::MATKA;
         }else {
             return '';
         }
    }

}
