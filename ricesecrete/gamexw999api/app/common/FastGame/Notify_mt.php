<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_mt extends Notify
{
    private $thirds_name = 'mt';

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
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
        $datas = $datas['param'];
        $verify = Utils::uToken(null,$datas['Token']);
        if($verify){
            $Method = $datas['cash'];
            $this->PushLog($Method,'notify_data',$datas);
            return self::$Method($datas,$trdobj,$cfg);
        }else{
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog('notify','token验证失败',$datas);
            return $this->ReturnData('token Validation failed',['data'=>'']);
        }
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(string $msg = '', array $data = []): array
    {
        $arr['err'] = $msg;
        $arr['data'] = $data;
        $arr = array_merge($arr, $data);
        unset($arr['balance']);
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //查询用户余额
    function get($data, $trdobj, $cfg)
    {
        $user = UserModel::where(Meta::UFIELD['uname'], $data['Opid'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
        if(empty($user)){
            return $this->ReturnData('User does not exist');
        }
        $balance = $this->getBalance($user[Meta::UFIELD['uid']]);
        $arr['balance'] = floatval($balance);
        return $this->ReturnData('', $arr);
    }


    //下注并结算
    function transfer($param, $trdobj, $cfg){
        $action = 'transfer';
        $redis_key = $this->thirds_name."_".$action."_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['GameType'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$param);
            return $this->ReturnData('参数无效');
        }
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $param['Opid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = strval($param['OrderID']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount = Third::transExchangerate($param['Bet'],0);
                        $winloseAmount = Third::transExchangerate($param['Win'],0);
                        if ($winloseAmount > 0) {
                            $gb_winlose = 3;
                        } else{
                            $gb_winlose = 2;
                        }
                        $data_bet = [
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_gid'=>$gid,
                            'gb_uid'=>$uid,
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_sn'=>$order_sn,
                            'gb_winlose'=>$gb_winlose,
                            'gb_bettime'=>$ts,
                            'gb_overtime'=> $ts,
                            'gb_winprice'=>$winloseAmount,
                            'gb_betinfo'=>json_encode($param)
                        ];
                        if ($amount < $ubalance) {
                            $msgs = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                            $betcjprice = User::accountChange($uid, $amount, '-', 1, $msgs, true, $cfg['trdcfg']['tc_id'], $gid);
                            $data_bet['gb_cjprice'] = $betcjprice;
                            $bid = (new GameBets())->insertGetId($data_bet);
                            if ($gb_winlose == 3) {
                                $msgs = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                User::accountChange($uid, $winloseAmount, '+', 2, $msgs, true, $cfg['trdcfg']['tc_id'], $gid, $bid, $data_bet['gb_price'], $data_bet['gb_cjprice']);
                            }
                            $msg = '';
                            $res['balance'] = $this->getBalance($uid);
                            Db::commit();
                        } else {
                            $msg = 'Not enough balance';
                            $this->PushLog($action,'用户:'.$uid."余额不足",$param);
                            Db::rollback();
                        }
                    }else{
                        $msg = 'Order already exists';
                        $this->PushLog($action,'订单已存在',$param);
                        Db::rollback();
                    }
                }else{
                    $msg = 'Already accepted';
                    $this->PushLog($action,'订单重复请求',$param);
                    Db::rollback();
                }
            } else {
                $msg = 'Player does not exist';
                $this->PushLog($action,'错误:用户信息不存在',$param);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $msg = 'Other error';
            $this->PushLog($action,'过程错误',$ex->getMessage());
            Db::rollback();
        }
        return $this->ReturnData($msg,$res);
    }




    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return floatval($balance);
    }

}