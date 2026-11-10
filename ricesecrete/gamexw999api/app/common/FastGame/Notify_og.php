<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use think\facade\Db;

class Notify_og extends Notify
{
    private $thirds_name = '';

    private $game_code = 'og_game';

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
    private function ReturnData(string $code = 'S-100', string $msg = '', array $data = []): array
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

    public function chess($datas, $trdobj, $cfg)
    {
        $param = $datas['param'];
        try {
            $action_type = ['authenticate', 'balance', 'bulk/debit', 'credit'];
            $s_arr = explode( 'transaction/', $param['s']);
            if($param['operator'] == 'security'){
                $s_arr = explode( 'security/', $param['s']);
            }else if($param['operator'] == 'player'){
                $s_arr = explode('player/', $param['s']);
            }
            $action = $s_arr[1];

            //允许请求的方法
            if(in_array($action, $action_type)){
                //登录验证
                ///api/v1/operator/security/authenticate
                //获取余额
                //GET - {{ Operator_Host }}/api/v1/operator/player/balance
                //剩下都是关于注单的操作
                //体育 /api/v1/operator/transaction/debit
                //下注 /api/v1/operator/transaction/bulk/debit
                //结算/api/v1/operator/transaction/credit
                if($action == 'bulk/debit'){
                    $action = 'bulk_debit';
                }
                $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
                $param['action'] = $action;
                $signflag = $trdobj->CheckSign($cfg, $param);
                if ($signflag) {
                    $info = [];
                    $u_id = $param['player_id'];
                    $utuser = UserThirds::where(['ut_trdid' => $u_id])->find();
                    if($utuser->ut_uid){
                        $info['uid'] = $utuser->ut_uid;
                        $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
                        $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                        $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                        $res = $this->$action($param, $trdobj, $cfg, $info);
                    }else{
                        $this->PushLog($action, '用户不存在', $datas);
                        $res['code'] = 'S-104';
                    }
                } else {
                    $this->PushLog($action, '签名错误', $datas);
                    $res['code'] = 'E-103';
                }
                $this->PushLog($action, '执行完成', $res);
            }else{
                $this->PushLog($action, '参数不合法', $datas);
                $res['code'] = 'E-104';
            }
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($param['s'], '主过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $res['code'] = 'E-104';
        }

        return $res;
    }

    //登录验证
    private function authenticate($data, $trdobj, $cfg, $info): array
    {
        $arr = [];
        try {
            $arr['player_status'] = 'activate';
            $arr['token'] = $data['token'];
            $msg = 'success';
            $code = 'S-100';
        } catch (\Throwable $e) {
            $msg = 'invalid parameter';
            $code = 'E-104';
            $this->PushLog($data['action'], $msg, $e->getMessage());
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //查询余额
    private function balance($data, $trdobj, $cfg, $info): array
    {

        $arr = [];
        try {
            $balance = $this->getUserBalance($info['uid']);
            $arr['player_id'] = $data['player_id'];
            $arr['player_status'] = 'activate';
            $arr['balance'] = $balance;
            $arr['timestamp'] = time() * 1000;
            $arr['currency'] = $info['currency'];
            $msg = 'success';
            $code = 'S-100';
        } catch (\Throwable $e) {
            $msg = 'fail';
            $code = 'E-000';
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }



    //下注/取消
    private function bulk_debit($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        Db::startTrans();
        $msg = 'fail';
        $arr = [];
        try {
            $code = 'E-000';
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            //transaction_type 操作类型  bet / rollback
            $tr_type = $data['transaction_type'];
            $key = "og_bulk_debit_{$info['gid']}_{$tr_type}_{$data['records'][0]['transaction_id']}";//直接取订单中的 第一条 因为只要失败一条 剩下的全部都要返回失败

            $aflag = Redis::get($key);
            if(!$aflag) {
                Redis::setex($key, 1, 60);//60秒内不接受重复得action
                $total_amount = abs($data['total_amount']);
                //从我方到三方的时候 汇率相乘 回来的时候相除
                $total_amount_me = Third::transExchangerate($total_amount,0, 2);
                $records = [];
                $suc_num = 0;
                /* @GameBets $GameBetsModel */
                $GameBetsModel = app()->make(GameBets::class);
                $userBalance = $this->getUserBalance($info['uid']);
                foreach($data['records'] as $k => $v){
                    $bet[$k] = [
                        'gb_gid' => $info['gid'],
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_uid' => $info['uid'],
                        'gb_intime' => $ts,
//                        'gb_price' => $amount,
//                        'gb_sn' => $data['businessId'],
                        'gb_winlose' => 1,
                        'gb_bettime' => $ts,
                        'gb_overtime' => '',
                        'gb_winprice' => 0,
                        'gb_betinfo' => json_encode($data)//原始订单为空的时候就是下注的时候生成的初始订单
                    ];
                    if($tr_type == 'debit'){
                        //下注时
                        $wh['gb_sn'] = $v['transaction_id'];//如果已经写入过这个注单 直接返回失败
                        $GameBets = $GameBetsModel->where($wh)->find();
                        if(isset($GameBets['gb_id'])){
                            $this->PushLog($data['action'], '订单已存在', $data);
                            Db::rollback();
                            $suc_num ++ ;
                        }else{
                            if ($total_amount_me <= $userBalance) {

                                $amount = abs($v['amount']);
                                //从我方到三方的时候 汇率相乘 回来的时候相除
                                $amount = Third::transExchangerate($amount,0, 2);
                                $bet[$k]['gb_price'] = $amount;
                                $bet[$k]['gb_sn'] = $v['transaction_id'];
//                                $mx = $cfg['trdcfg']['tc_name'] . "_[ " . $this->game_name($data['game_id']) . " ] _下注_".$bet[$k]['gb_sn'];
                                $msg = UserModel::betRemarks($bet[$k]['gb_sn'], $cfg['trdcfg']['tc_name'], $data['game_id']);
                                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                                $bet[$k]['gb_cjprice'] = $betcjprice;
                                    //写入注单
                                    $res = (new GameBets())->insertGetId($bet[$k]);
                                    if (!$res){
                                        $suc_num ++ ;
                                    }
                                    $records[$k] = [
                                        'amount' => $v['amount'],
                                        'transaction_id' => $v['transaction_id'],
                                    ];
                            }else{
                                $this->PushLog($data['action'], '用户余额不足', $data);
                                Db::rollback();
                                $suc_num ++ ;
                            }
                        }
                    }else if($tr_type == 'rollback'){
                        //下注回滚
                        $wh = $upd = [];
                        $wh['gb_winlose'] = 1; //如果不为1 就不管 因为为1 才确定已经扣款了 需要回滚
                        $wh['gb_sn'] = $v['transaction_id'];
                        $wh['gb_gid'] = $info['gid'];
                        $GameBets = $GameBetsModel->where($wh)->find();
                        if(isset($GameBets['gb_id'])){
//                            $mx = $cfg['trdcfg']['tc_name'] . "_[ " . $this->game_name($data['game_id']) . " ] _取消下注_".$v['transaction_id'];
                            $msg = UserModel::betRemarks($v['transaction_id'], $cfg['trdcfg']['tc_name'], $data['game_id']);
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $GameBets['gb_price'], '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                            $upd['gb_overtime'] = $ts;
                            $upd['gb_winlose'] = 0;
                            $upd['gb_betinfo'] = json_encode($data);
                            $res = $GameBets->save($upd);
                            if (!$res){
                                $suc_num ++ ;
                            }
                            $records[$k] = [
                                'amount' => $v['amount'],
                                'transaction_id' => $v['transaction_id'],
                            ];
                        }else{
                            $this->PushLog($data['action'], '订单不存在', $data);
                            Db::rollback();
                            $suc_num ++ ;
                        }
                    }
                }
                //失败数为0 才能正常返回成功
                if($suc_num == 0){
                    $balance = $this->getUserBalance($info['uid']);
                    $code = 'S-100';
                    $msg = 'success';
                    $arr['player_id'] = $data['player_id'];
                    $arr['total_amount'] = $total_amount_me;
                    $arr['updated_balance'] = $balance;
                    $arr['billing_at'] = $ts;
                    $arr['records'] = json_encode($records);
                    $this->PushLog($data['action'], '注单写入成功', $bet);
                    Db::commit();
                }else{
                    $this->PushLog($data['action'], '注单写入失败', $bet);
                    $code = 'S-110';
                    Db::rollback();
                }

            }else{
                $this->PushLog($data['action'], '订单重复请求', $data);
                $code = 'E-105';
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = 'E-000';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }



    //结算/派彩後取消/已派彩注單取消後重新結算
    private function credit($data, $trdobj, $cfg, $info): array
    {
        $ts = time();
        Db::startTrans();
        $msg = 'fail';
        $arr = [];
        try {
            $code = 'E-000';
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            //transaction_type 操作类型  bet / rollback
            $tr_type = $data['transaction_type'];
            $tr_id = $data['transaction_id'];
            $money = $data['amount']; //结算金额 大于0 就是胜
            $key = "og_bulk_debit_{$info['gid']}_{$tr_type}_{$tr_id}";
            $aflag = Redis::get($key);
            if(!$aflag) {
                Redis::setex($key, 1, 60);//60秒内不接受重复得action
                $wh = $upd = [];
                $wh[] = ['gb_gid', '=', $info['gid']];
                /* @GameBets $GameBetsModel */
                $GameBetsModel = app()->make(GameBets::class);
                $upd_res = false;
                if($tr_type == 'credit'){ //结算
                    $wh[] = ['gb_sn', '=', $tr_id];
                    $wh[] = ['gb_winlose', '=', 1];
                    $GameBets = $GameBetsModel->where($wh)->find();
                    if(isset($GameBets['gb_id'])){
                        $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
                        $upd['gb_overtime'] = $ts;
                        if($money == 0){ // 等于0的时候 必定为输
                            $upd['gb_winlose'] = 2;
                            $upd_res = $GameBets->save($upd);
                        }else{
                            $upd['gb_winlose'] = 3;
                            $amount = Third::transExchangerate($money, 0);
                            $upd['gb_winprice'] = $amount;
//                            $mx = $cfg['trdcfg']['tc_name'] . "_[ " . $this->game_name($data['game_id']) . " ] _结算_" . $tr_id;
                            $msg = UserModel::betRemarks($tr_id, $cfg['trdcfg']['tc_name'], $data['game_id'], '结算');
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                            $upd_res = $GameBets->save($upd);
                        }
                    }else{
                        $this->PushLog($data['action'], '订单不存在', $data);
                        Db::rollback();
                    }
                }

                if($tr_type == 'cancel'){ //取消
                    $wh[] = ['gb_sn', '=', $tr_id];
                    $wh[] = ['gb_winlose', '>', 1]; //取消的时候 必定是已结算状态 即为 2 3 4
                    $GameBets = $GameBetsModel->where($wh)->find();
                    if(isset($GameBets['gb_id'])) {
                        $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
                        $upd['gb_overtime'] = 0; //重置结算时间
                        $upd['gb_winlose'] = 1; //改为未结算
                        $upd['gb_winprice'] = 0; //结算金额改为0
                        $money = $GameBets['gb_winprice']; //操作金额为我方注单结算金额
                        if($GameBets['gb_winprice'] > 0){
                            //从赢到未结算  将赢的金额扣掉
//                            $mx = $cfg['trdcfg']['tc_name'] . "_[ " . $this->game_name($data['game_id']) . " ] _取消结算补扣_" . $data['transaction_id'];
                            $msg = UserModel::betRemarks($data['transaction_id'], $cfg['trdcfg']['tc_name'], $data['game_id'], '取消结算补扣');
                            User::accountChange($uinfo[Meta::UFIELD['uid']], $GameBets['gb_winprice'], '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid']);
                        }
                        //从输到未结算 不用额外处理 直接更新注单
                        $upd_res = $GameBets->save($upd);
                    }else{
                        $this->PushLog($data['action'], '订单不存在', $data);
                        Db::rollback();
                    }
                }

                if($tr_type == 'resettle'){ //重新结算 重新结算必定是从 结算 -> 取消结算 ->重新结算
                    $wh[] = ['gb_sn', '=', $tr_id];
                    $wh[] = ['gb_winlose', '=', 1];
                    $GameBets = $GameBetsModel->where($wh)->find();
                    if(isset($GameBets['gb_id'])){
                        $upd['gb_betinfo'] = json_encode($data); //重新更新原始注单
                        $upd['gb_overtime'] = $ts;
                        if($money == 0){ // 等于0的时候 必定为输
                            $upd['gb_winlose'] = 2;
                            $upd_res = $GameBets->save($upd);
                        }else{
                            $upd['gb_winlose'] = 3;
                            $amount = Third::transExchangerate($money, 0);
                            $upd['gb_winprice'] = $amount;
//                            $mx = $cfg['trdcfg']['tc_name'] . "_[ " . $this->game_name($data['game_id']) . " ] _重新结算_" . $tr_id;
                            $msg = UserModel::betRemarks($tr_id, $cfg['trdcfg']['tc_name'], $data['game_id'], '重新结算');
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $info['gid'], $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                            $upd_res = $GameBets->save($upd);
                        }
                    }else{
                        $this->PushLog($data['action'], '订单不存在', $data);
                        Db::rollback();
                    }
                }

                if ($upd_res) {
                    $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    $code = 'S-100';
                    $msg = 'success';
                    $arr['player_id'] = $data['player_id'];
                    $arr['amount'] = $money;
                    $arr['transaction_id'] = $tr_id;
                    $arr['updated_balance'] = $balance;
                    $arr['billing_at'] = $ts;
                    $this->PushLog("{$data['action']}_{$tr_type}", '注单操作成功', $data);
                    Db::commit();
                } else {
                    $this->PushLog("{$data['action']}_{$tr_type}", '注单操作失败', $data);
                    $code = 'E-000';
                    Db::rollback();
                }
            }else{
                $this->PushLog($data['action'], '订单重复请求', $data);
                $code = 'E-105';
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = 'E-000';
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    private function game_name($key): string
    {
        $filename = 'og_game.info.json';
        $dir= Utils::getStaticFilePath();
        $arr = json_decode(file_get_contents($dir.$filename), true);
        return  $arr[$key];
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
