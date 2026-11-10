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

class Notify_pgfalse extends Notify
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
            $userId = $param['UseID'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            $userBalance = $this->getUserBalance($uid);
            return [
                'error' => null,
                'data' => [
                    'currency_code' => Util::i18n_currency_code(),
                    'balance_amount' => (int)(Third::transExchangerate($userBalance, 1, 2) * 1000),
                    'updated_time' => time() * 1000,
                ],
            ];

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
        }
    }

    //设置余额
    public function setBalance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'set balance', $trdobj);
        $returnData = [
            'error' => null,
            'data' => [],
        ];
        try {
            $userId = $param['UseID'];
            $gameId = $param['GameID'];
            $roundId = $param['Term'];
            $amount = round($param['Bet'] / 1000, 4);
            $amount = Third::transExchangerate($amount, 0, 2);
            $award = round($param['Award'] / 1000, 4);
            $award = Third::transExchangerate($award, 0, 2);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'code', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                $returnData['error'] = 3004;
                return $returnData;
            }
            if( $amount > $userBalance ){
                $returnData['error'] = 3202;#用户余额不足
                return $returnData;
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
            $returnData['error'] = 3033;
            return $returnData;
        }
        Db::startTrans();
        try {
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if( !$rlt ){
                $returnData['error'] = 3033;
                Db::rollback();
                return $returnData;
            }
            //写入下注信息
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            (new GameBets())->where('gb_id', '=', $rlt)->update([
                'gb_cjprice' => $betcjprice
            ]);
            $data['gb_cjprice'] = $betcjprice;
//            if( !$rlt ){
//                $returnData['error'] = 3033;
//                Db::rollback();
//                return $returnData;
//            }
            if( $award ){
                //结算
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $data['gb_price'], $data['gb_cjprice']);
//                if( !$rlt ){
//                    $returnData['error'] = 3033;
//                    Db::rollback();
//                    return $returnData;
//                }
            }
            //获取用户余额返回
            $balance = (int)($this->getUserBalance($uinfo[Meta::UFIELD['uid']]) * 1000);
            $returnData['data']['currency_code'] = Util::i18n_currency_code();
            $returnData['data']['balance_amount'] = $balance;
            $returnData['data']['updated_time'] = $ts * 1000;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $returnData['error'] = 3033;
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
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
