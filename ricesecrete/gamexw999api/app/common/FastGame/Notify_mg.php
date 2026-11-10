<?php


namespace app\common\FastGame;

use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsex, GameBetsSon, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\Exception;
use think\exception\HttpException;
use think\facade\Db;

class Notify_mg extends Notify
{
    private $thirds_name = 'mg';

    private $x_mgp_req_id = '';

    private $start_time = '';

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

    //设置header
    private function setHeader($param){
        $time = $this->start_time ? $this->getMillisecond() - $this->start_time : 30;
        $time = max($time, 10);
        $param['header'] = [
            'X-MGP-REQ-ID' => $this->x_mgp_req_id, //請求的唯一識別碼（字串，GUID 格式）
//            'X-MGP-TOKEN ' => '', //API 令牌，用以認證 API 的存取（請參照上述“ 共享 API 令牌或自定義 API 令牌”）
//            'X-MGP-REQUEST_TIMEMS' => '', //請求的時間戳（數字格式，當前 UTC 時間，Epoch 毫秒類型）
            'X-MGP-RESPONSE-TIME' => $time, //回傳處理請求的時間（整數格式，以毫秒計）
        ];
        return $param;
    }

    //获取毫秒时间戳
    function getMillisecond() {
        return intval(microtime(1) * 1000);
    }

    /**
     * 生成GUID
     */
    private function create_guid() {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    /**
     * @param int $code
     * @param array $data
     * @param string $msg
     * @return array
     */
    private function returnData(int $code = 200, array $data = [], string $msg = ''): array
    {
        $data['currency'] = Util::i18n_currency_code();
        $data = $this->setHeader($data);
        if( $code == 200 ){
            return $data;
        }else{
            throw new HttpException($code, $msg);
        }
    }

    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function encryptToken($recCode, $key = 'three_mg'){
        return md5(md5($recCode).$key);
    }

    private function checkEncryptToken($recCode, $token, $key = 'three_mg'){
        if( $this->encryptToken($recCode, $key) != $token ){
            throw new Exception('API 令牌過期或無效', 401);
        }
    }

    //处理params
    private function getParam($param, $cfg = null){
        //设置x-mgp-req-id
        if( isset($param['header']['requestid']) ){
            $this->x_mgp_req_id = $param['header']['requestid'];
        }
        //设置开始时间戳（毫秒）
        $this->start_time = $this->getMillisecond();
        if( isset($param['param']) ){
            $param = $param['param'];
        }
        //设置平台id
        if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param;
    }

    //登录
    public function login($param, $trdobj = null, $cfg = null){
        $param = $this->getParam($param, $cfg);
        $this->pushLog($param, 'login', $trdobj);
        try {
            //获取用户余额
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $param['playerId'])->value(Meta::UFIELD['uid']);
            $balance = $this->getUserBalance($uid);
            return $this->returnData(200, [
                'balance' => $balance,
                'extOperatorToken' => $this->encryptToken($param['playerId'])
            ]);
        }catch (\Exception $e){
            $this->pushLog($e->getCode(), 'error', $e->getMessage(), 'error');
            return $this->returnData($e->getCode(), [], $e->getMessage());
        }
    }

