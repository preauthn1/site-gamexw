<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Notify;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\User;
use app\common\FastGame\Base\Utils;
use app\common\model\UserModel;
use think\exception\HttpException;
use app\model\GameBets;
use app\model\Games;
use app\model\Users;
use think\facade\Db;

class Notify_universal extends Notify
{
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
        // TODO: Implement notify() method.
        return false;
    }

    /** 注单处理
     * @param $datas
     * @param Third $trdobj
     * @return bool
     * @author twt326
     * @date 2022-10-04 18:39
     */
    function play($datas,$trdobj,$cfg){
        $param=$datas['param'];
        $header=$datas['header'];
        $param = json_decode(file_get_contents('php://input', 'r'), true);
        
        Utils::L('universal',['notify:play','datas'=>$param,'cfg'=>$cfg,$trdobj?'trdobj':'trdobj false','check:'=>array_merge($param,['return_headers'=>$header])]);
        $signflag=$trdobj->CheckSign($cfg,array_merge($param,['return_headers'=>$header]));
        if ($signflag){
            Utils::L('universal',['notify:play','验签通过',$param['user_id']]);
            $uid=null;
            if (isset($param['user_id']) && !empty($param['user_id'])){
                Utils::L('universal',['notify:play','正确UID',$param['user_id']]);
                $uid=addslashes($param['user_id']);
            }else{
                Utils::L('universal',['notify:play','错误UID',$param]);
                abort(403,'Data error');
            }
            $ts=time();
            Db::startTrans();
            try {
                Utils::L('universal',['notify:play','准备查询用户：','uid'=>$uid,'field'=>[Meta::UFIELD['uname'],Meta::UFIELD['ubalance'],Meta::UFIELD['uid']]]);
                $uinfo = UserModel::where(Meta::UFIELD['uname'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                Utils::L('universal',['notify:play','uid'=>$uid,'uinfo'=>$uinfo?$uinfo->toArray():null]);
                if ($uinfo) {
                    $uinfo=$uinfo->toArray();
                    $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                    //判断注单是否已经处理
                    if (isset($param['actions']) && count($param['actions']) > 0) {
                        $tgid = $param['game_id'];
                        $tggame = $param['game'];
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$tggame, 'tag', 'g_id');
                        if ($gid) {
                            $bet = null;
                            $betid = null;
                            //foreach ($param['actions'] as $k => $v) {
                            Utils::L('universal',['notify:play','参数列表',$param]);
                            $v=$param['actions'][0];
                            $vv=(count($param['actions'])>1)?$param['actions'][1]:null;
                                if ($v['action'] == "bet") {
                                    $actionid = $v['action_id'];
                                    $aflag = Redis::get("uni_betactionid_" . $gid . "_" . $actionid);
                                    if ($aflag) {
                                        $return=[
                                            'balance'=>$userBalance*100,
                                            'game_id'=>$param['game_id'],
                                            'transactions'=>[
                                                0=>[
                                                    'action_id'=>$v['action_id'],
                                                    'tx_id'=>$v['action_id'],
                                                    'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                ]
                                            ]
                                        ];
                                        if ($vv){
                                            $return['transactions'][1]=[
                                                'action_id'=>$vv['action_id'],
                                                'tx_id'=>$vv['action_id'],
                                                'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                            ];
                                        }
                                        Db::commit();
                                        return $return;
                                    }
                                    Redis::setex("uni_betactionid_" . $gid . "_" . $actionid, 1, 60);//60秒内不接受重得action
                                    $bet = GameBets::where('gb_gid', $gid)->where('gb_cusfield', $tgid)->where('gb_sn', $actionid)->find();
                                    if ($bet) {
                                        $return=[
                                            'balance'=>$userBalance*100,
                                            'game_id'=>$param['game_id'],
                                            'transactions'=>[
                                                0=>[
                                                    'action_id'=>$v['action_id'],
                                                    'tx_id'=>$v['action_id'],
                                                    'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                ]
                                            ]
                                        ];
                                        if ($vv){
                                            $return['transactions'][1]=[
                                                'action_id'=>$vv['action_id'],
                                                'tx_id'=>$vv['action_id'],
                                                'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                            ];
                                        }
                                        Db::commit();
                                        return $return;
                                    }
                                    $amountb = $amount = $v['amount'] / 100;
                                    if(isset($vv['action']) && $vv['action']=="bet"){
                                        $amountb += $vv['amount'] / 100;
                                    }
                                    if ($amountb > $uinfo[Meta::UFIELD['ubalance']]) {
                                        $return=[
                                            'code'=>100,
                                            'message'=> "Not enough funds.",
                                            'balance'=>$uinfo[Meta::UFIELD['ubalance']]*100
                                        ];

                                        Db::commit();
                                        throw new HttpException(412, 'Not enough funds.', null, [], 100);
                                        //return $return;
                                    }
                                    $msg = UserModel::betRemarks($actionid, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//                                    if ($rlt){
                                        $data=[
                                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                            'gb_gid'=>$gid,
                                            'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                                            'gb_intime'=>$ts,
                                            'gb_price'=>$amount,
                                            'gb_cjprice'=>$betcjprice,
                                            'gb_cusfield'=>$tgid,
                                            'gb_sn'=>$actionid,
                                            'gb_winlose'=>1,
                                            'gb_bettime'=>$ts,
                                            'gb_overtime'=>$ts,
                                            'gb_winprice'=>0,
                                            'gb_betinfo'=>json_encode($param)
                                        ];
                                        //写入注单
                                        $betId=(new GameBets())->insertGetId($data);
                                        if ($vv){
                                            if($vv['action']=="win"){
                                                $actionid = $vv['action_id'];
                                                $aflag = Redis::get("uni_betactionid_" . $gid . "_" . $actionid);
                                                if (!$aflag) {
                                                    Redis::setex("uni_betactionid_" . $gid . "_" . $actionid, 1, 60);//60秒内不接受重得action
                                                    $wamount=$vv['amount'] / 100;
                                                    if($wamount > 0){
                                                        $msg = UserModel::betRemarks($actionid, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $wamount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $betId, $data['gb_price'], $data['gb_cjprice']);
//                                                        if ($rlt){
                                                            $data['gb_winprice']=$wamount;
                                                            $data['gb_winlose']=3;
//                                                        }else{
//                                                            Db::rollback();
//                                                            abort(403, 'run error');
//                                                        }
                                                    } else {
                                                        $data['gb_winlose']=2;
                                                    }
                                                }
                                                $data['gb_id'] = $betId;
                                                (new GameBets())->save($data);
                                            }elseif($vv['action']=="bet"){
                                                $amount2 = $vv['amount'] / 100;
                                                $actionid2 = $vv['action_id'];
                                                $msg = UserModel::betRemarks($actionid, $cfg['trdcfg']['tc_name'], $gid);
                                                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount2, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                                $data2=[
                                                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                                                    'gb_gid'=>$gid,
                                                    'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                                                    'gb_intime'=>$ts,
                                                    'gb_price'=>$amount2,
                                                    'gb_cjprice'=>$betcjprice,
                                                    'gb_cusfield'=>$tgid,
                                                    'gb_sn'=>$actionid2,
                                                    'gb_winlose'=>1,
                                                    'gb_bettime'=>$ts,
                                                    'gb_overtime'=>$ts,
                                                    'gb_winprice'=>0,
                                                    'gb_betinfo'=>json_encode($param)
                                                ];
                                                (new GameBets())->insertGetId($data2);
                                            }
                                        }
                                        if ($betId){
                                            $return=[
                                                'balance'=>$this->getUserBalance($uinfo[Meta::UFIELD['uid']])*100,
                                                'game_id'=>$param['game_id'],
                                                'transactions'=>[
                                                    0=>[
                                                        'action_id'=>$v['action_id'],
                                                        'tx_id'=>$v['action_id'],
                                                        'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                    ]
                                                ]
                                            ];
                                            if ($vv){
                                                $return['transactions'][1]=[
                                                    'action_id'=>$vv['action_id'],
                                                    'tx_id'=>$vv['action_id'],
                                                    'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                ];
                                            }
                                            Db::commit();
                                            return $return;
                                        }
//                                    }
                                    Db::rollback();
                                    abort(403, 'run bet error');
                                } else {
                                    //赢
                                    $actionid = $v['action_id'];
                                    $aflag = Redis::get("uni_betactionid_" . $gid . "_" . $actionid);
                                    if ($aflag) {
                                        Db::commit();
                                        $return=[
                                            'balance'=>$this->getUserBalance($uinfo[Meta::UFIELD['uid']])*100,
                                            'game_id'=>$param['game_id'],
                                            'transactions'=>[
                                                0=>[
                                                    'action_id'=>$v['action_id'],
                                                    'tx_id'=>$v['action_id'],
                                                    'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                ]
                                            ]
                                        ];
                                        return $return;
                                    }
                                    Redis::setex("uni_betactionid_" . $gid . "_" . $actionid, 1, 60);//60秒内不接受重得action
                                    $bet = GameBets::where('gb_gid', $gid)->where('gb_cusfield', $tgid)->find();
                                    if (!$bet || $bet->gb_winlose==3) {
                                        Db::commit();
                                        $return=[
                                            'balance'=>$this->getUserBalance($uinfo[Meta::UFIELD['uid']])*100,
                                            'game_id'=>$param['game_id'],
                                            'transactions'=>[
                                                0=>[
                                                    'action_id'=>$v['action_id'],
                                                    'tx_id'=>$v['action_id'],
                                                    'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                ]
                                            ]
                                        ];
                                        return $return;
                                    }
                                    $amount = $v['amount'] / 100;
                                    $msg = UserModel::betRemarks($actionid, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                                    if ($rlt){
                                        $bet->gb_betinfo = json_encode($param);
                                        $bet->gb_winprice=$amount;
                                        $bet->gb_winlose=3;
                                        $rlt=$bet->save();
                                        if ($rlt){
                                            Db::commit();
                                            $return=[
                                                'balance'=>$this->getUserBalance($uinfo[Meta::UFIELD['uid']])*100,
                                                'game_id'=>$param['game_id'],
                                                'transactions'=>[
                                                    0=>[
                                                        'action_id'=>$v['action_id'],
                                                        'tx_id'=>$v['action_id'],
                                                        'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                                    ]
                                                ]
                                            ];
                                            return $return;
                                        }
//                                    }else {
//                                        Db::rollback();
//                                        abort(403, 'run win error');
//                                    }
                                }
                            //}

                        } else {
                            Db::commit();
                            $v=$param['actions'][0];
                            return ['balance' => $this->getUserBalance($uinfo[Meta::UFIELD['uid']])*100,'game_id'=>$param['game_id'],
                                'transactions'=>[
                                        0=>[
                                            'action_id'=>$v['action_id'],
                                            'tx_id'=>$v['action_id'],
                                            'processed_at'=>date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                        ]
                                    ]];
                        }
                    } else {
                        Db::commit();
                        return ['balance' => $uinfo[Meta::UFIELD['ubalance']]*100];

                    }
                } else {
                    Utils::L('universal',['notify:play','用户错误',$uid]);
                    Db::rollback();

                    abort(403, 'User error');
                }
            }catch (\Exception $ex){
                Utils::L('universal',['notify:play','过程错误',$ex->getMessage(),$ex->getCode()]);
                Db::rollback();
                if($ex->getCode() == 100){
                    throw new HttpException(412, $ex->getMessage(), null, [], $ex->getCode());
                } else {
                    abort(403, 'error');
                }
            }
        }else{
            Utils::L('universal',['notify:play','签名错误',$cfg]);
            throw new HttpException(403, 'signature error', null, [], 403);
            //return 'sign check faild';
        }
    }

    function rollback($datas,$trdobj,$cfg){
        $param=$datas['param'];
        $header=$datas['header'];
        $param = json_decode(file_get_contents('php://input', 'r'), true);
        Utils::L('universal',['notify:play',$datas,$trdobj?'trdobj':'trdobj false']);
        $signflag=$trdobj->CheckSign($cfg,array_merge($param,['return_headers'=>$header]));
        if ($signflag){
            $uid=null;
            if (isset($param['user_id']) && !empty($param['user_id'])){
                $uid=addslashes($param['user_id']);
            }else{
                abort(403,'Data error');
            }
            //Db::startTrans();
            try {
                //$uinfo = UserModel::where(Meta::UFIELD['uname'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                //因 202210062206 只有加的操作，不用锁定
                $uinfo = UserModel::where(Meta::UFIELD['uname'], $uid)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                if ($uinfo) {
                    //判断注单是否已经处理
                    if (isset($param['actions']) && count($param['actions']) > 0) {
                        $tgid = $param['game_id'];
                        $tggame = $param['game'];
                        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$tggame, 'tag', 'g_id');
                        if ($gid) {
                            $bet = null;
                            $betid = null;
                            $return=[
                                'game_id'=>$param['game_id'],
                                'transactions'=>[

                                ]
                            ];
                            $ts = time();

                            foreach ($param['actions'] as $k => $v) {

                                if ($v['action'] == "rollback") {
                                    array_push($return['transactions'], [
                                        'action_id' => $v['action_id'],
                                        'tx_id' => $v['action_id'],
                                        'processed_at' => date('Y-m-d\\TH:i:s.Z\\Z', $ts)
                                    ]);
                                    $actionid = $v['action_id'];
                                    $aflag = Redis::get("uni_betactionid_" . $gid . "_" . $actionid);
                                    if (!$aflag) {

                                        Redis::setex("uni_betactionid_" . $gid . "_" . $actionid, 1, 60);//60秒内不接受重得action
                                        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $v['original_action_id'])->where('gb_winlose','>',0)->find();
                                        if ($bet) {
                                            Db::startTrans();
                                            $money = $bet->gb_winprice - $bet->gb_price;
                                            if ($money > 0) {
                                                //TODO 验证如果回滚是否只回滚未赢注单，则这里不需要，如果回滚包括赢的注单，那么这里要考虑回滚的是输赢，还是注单，如果是前者，那下面这条语句可以，如果是后者，那要扣减的就是赢金额和投注金额的差额 202210062100
                                                $msg = UserModel::betRemarks($v['original_action_id'], $cfg['trdcfg']['tc_name'], $gid, '下注结果回滚');
                                                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $bet->gb_winprice - $bet->gb_price, '-', 4, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                                $bet->gb_winprice=0; //TODO 这里未来要考虑是否回滚会影响分佣结果
                                                $bet->gb_winlose=0;
                                            }else {
                                                $msg = UserModel::betRemarks($v['original_action_id'], $cfg['trdcfg']['tc_name'], $gid, '取消');
                                                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $bet->gb_price - $bet->gb_winprice, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                                                $bet->gb_winlose=0;//TODO 这里未来要考虑是否回滚会影响分佣结果
                                            } //TODO 说明：注释是因 瞳 已确认回滚就是用来取消下单的，所以只保留一个处理逻辑 202210062206
                                                $bet->gb_betinfo = json_encode($param);
                                                $rlt=$bet->save();
                                                
                                                //Db::commit();
                                               // Db::startTrans();
                                                Db::commit();
                                        }
                                    }

                                }

                            }
                            $return['balance']=$this->getUserBalance(Meta::UFIELD['uid'])*100;
                            return $return;

                        }else {
                            Db::rollback();
                            abort(403, 'Game flag error');
                        }
                    }
                } else {
                    Db::rollback();
                    abort(403, 'User error');
                }
            }catch (\Exception $ex){
                Db::rollback();
                abort(403, 'error');
            }
        }else{
            throw new HttpException(403, 'signature error', null, [], 403);
            //return 'sign check faild';
        }
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }

}
