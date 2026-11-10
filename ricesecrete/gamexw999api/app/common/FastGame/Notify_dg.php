<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;
use think\facade\Request;

class Notify_dg extends Notify
{
    private $thirds_name = 'dg';

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
        $verify = $this->verify($datas['token'], $cfg);
        if($verify){
            $key = in_array('account',array_keys($datas)) ? 'account':'user';
            $Method = $datas[$key];
            $this->PushLog($Method,'notify_data',$datas);
            return self::$Method($datas,$trdobj,$cfg);
        }else{
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog('notify','token验证失败',$datas);
            return $this->ReturnData(2);
        }
    }

    /**
     * @return array
     */
    private function ReturnData(int $code = 0, string $token = '', array $data = []): array
    {
        $arr['codeId'] = $code;
        $arr['token'] = $token;
        $arr = array_merge($arr, $data);
        $this->PushLog('ReturnData','返回数据',$arr);
        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }

    //获取用户余额
    function getBalance($data, $trdobj, $cfg)
    {
        $action = 'getBalance';
        $arr = [];
        $user = UserModel::where(Meta::UFIELD['uname'], $data['member']['username'])->field(Meta::UFIELD['uid'].','.Meta::UFIELD['ubalance'])->find();
        if($user){
            $balance = Third::transExchangerate($user['balance'], 1, 2);
            $code = 0;
            $arr['member'] = [
                'username'=> $data['member']['username'],
                'balance'=> floatval($balance),
            ];
        }else{
            $code = 102;
            $this->PushLog($action, '用户不存在', $data);
        }
        return $this->ReturnData($code, $data['token'], $arr);
    }

    //验证
    function verify($token, $cfg)
    {
        $key = md5($cfg['trdcfg']['tc_tag'].$cfg['trdcfg']['tc_api_signkey']);
        if($token == $key) return true; else return false;
    }

    function get_Balance($uid){
        $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
        $balance = Third::transExchangerate($balance,1,2);
        return floatval($balance);
    }

    //存取款
    function transfer($data, $trdobj, $cfg){
        $action = 'transfer';
        $redis_key = $this->thirds_name."_".$action."_";
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        $ts=time();
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['member']['username'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
                $ubalance = floatval($uinfo[Meta::UFIELD['ubalance']]);
                $order_sn = $data['data'];
                $order_sn2 = $data['ticketId'];
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                $GamePayLogsModel = app()->make(GamePayLogs::class);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $bet = $GamePayLogsModel->where(['gpl_uid'=>$uid,'gpl_sn'=>$order_sn])->find();
                    if(!$bet){
                        $amount =  Third::transExchangerate(trim($data['member']['amount'],'-'),0);
                        if ($data['member']['amount'] > 0) {
                            $gpl_type = 1; $ste = '+'; $msg = '结算';
                        } else {
                            $gpl_type = 2; $ste = '-'; $msg = '下注';
                        }
                        $pay_log = [
                            'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                            'gpl_gid' => 0,
                            'gpl_uid' => $uid,
                            'gpl_type' => $gpl_type,
                            'gpl_price' => $amount,
                            'gpl_sn' => $order_sn,
                            'gpl_text' => $order_sn2,
                            'gpl_state' => 1,
                            'gpl_create_time' => date("Y-m-d H:i:s"),
                            'gpl_info' => json_encode($data),
                        ];
                        if ($gpl_type == 2 && $amount > $ubalance){
                                $code = 120;
                                $this->PushLog($action, '用户:' . $uid . "余额不足", $data);
                                $res['data'] = $data['data'];
                                $res['member'] = [
                                    'username'=>$data['member']['username'],
                                    'amount'=>$data['member']['amount'],
                                    'balance'=>$ubalance ??0,
                                ];
                                Db::rollback();
                                return $this->ReturnData($code, $data['token'], $res);
                        }
                        if($amount > 0){
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], 0, $msg);
                            User::accountChange($uid, $amount, $ste, $gpl_type == 2 ? 1:2, $msg);
                        }
                        $GamePayLogsModel->insertGetId($pay_log);
                        $code = 0;
                        Db::commit();
                    }else{
                        $code = 323;
                        $this->PushLog($action,'订单已存在',$data);
                        Db::rollback();
                    }
                }else{
                    $code = 405;
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = 102;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (Exception $ex) {
            $code = 503;
            $this->PushLog($action,'过程错误',[$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['data'] = $data['data'];
        $res['member'] = [
            'username'=>$data['member']['username'],
            'amount'=>$data['member']['amount'],
            'balance'=>$ubalance ??0,
        ];
        return $this->ReturnData($code, $data['token'], $res);
    }

    //确认存取款结果
    function checkTransfer($data, $trdobj, $cfg)
    {
        $action = 'checkTransfer';
        $bets = GamePayLogs::where(['gpl_sn'=>$data['data'] ?? 0])->value('gpl_id');
        $code = 0;
        if(!$bets){
            $code = 98;
            $this->PushLog($action, '确认存取款结果：订单不存在', $data);
        }
        return $this->ReturnData($code, $data['token']);
    }

    //回滚
    function inform($data, $trdobj, $cfg){
        $action = 'inform';
        $redis_key = $this->thirds_name."_".$action."_";
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $data['member']['username'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $ubalance = $uinfo[Meta::UFIELD['ubalance']];
                $uid = $uinfo[Meta::UFIELD['uid']];
                $order_sn = $data['data'];
                $order_sn2 = $data['ticketId'];
                $amount =  Third::transExchangerate(trim($data['member']['amount'],'-'),0);
                $aflag = Redis::get($redis_key . "_" . $order_sn);
                if (!$aflag) {
                    Redis::setex($redis_key . "_" . $order_sn, 1, 10);
                    $paylog_sn = GamePayLogs::where(['gpl_uid'=>$uid,'gpl_sn'=>$order_sn])->value('gpl_text');
                    if($paylog_sn){
                        $order_sn3 = '-'.$paylog_sn;
                        $bets = GameBets::where([['gb_sn','=',$order_sn3],['gb_winlose','<>',0]])->find();
                        if($bets){
                            $bets->gb_overtime = time();
                            $bets->gb_winlose = 0;
                            $bets->save();
                            $msg = UserModel::betRemarks($order_sn, $cfg['trdcfg']['tc_name'], 0, '取消');
                            User::accountChange($uid, $amount, '+', 2, $msg, true, $bets->gb_tcid, $bets->gb_gid, $bets->gb_id, $bets->gb_price, $bets->gb_cjprice);
                            $code = 0;
                            Db::commit();
                        }else{
                            $res['data'] = $data['data'];
                            $res['member'] = [
                                'username'=>$data['member']['username'],
                                'balance'=>$ubalance ??0
                            ];
                            Db::rollback();
                            return $this->ReturnData(98, $data['token'], $res);
                        }
                    }else{
                        if($data['member']['amount'] >= 0){
                            $pay_log = [
                                'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                                'gpl_gid' => 0,
                                'gpl_uid' => $uid,
                                'gpl_type' => 1,
                                'gpl_price' => $amount,
                                'gpl_sn' => $order_sn,
                                'gpl_text' => $order_sn2,
                                'gpl_state' => 2,
                                'gpl_create_time' => date("Y-m-d H:i:s"),
                                'gpl_info' => json_encode($data),
                            ];
                            GamePayLogs::insert($pay_log);
                        }
                        return $this->ReturnData(98, $data['token'], $res);
                    }
                }else{
                    $code = 405;
                    $this->PushLog($action,'订单请求频繁',$data);
                    Db::rollback();
                }
            } else {
                $code = 102;
                $this->PushLog($action,'错误:用户信息不存在',$data);
                Db::rollback();
            }
        } catch (Exception $ex) {
            $code = 503;
            $this->PushLog($action,'过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
        }
        $res['data'] = $data['data'];
        $res['member'] = [
            'username'=>$data['member']['username'],
            'balance'=>$ubalance ??0
        ];
        return $this->ReturnData($code, $data['token'], $res);
    }

    //对账接口
    function order($data, $trdobj, $cfg)
    {
        $res['ticketId'] = intval($data['ticketId']);
        $da = [];$price = 0;
        $list = Db::name('games')->alias('a')->where(['a.g_tcid'=>$cfg['trdcfg']['tc_id'],'b.gb_cusfield'=>$data['ticketId']])
            ->join('game_bets b','a.g_id=b.gb_gid')
            ->field('b.gb_uid,b.gb_sn,b.gb_winlose,b.gb_winprice')
            ->limit(500)
            ->order('b.gb_id desc')
            ->select();
        foreach ($list as $k=>$v){
            $da[$k]['username'] = Db::name('user')->where('id',$v['gb_uid'])->value('rec_code');
            $da[$k]['ticketId'] = intval($data['ticketId']);
            $da[$k]['data'] = $v['gb_sn'];
            if($v['gb_winlose'] == 3){
                $price = $v['gb_winprice'];
            }else if($v['gb_winlose'] == 2){
                $price = '-'.$v['gb_winprice'];
            }
            $da[$k]['amount'] = floatval($price);
        }
        $res['list'] = $da;
        return $this->ReturnData(0, $data['token'], $res);
    }

    //查询未派彩和未回滚记录
    function unsettle($data, $trdobj, $cfg)
    {
        $time = time() - 600;
        $list = Db::name('games')->alias('a')->where(['a.g_tcid'=>$cfg['trdcfg']['tc_id']])
            ->join('game_bets b','a.g_id=b.gb_gid')
            ->where([['b.gb_winlose','=',1],['b.gb_bettime','<',$time]])
            ->field('b.gb_uid,b.gb_cusfield,b.gb_sn,b.gb_winlose,b.gb_winprice')
            ->limit(500)
            ->order('b.gb_id desc')
            ->select();
        $da = [];$price = 0;
        foreach ($list as $k=>$v){
            $da[$k]['username'] = Db::name('user')->where('id',$v['gb_uid'])->value('rec_code');
            $da[$k]['ticketId'] = intval($v['gb_cusfield']);
            $da[$k]['data'] = $v['gb_sn'];
            if($v['gb_winlose'] == 3){
                $price = $v['gb_winprice'];
            }else if($v['gb_winlose'] == 2){
                $price = '-'.$v['gb_winprice'];
            }
            $da[$k]['amount'] = floatval($price);
        }
        $res['list'] = $da;
        return $this->ReturnData(0, $data['token'], $res);
    }

}