    //获取余额
    public function getbalance($param, $trdobj = null, $cfg = null){
        $param = $this->getParam($param, $cfg);
        $this->pushLog($param, 'getbalance', $trdobj);
        try {
//            $this->checkEncryptToken($param['playerId'], $param['extOperatorToken']);
            //获取用户余额
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $param['playerId'])->value(Meta::UFIELD['uid']);
            $balance = $this->getUserBalance($uid);
            return $this->returnData(200, [
                'balance' => $balance,
            ]);
        }catch (\Exception $e){
            $this->pushLog($e->getCode(), 'error', $e->getMessage(), 'error');
            return $this->returnData($e->getCode(), [], $e->getMessage());
        }
    }

    //更新余额
    public function updatebalance($param, $trdobj, $cfg, $info = null){
        $action = 'updatebalance';
        $param = $this->getParam($param, $cfg);
        $this->pushLog($param, $action, [$trdobj, $info]);
        $msg = 'success';
        $return = [];
        $code = 200;
        Db::startTrans();
        try {
//            $this->checkEncryptToken($param['playerId'], $param['extOperatorToken']);
            $ts=time();
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $param['playerId'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uinfo=$uinfo->toArray();
                $betId = $param['betId'];
                $txnId = $param['txnId'];
                $completed = $param['completed']??0;
                $amount = $param['amount'];
                $amount = Third::transExchangerate($amount, 1, 2);
                $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['contentCode'], 'code', 'g_id');
                if ($gid) {
                    //判断输赢类型
                    if( $param['txnType'] == 'DEBIT' ){
                        //下分（下注）
                        $data = [
                            'gb_gid'=>$gid,
                            'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                            'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                            'gb_intime'=>$ts,
                            'gb_price'=>$amount,
                            'gb_sn'=>$betId,
                            'gb_winlose'=> 1,
                            'gb_bettime'=>$ts,
                            'gb_overtime'=>$ts,
                            'gb_winprice'=> 0,
                            'gb_betinfo'=>json_encode($param)
                        ];
                        if( $amount <= 0 ){
                            //写入子注单
                            $data['gb_sn_ac'] = $txnId;
                            $data['gb_action'] = $action;
                            $rlt=(new GameBetsSon())->insertGetId($data);
                        }else{
                            if( $amount > $userBalance ){
                                $msg = '余额不足';
                                $this->pushLog([$param, $trdobj, $cfg, $info], $msg, $param);
                                return $this->returnData(402, $param, $msg);
                            }
                            $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                            $data['gb_cjprice'] = $betcjprice;
                            //写入注单
                            $rlt=(new GameBets())->insertGetId($data);
                        }
                        if ($rlt){
                            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                            $code = 200;
                            $return['balance'] = $balance;
                            Db::commit();
                        } else {
                            $code = 500;
                            $msg = '插入注单失败';
                            Db::rollback();
                        }
                    }elseif ( $param['txnType'] == 'CREDIT' ){
                        //上分（结算）
                        //查询子注单
                        $bet = GameBetsSon::where('gb_gid', $gid)->where('gb_sn_ac', $txnId)->find();
                        if(!$bet){
                            //没有子注单为总订单
                            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $betId)->find();
                        }
                        $bet->gb_overtime = $ts;
                        if( $bet->gb_winlose == 1 ){
                            if($amount > 0 ){
                                $bet->gb_winlose = 3;
                                $bet->gb_winprice = $amount;
                            }else{
                                $bet->gb_winlose = 2;
                            }
                        }else{
                            $bet->gb_winprice += floatval($amount);
                        }

                        $bet->gb_betinfo = json_encode($param);
                        $bet->save();

                        if( $completed && $bet->gb_winprice > 0 ){
                            //总订单才操作用户余额
                            $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $bet->gb_winprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                        }

                        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                        $code = 200;
                        $return['balance'] = $balance;
                        Db::commit();
                    }else{
                        $msg = '类型错误';
                        $this->pushLog([$param, $trdobj, $cfg, $info], $msg, $param, 'error');
                        return $this->returnData(400, $param, $msg);
                    }

                } else {
                    $code = 500;
                    $msg = '游戏标志错误';
                    Db::rollback();
                }
                Db::commit();
                if( $code != 200 ){
                    $this->pushLog([$param, $trdobj, $cfg, $info], $msg, $param, 'error');
                }
                return $this->returnData($code, $return, $msg);
            } else {
                $msg = '用户数据不存在';
                $this->pushLog([$param, $trdobj, $cfg, $info], $msg, $param, 'error');
                return $this->returnData(404, $param, $msg);
            }
        } catch (\Throwable $e) {
            $this->PushLog($action, $msg, $e->getMessage(), 'error');
            $code = 500;
            Db::rollback();
        }
        $this->pushLog($return, $msg, 'success'.$code);
        return $this->ReturnData($code, $return, $msg);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
