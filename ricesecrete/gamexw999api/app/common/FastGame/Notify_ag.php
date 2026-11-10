<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_ag extends Notify
{
    private $thirds_name = '';

    private $game_code = 'ag';

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
        return false;
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(string $code = '00', string $msg = '', array $data = []): array
    {
        $arr['rs_message'] = $msg;
        $arr['rs_code'] = $code;
        $arr = array_merge($arr, $data);
        $arr = array_filter($arr); //清除空值
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action . $msg, $data]);
    }

    public function cockfight($datas, $trdobj, $cfg)
    {
        $param = $datas['param'];
        $this->PushLog($param['s'], '主过程错误', $datas);
        try {
            $action_type = ['bet', 'cancel', 'settle', 'resettle'];
            $s_arr = explode( 'cockfight/', $param['s']);
            $a_arr = explode('/', $s_arr[1]);
            $action = $a_arr[0];
            $param['action'] = $action;
            $signflag = $trdobj->CheckSign($cfg, $datas);
            if ($signflag) {
                //允许请求的方法
                if($action == 'balance'){
                    $this->thirds_name = strtolower($cfg['trdcfg']['tc_code']);
                    $info = [];
                    $u_id = $param['balance'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if($utuser->ut_uid){
                        $info['uid'] = $utuser->ut_uid;
                        $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
                        $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                        $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                        $res = $this->$action($param, $trdobj, $cfg, $info);
                    }else{
                        $this->PushLog($action, '用户不存在', $datas);
                        $res['code'] = '99';
                    }

                }elseif(in_array($action, $action_type)){
                    $res = $this->$action($param, $trdobj, $cfg);
                }else{
                    $this->PushLog($action, '参数不合法', $datas);
                    $res['code'] = '99';
                }
            } else {
                $this->PushLog($action, '签名错误', $datas);
                $res['code'] = '99';
            }
        } catch (\Throwable $e) {
            $this->PushLog($param['s'], '主过程错误', $e->getMessage());
            $res['code'] = '99';
        }
        return $res;
    }

    private function bet($param, $trdobj, $cfg){
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $third = $param['third'];
        $ts=time();
        $res = [];
        $data_param = $this->analyzeData($cfg);
        Db::startTrans();
        try {
            if(isset($data_param['data'])){
                $data = $data_param['data'];
                Utils::L($third, ['notify:bet','Data',$data]);
                $is_user = true;
                $uids = [];
                foreach($data as $key => $val){
                    $u_id = $val['player'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if(!$utuser->ut_uid && $is_user){
                        $is_user = false;
                    } else {
                        $uids[$u_id] = $utuser->ut_uid;
                    }
                }
                $return_data = [];
                if($is_user){
                    foreach($data as $key => $val){
                        $uid = $uids[$val['player']];
                        $amount = abs($val['amount']);
                        unset($val['amount']);
                        $num = $val['num'];
                        $aflag = Redis::get("uni_betactionid_" . $gid . "_" . $num);
                        if (!$aflag) {
                            Redis::setex("uni_betactionid_" . $gid . "_" . $num, 1, 60);//60秒内不接受重得action
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $num)->find();
                            if(!$bet){
                                $msg = UserModel::betRemarks($num, $cfg['trdcfg']['tc_name'], $gid);
                                $betcjprice = User::accountChange($uid, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                $userBalance = $this->getBalance($uid);
                                if ($amount < $userBalance) {
                                    $data_bet = [
                                        'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                        'gb_gid'=>$gid,
                                        'gb_uid'=>$uid,
                                        'gb_cjprice'=>$betcjprice,
                                        'gb_intime'=>$ts,
                                        'gb_price'=>$amount,
                                        'gb_sn'=>$num,
                                        'gb_winlose'=>1,
                                        'gb_bettime'=>$ts,
                                        'gb_overtime'=>$ts,
                                        'gb_winprice'=>0,
                                        'gb_betinfo'=>json_encode($val)
                                    ];
                                    //写入注单
                                    $rlt=(new GameBets())->insertGetId($data_bet);
                                    if (!$rlt){
                                        $res['code'] = '99';
                                        Utils::L($third, ['notify:bet','错误:插入订单失败',$val]);
                                    } else {
                                        $res['code'] = '00';
                                        $val['balance'] = $this->getBalance($uid, $cfg);
                                        $return_data[] = $val;
                                    }
                                } else {
                                    $res['code'] = '31';
                                    Utils::L($third, ['notify:bet','用户:'.$uid."余额不足",$val]);
                                }
                            } else {
                                $res['code'] = '00';
                                $val['balance'] = $this->getBalance($uid, $cfg);
                                $return_data[] = $val;
                            }
                        } else {
                            $res['code'] = '00';
                            $val['balance'] = $this->getBalance($uid, $cfg);
                            $return_data[] = $val;
                        }
                    }
                    
                    $res['data'] = $return_data;
                    Db::commit();
                } else {
                    Utils::L($third, ['notify:bet','错误:用户信息不存在',$data]);
                    $res['code'] = '99';
                    Db::rollback();
                }
            } else {
                Db::rollback();
                $this->PushLog("bet", 'data 数据错误', $data_param);
                $res['code'] = '99';
            }
        } catch (\Exception $ex) {
            Utils::L($third,['notify:bet','过程错误',$ex->getMessage()]);
            $res['code'] = '99';
            Db::rollback();
        }
        
        return $res;
    }
    
    // 结算
    private function settle($param, $trdobj, $cfg){
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $third = $param['third'];
        $ts=time();
        $res = [];
        $data_param = $this->analyzeData($cfg);
        Db::startTrans();
        try {
            if(isset($data_param['data'])){
                $data = $data_param['data'];
                Utils::L($third, ['notify:settle','Data',$data]);
                $is_user = true;
                $uids = [];
                foreach($data as $key => $val){
                    $u_id = $val['player'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if(!$utuser->ut_uid && $is_user){
                        $is_user = false;
                    } else {
                        $uids[$u_id] = $utuser->ut_uid;
                    }
                }
                $return_data = [];
                if($is_user){
                    foreach($data as $key => $val){
                        $uid = $uids[$val['player']];
                        $amount = $val['amount'];
                        unset($val['amount']);
                        $num = $val['num'];
                        $aflag = Redis::get("uni_settleactionid_" . $gid . "_" . $num);
                        if (!$aflag) {
                            Redis::setex("uni_settleactionid_" . $gid . "_" . $num, 1, 60); //60秒内不接受重得action
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $num)->where('gb_winlose', 1)->find();
                            if($bet){
                                if($amount > 0){ // 结算赢
                                    $msg = UserModel::betRemarks($num, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                    if ($rlt){
                                        $bet->gb_overtime = $ts;
                                        $bet->gb_winlose = 3;
                                        $bet->gb_winprice = $amount;
                                        $bet->gb_betinfo = json_encode($val);
                                        $bet->save();

                                        $res['code'] = '00';
                                        $val['balance'] = $this->getBalance($uid, $cfg);
                                        $return_data[] = $val;

//                                    }else{
//                                        Db::rollback();
//                                    }
                                } else { // 结算输
                                    $bet->gb_overtime = $ts;
                                    $bet->gb_winlose = 2;
                                    $bet->gb_betinfo = json_encode($val);
                                    $bet->save();

                                    $res['code'] = '00';
                                    $val['balance'] = $this->getBalance($uid, $cfg);
                                    $return_data[] = $val;
                                }
                            } else {
                                $res['code'] = '00';
                                $val['balance'] = $this->getBalance($uid, $cfg);
                                $return_data[] = $val;
                            }
                        } else {
                            $res['code'] = '00';
                            $val['balance'] = $this->getBalance($uid, $cfg);
                            $return_data[] = $val;
                        }
                    }
                    
                    $res['data'] = $return_data;
                    Db::commit();
                } else {
                    Utils::L($third, ['notify:settle','错误:用户信息不存在',$data]);
                    $res['code'] = '99';
                    Db::rollback();
                }
            } else {
                Db::rollback();
                Utils::L($third,['notify:settle','data 数据错误', $data_param]);
                $res['code'] = '99';
            }
        } catch (Exception $ex) {
            Utils::L($third,['notify:settle','过程错误',$ex->getMessage()]);
            $res['code'] = '99';
            Db::rollback();
        }
        
        return $res;
    }
    
    // 重新结算
    private function resettle($param, $trdobj, $cfg){
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $third = $param['third'];
        $ts=time();
        $res = [];
        $data_param = $this->analyzeData($cfg);
        Db::startTrans();
        try {
            if(isset($data_param['data'])){
                $data = $data_param['data'];
                Utils::L($third, ['notify:resettle','Data',$data]);
                $is_user = true;
                $uids = [];
                foreach($data as $key => $val){
                    $u_id = $val['player'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if(!$utuser->ut_uid && $is_user){
                        $is_user = false;
                    } else {
                        $uids[$u_id] = $utuser->ut_uid;
                    }
                }
                $return_data = [];
                if($is_user){
                    foreach($data as $key => $val){
                        $uid = $uids[$val['player']];
                        $amount = $val['amount'];
                        unset($val['amount']);
                        $num = $val['num'];
                        $aflag = Redis::get("uni_resettleactionid_" . $gid . "_" . $num);
                        if (!$aflag) {
                            Redis::setex("uni_resettleactionid_" . $gid . "_" . $num, 1, 60); //60秒内不接受重得action
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $num)->where([['gb_winlose', ">", 1]])->find();
                            if($bet){
                                $msg = UserModel::betRemarks($num, $cfg['trdcfg']['tc_name'], $gid, '重新结算');
                                if($bet->gb_winlose == 3){ // 首次结算为赢的时候，重新结算先扣除首次结算金额
                                    $money = $bet->gb_winprice;
                                    $betcjprice = User::accountChange($uid, $money, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    $bet->gb_cjprice = $betcjprice;
                                }
                                if($amount > 0){ // 结算赢
                                    $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                    if ($rlt){
                                        $bet->gb_overtime = $ts;
                                        $bet->gb_winlose = 3;
                                        $bet->gb_winprice = $amount;
                                        $bet->gb_betinfo = json_encode($val);
                                        $bet->save();

                                        $res['code'] = '00';
                                        $val['balance'] = $this->getBalance($uid, $cfg);
                                        $return_data[] = $val;

//                                    }else{
//                                        Db::rollback();
//                                    }
                                } else { // 结算输
                                    $bet->gb_overtime = $ts;
                                    $bet->gb_winlose = 2;
                                    $bet->gb_winprice = 0;
                                    $bet->gb_betinfo = json_encode($val);
                                    $bet->save();

                                    $res['code'] = '00';
                                    $val['balance'] = $this->getBalance($uid, $cfg);
                                    $return_data[] = $val;
                                }
                            } else {
                                $res['code'] = '00';
                                $val['balance'] = $this->getBalance($uid, $cfg);
                                $return_data[] = $val;
                            }
                        } else {
                            $res['code'] = '00';
                            $val['balance'] = $this->getBalance($uid, $cfg);
                            $return_data[] = $val;
                        }
                    }
                    
                    $res['data'] = $return_data;
                    Db::commit();
                } else {
                    Utils::L($third, ['notify:resettle','错误:用户信息不存在',$data]);
                    $res['code'] = '99';
                    Db::rollback();
                }
            } else {
                Db::rollback();
                Utils::L($third,['notify:resettle','data 数据错误', $data_param]);
                $res['code'] = '99';
            }
        } catch (\Exception $ex) {
            Utils::L($third,['notify:resettle','过程错误',$ex->getMessage()]);
            $res['code'] = '99';
            Db::rollback();
        }
        
        return $res;
    }
    
    // 结算
    private function cancel($param, $trdobj, $cfg){
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $third = $param['third'];
        $ts=time();
        $res = [];
        $data_param = $this->analyzeData($cfg);
        Db::startTrans();
        try {
            if(isset($data_param['data'])){
                $data = $data_param['data'];
                Utils::L($third, ['notify:settle','Data',$data]);
                $is_user = true;
                $uids = [];
                foreach($data as $key => $val){
                    $u_id = $val['player'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if(!$utuser->ut_uid && $is_user){
                        $is_user = false;
                    } else {
                        $uids[$u_id] = $utuser->ut_uid;
                    }
                }
                $return_data = [];
                $error_data = [];
                if($is_user){
                    foreach($data as $key => $val){
                        $uid = $uids[$val['player']];
                        $amount = $val['amount'];
                        unset($val['amount']);
                        $num = $val['num'];
                        $aflag = Redis::get("uni_cancelactionid_" . $gid . "_" . $num);
                        if (!$aflag) {
                            Redis::setex("uni_cancelactionid_" . $gid . "_" . $num, 1, 60); //60秒内不接受重得action
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $num)->where('gb_winlose', 1)->find();
                            if($bet){
                                if($amount > 0){ // 
                                    $msg = UserModel::betRemarks($num, $cfg['trdcfg']['tc_name'], $gid, '取消');
                                    $rlt = User::accountChange($uid, $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                    if ($rlt){
                                        $bet->gb_overtime = $ts;
                                        $bet->gb_winlose = 0;
                                        $bet->gb_betinfo = json_encode($val);
                                        $bet->save();

                                        $val['balance'] = $this->getBalance($uid, $cfg);
                                        $return_data[] = $val;
//                                    }else{
//                                        Db::rollback();
//                                    }
                                } else { // 无金额 返回错误信息
                                    $val['balance'] = $this->getBalance($uid, $cfg);
                                    $error_data[] = $val;
                                }
                            } else { // 无订单 返回错误信息
                                $val['balance'] = $this->getBalance($uid, $cfg);
                                $error_data[] = $val;
                            }
                        } else { // 60秒内重复取消同一订单 返回错误信息
                            $val['balance'] = $this->getBalance($uid, $cfg);
                            $error_data[] = $val;
                        }
                    }
                    
                    $res['data'] = $return_data;
                    if(!empty($error_data)){
                        $res['code'] = '05';
                        $res['error'] = $error_data;
                    } else {
                        $res['code'] = '00';
                    }
                    Db::commit();
                } else {
                    Utils::L($third, ['notify:settle','错误:用户信息不存在',$data]);
                    $res['code'] = '99';
                    Db::rollback();
                }
            } else {
                Db::rollback();
                Utils::L($third,['notify:settle','data 数据错误', $data_param]);
                $res['code'] = '99';
            }
        } catch (Exception $ex) {
            Utils::L($third,['notify:settle','过程错误',$ex->getMessage()]);
            $res['code'] = '99';
            Db::rollback();
        }
        
        return $res;
    }

    function analyzeData($cfg){
        $datas = file_get_contents('php://input', 'r');
        //$datas = "eyJhbGciOiJIUzUxMiJ9.eyJzbHVnIjoiMjMwMTA3MTYyMTUwMjk3Mzc5IiwiZGF0YSI6W3sibnVtIjoiQjE2NzMwNzk1NjM5MDI5OTAwNDUiLCJhbW91bnQiOiIxMTQwLjAiLCJwbGF5ZXIiOiI0NjE5MjY2OCJ9XX0.QBktFHRyc86F6A7IwKAYbmgS1ok60DtHvPR6lNLds2OeWsEq6Dvz56Ej0Qp-yJ7i8ui3R9dNE4inXJkh0_eaMQ";
        $key = $cfg['trdcfg']['tc_api_signkey'];
        $data_param = JWT::decode($datas, $key, ["HS512"]);
        $data_param = json_decode(json_encode($data_param), true);
        return $data_param;
    }
    
    function getBalance($uid, $cfg){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

}
