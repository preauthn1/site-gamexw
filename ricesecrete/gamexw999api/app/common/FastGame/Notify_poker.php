<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_poker extends Notify
{
    private $thirds_name = 'poker';
    private $api = '/channelHandle';

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
        $datas = $datas['param'];
        $data = $this->desDecode($cfg['trdcfg']['tc_api_prikey'],$datas['param']);
        if($data){
            $Method = 'method_'.$data['s'];
            $datas['param'] = $data;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
            $this->PushLog('notify',$Method,$datas);
            return self::$Method($datas,$trdobj,$cfg);
        }else{
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog('notify','数据错误',$datas);
            return $this->ReturnData($data['s'],['code'=>6]);
        }
    }

    //解密数据
    function desDecode($key, $str)
    {
        $decrypt_str = openssl_decrypt(base64_decode($str), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        parse_str(trim($decrypt_str),$data);
        return $data;
    }

    /**
     * @return array
     */
    private function ReturnData(int $s = 0, array $data = []): array
    {
        $arr['m'] = $this->api;
        $arr['s'] = $s;
        $arr['d'] = $data;
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //下注
    function method_1002($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'method_1002';
        $redis_key = "poker_method_1002_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['kindId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$param);
            return $this->ReturnData($data['s'],['code'=>5,'account'=>'','money'=>0]);
        }
        $ts=time();
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['account'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = $this->getBalance($uid);
                $order_sn = strval($data['gameNo']);
                $order_sn2 = strval($data['orderId']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn .'_'.$order_sn2);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn.'_'.$order_sn2, 1, 10);
                    $amount = Third::transExchangerate($data['money'],0);  //金额转换
                    $data_bet = [
                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                        'gb_gid'=>$gid,
                        'gb_uid'=>$uid,
                        'gb_intime'=>$ts,
                        'gb_price'=>$amount,
                        'gb_sn'=>$order_sn,
                        'gb_winlose'=>1,
                        'gb_bettime'=>$ts,
                        'gb_overtime'=>0,
                        'gb_winprice'=>0,
                        'gb_cusfield'=>$order_sn2,
                        'gb_betinfo'=>json_encode($param)
                    ];
                    if ($amount < $ubalance) {
                        $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn])->find();
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                        $data_bet['gb_cjprice'] = $betcjprice;
//                        if ($rlt){
                            if(!$bet) {
                                (new GameBets())->insertGetId($data_bet);
                            }else {
                                $bet->gb_price = $bet->gb_price + $amount;
                                $bet->save();
//                                (new GameBets())->where(['gb_id'=>$bet->gb_id])->update(['gb_price' => Db::raw('gb_price+' . $amount)]);
                            }
                            $res['code'] = 0;
                            $res['account'] = $data['account'];
                            $res['money'] = $this->getBalance($uid);
                            Db::commit();
                            return $this->ReturnData($data['s'],$res);
//                        } else {
//                            $res['code'] = 5;
//                            $this->PushLog($action,'用户:'.$uid."扣款失败",$data);
//                            Db::rollback();
//                        }
                    } else {
                        $res['code'] = 1;
                        $this->PushLog($action,'用户:'.$uid."余额不足",$data);
                        Db::rollback();
                    }
                }else{
                    $res['code'] = 9;
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $res['code'] = 2;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['code'] = 5;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['account'] = isset($uid) ? $data['account'] : '';
        $res['money'] = $this->getBalance($uid ??0);
        return $this->ReturnData($data['s'],$res);
    }

    //结算
    function method_1003($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'method_1003';
        $redis_key = "poker_method_1003_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['kindId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$param);
            return $this->ReturnData($data['s'],['code'=>5,'account'=>'','money'=>0]);
        }
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['account'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['gameNo'];
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 10);
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn,'gb_winlose'=>1])->field('gb_id,gb_price')->find();
                    if($bet) {
                        $price = 0;
                        $amount = Third::transExchangerate($data['money'], 0,2);
                         if ($amount > 0) {
                            $gb_winlose = 3;
                            $price = $amount;
                        } else {
                            $gb_winlose = 2;
                        }
                        if ($price > 0) {
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                            User::accountChange($uid, $price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                        }
                        $bet->gb_overtime = time();
                        $bet->gb_winprice = $price;
                        $bet->gb_winlose = $gb_winlose;
                        $bet->gb_betinfo = json_encode($data);
                        $bet->save();
                        $res['code'] = 0;
                        $res['account'] = $data['account'];
                        $res['money'] = $this->getBalance($uid);
                        Db::commit();
                        return $this->ReturnData($data['s'],$res);
                    }else{
                        $res['code'] = 9;
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $res['code'] = 9;
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            }else{
                $res['code'] = 2;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $res['code'] = 5;
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['account'] = isset($uid) ? $data['account'] : '';
        $res['money'] = $this->getBalance($uid ??0);
        return $this->ReturnData($data['s'],$res);
    }

    //查询订单
    function method_1004($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $bet = GameBets::where(['gb_cusfield'=>$data['orderId']])->value('gb_id');
        if(empty($bet)){
            return $this->ReturnData($data['s'], ['code'=>0,'status'=>4]);
        }
        return $this->ReturnData($data['s'], ['code'=>0,'status'=>1]);
    }

    //查询余额
    function method_1001($data, $trdobj, $cfg)
    {
        $data = $data['param'];
        $user = UserModel::where(Meta::UFIELD['uname'], $data['account'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
        if(empty($user)){
            return $this->ReturnData($data['s'], ['code'=>2,'account'=>0,'money'=>0]);
        }
        $balance = $this->getBalance($user[Meta::UFIELD['uid']]);
        return $this->ReturnData($data['s'], ['code'=>0,'account'=>$data['account'],'money'=>floatval($balance)]);
    }

    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return floatval($balance);
    }

    //取消下注
    function method_1005($param, $trdobj, $cfg){
        $data = $param['param'];
        $action = 'method_1002';
        $redis_key = "poker_method_1002_";
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $data['kindId'], 'tag', 'g_id');
        if(!$gid){
            $this->PushLog($action,'游戏不存在',$param);
            return $this->ReturnData($data['s'],['code'=>5,'account'=>'','money'=>0]);
        }
        $ts=time();
        $res['code'] = 0;
        Db::startTrans();
        try {
            $this->PushLog($action,'data',$data);
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['account'])->lock(true)->field(Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = strval($data['gameNo']);
                $aflag = Redis::get($redis_key . $gid . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . $gid . "_" . $order_sn, 1, 60);//60秒内不接受重得action
                    $bet = GameBets::where(['gb_gid'=>$gid,'gb_sn'=>$order_sn,'gb_winlose'=>1])->find();
                    if($bet){
                        $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], $gid, '取消');
                        $rlt = User::accountChange($uid, $bet->gb_price, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                        if ($rlt){
                            $bet->gb_overtime = $ts;
                            $bet->gb_winlose = 0;
                            $bet->gb_betinfo = json_encode($data);
                            $bet->save();
                            $res['status'] = 1;
                            Db::commit();
                            return $this->ReturnData($data['s'],$res);
//                        } else {
//                            $this->PushLog($action,'用户:'.$uid."注单取消失败",$data);
//                            Db::rollback();
//                        }
                    }else{
                        $this->PushLog($action,'订单不存在或状态异常',$data);
                        Db::rollback();
                    }
                }else{
                    $this->PushLog($action,'订单重复请求',$data);
                    Db::rollback();
                }
            } else {
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (\Exception $ex) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['status'] = 2;
        return $this->ReturnData($data['s'],$res);
    }


}
