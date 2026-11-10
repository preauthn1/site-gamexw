<?php


namespace app\common\FastGame;

use app\common\lib\Util;
use app\common\model\UserMsgModel;
use Carbon\Carbon;
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GameBetsex, GameBetsSon, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\Exception;
use think\exception\HttpException;
use think\facade\Db;

class Notify_cg extends Notify
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
    public function balance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'balance', $trdobj);
        try {
            $channelId = $param['channelId'];
            $accountId = $param['accountId'];
            //验证token
            $this->checkToken($params, $channelId, $cfg);
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $accountId)->value(Meta::UFIELD['uid']);
            $userBalance = $this->getBalance($uid);
            return [
                'channelId' => $channelId,
                'accountId' => $accountId,
                'balance' => (float)$userBalance,
                'currency' => Util::i18n_currency_code(),
                'errorCode' => 0,
                'returnTime' => Carbon::now()->format(DATE_ATOM),
            ];

        }catch (\Exception $e){
            $this->pushLog($param, 'check user error：'.$e->getMessage(), $trdobj);
        }
    }

    //下注
    //channelId 启动游戏时 URL 所带入的渠道编号
    //accountId 贵端平台 [GetLoginAccountURL]所响应的玩家账号内容
    //gameType 玩家正在游玩的游戏标识符串 String
    //roundId CG 所产生的交易局号 String
    //amount 玩家押注的数值 Number
    //currency 币别 String
    //mtcode CG 所产生的交易混合 码，押注之交易混合码 必定为「mtcode-bet-xxxx」格式的字符串，如：mtcode-bet-11276
    //eventTime 玩家押注的事件时间，供钱包平台纪录或检验timeout 用。时间字符串格式为：2019-09-19T11:23:39.157+08:00
    public function bet($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'balance', $trdobj);

        Db::startTrans();
        try {
            $channelId = $param['channelId'];
            $accountId = $param['accountId'];
            $roundId = $param['roundId'];
            $gameType = $param['gameType'];
            $amount = $param['amount'];
            $amount = Third::transExchangerate($amount, 0, 2);
            $ts=time();
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $accountId)->find();
            $userBalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData = [
                'channelId' => $channelId,
                'accountId' => $accountId,
                'balance' => (float)$userBalance,
                'currency' => Util::i18n_currency_code(),
                'errorCode' => 0,
                'returnTime' => Carbon::now()->format(DATE_ATOM),
            ];
            if( $amount < 0 ){
                $this->pushLog($param, '用户余额为负', $trdobj);
                $returnData['errorCode'] = 107;
                return $returnData;
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameType, 'code', 'g_id');
            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> 1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=> 0,
                'gb_betinfo'=>json_encode($param)
            ];
            $this->pushLog($param, 'balance data', $data);
            if( $amount > $uinfo[Meta::UFIELD['ubalance']] ){
                $msg = '余额不足';
                Db::rollback();
                $returnData['errorCode'] = 101;
                $this->pushLog([$param, $trdobj, $cfg, $uinfo], $msg, $param);
                return $returnData;
            }
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//            if ($betcjprice){
                $data['gb_cjprice'] = $betcjprice;
                //写入注单
                $rlt=(new GameBets())->insertGetId($data);
                if ($rlt){
                    $userBalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
                    $returnData['balance'] = (float)$userBalance;
                    $returnData['errorCode'] = 0;
                    Db::commit();
                    return $returnData;
                } else {
                    $msg = '插入注单失败';
                    Db::rollback();
                    $returnData['errorCode'] = 105;
                    $this->pushLog($param, $msg, $trdobj);
                    return $returnData;
                }
//            } else {
//                $msg = '操作余额失败';
//                Db::rollback();
//                $returnData['errorCode'] = 107;
//                $this->pushLog($param, $msg, $trdobj);
//                return $returnData;
//            }
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'bet error：'.$e->getMessage(), [$e->getFile(),$e->getLine(),$trdobj]);
        }
    }

    //结算
    public function endround($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'balance', $trdobj);

        Db::startTrans();
        try {
            $channelId = $param['channelId'];
            $accountId = $param['accountId'];
            $roundId = $param['roundId'];
            $gameType = $param['gameType'];
            $amount = $param['amount'];
            $amount = Third::transExchangerate($amount, 0, 2);
            $ts = time();
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $accountId)->find();
            $userBalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData = [
                'channelId' => $channelId,
                'accountId' => $accountId,
                'balance' => (float)$userBalance,
                'currency' => Util::i18n_currency_code(),
                'errorCode' => 0,
                'returnTime' => Carbon::now()->format(DATE_ATOM),
            ];
            if( $amount < 0 ){
                $this->pushLog($param, '用户余额为负', $trdobj);
                $returnData['errorCode'] = 107;
                return $returnData;
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameType, 'code', 'g_id');
            //没有子注单为总订单
            $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', $roundId)->find();
            $bet->gb_overtime = $ts;
            if($amount > 0 ){
                $bet->gb_winlose = 3;
                $bet->gb_winprice = $amount;
            }else{
                $bet->gb_winlose = 2;
            }

            $bet->gb_betinfo = json_encode($param);
            $bet->save();

            if( $amount > 0 && $bet->gb_winprice > 0 ){
                //总订单才操作用户余额
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
//                if (!$rlt){
//                    Db::rollback();
//                    $msg = '操作余额失败';
//                    $this->pushLog([$param, $trdobj, $cfg, $uinfo], $msg, $param, 'error');
//                    $returnData['errorCode'] = 107;
//                    return $returnData;
//                }
            }

            $userBalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['errorCode'] = 0;
            $returnData['balance'] = (float)$userBalance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'endround error：'.$e->getMessage(), $trdobj);
        }
    }

    //查询交易记录
    public function record($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'record', $trdobj);
        $channelId = $param['channelId']??'';
        $mtcode = $param['mtcode']??'';
        $roundId = $param['roundId']??'';
        $accountId = $param['accountId']??'';
        $returnData = [
            'data' => [
                'transaction_id' => 'null',
                'action' => '',
                'target' => '',
                'balance' => 0,
                'status' => '',
                'currency' => Util::i18n_currency_code(),
                'incident' => '',
            ],
            'channelId' => $channelId,
            'errorCode' => 103,
            'returnTime' => Carbon::now()->format(DATE_ATOM),
        ];
        //获取注单数据
        $bet = GameBets::where('gb_sn', $roundId)->find();
        if( $bet ){
            //获取用户余额
            $balance = $this->getBalance($bet->gb_uid);
            $status = 'success';
            $statusMessage = 'success';
            if( $bet->gb_winlose == 1 ){
                $action = 'bet';
                $beforeBalance = $balance + $bet->gb_price;
            }else{
                $action = 'endround';
                $beforeBalance = $balance - $bet->gb_winprice;
            }
            if( $bet->gb_winlose == 0) {
                //退款后的记录其他都不变，金额还是显示退款之前的
                $beforeBalance = $balance + $bet->gb_winprice;
                $balance -= $bet->gb_price;
                $action = 'bet';
                $status = 'refund';
                $statusMessage = 'refund';
            }
            //获取eventTime时间，从之前参数中获取
            $betinfo = GameBetsex::where('gbe_id', $bet->gb_id)->order('gbe_inmonth desc')->value('gbe_betinfo');
            $eventTime = Carbon::now()->format(DATE_ATOM);
            if( $betinfo ){
                $betinfo = json_decode($betinfo, true);
                $eventTime = $betinfo['eventTime']??$eventTime;
            }
            $returnData['data'] = [
                'transaction_id' => (string)$bet->gb_id,
                'action' => $action,
                'target' => [
                    'account' => $accountId
                ],
                'balance' => [
                    'before' => (float)$beforeBalance, //交易前余额
                    'after' => (float)$balance, //交易后余额
                ],
                'status' => [
                    'createtime' => Carbon::parse(date('Y-m-d H:i:s', $bet->gb_intime))->format(DATE_ATOM),
                    'endtime' => Carbon::parse(date('Y-m-d H:i:s', $bet->gb_overtime))->format(DATE_ATOM),
                    'status' => $status,
                    'message' => $statusMessage,
                ],
                'currency' => Util::i18n_currency_code(),
                'incident' => [
                    'mtcode' => $mtcode,
                    'amount' => (float)$bet->gb_price,
                    'eventtime' => $eventTime,
                ],
            ];
            $returnData['channelId'] = $channelId;
            $returnData['errorCode'] = 0;
        }
        return $returnData;
    }

    //退款
    public function refund($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'refund', $trdobj);
        $channelId = $param['channelId'];
        $mtcode = $param['mtcode'];
        $roundId = $param['roundId'];
        $accountId = $param['accountId'];
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $accountId)->find();
        $userBalance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
        $returnData = [
            'channelId' => $channelId,
            'accountId' => $accountId,
            'balance' => (float)$userBalance,
            'currency' => Util::i18n_currency_code(),
            'errorCode' => 0,
            'returnTime' => Carbon::now()->format(DATE_ATOM),
        ];
        //获取注单
        $bet = GameBets::where('gb_sn', $roundId)->find();
        if( !$bet ){
            //没有注单
            $returnData['errorCode'] = 103;
            return $returnData;
        }
        //判断是否处理
        if($bet->gb_winlose == 0){
            //已处理
            $returnData['errorCode'] = 104;
            return $returnData;
        }
        //处理订单数据
        Db::startTrans();
        try {
            //修改注单结果
            $bet->gb_winlose = 0;
            $bet->gb_overtime = time();
            $bet->gb_betinfo = json_encode($param);
            $bet->save();
            //给用户退款、
            $gid = 0;
            if( isset($gameType) ){
                $gameType = $param['gameType'];
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameType, 'code', 'g_id');
            }
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '退款');
            User::accountChange($uinfo[Meta::UFIELD['uid']], $bet->gb_price, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            //给用户发送消息通知
            UserMsgModel::add_msg($uinfo[Meta::UFIELD['uid']], '游戏退款', lang('游戏退款已到账,订单号：').$roundId, 30, $bet->gb_id);
            Db::commit();
            $balance = $this->getBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = (float)$balance;
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'refund error：'.$e->getMessage(), $trdobj);
        }
    }

    //验证参数token
    //1、使用当前接口平台加密方法加密渠道id$channelId放入后台Wtoken中
    //2、反方向解密验证是否等于$channelId
    private function checkToken($params, $channelId, $cfg){
        return true;
        if( isset($params['header']['wtoken']) ){
            $key = $cfg['trdcfg']['tc_api_prikey'];
            $iv = $cfg['trdcfg']['tc_api_signkey'];
            $token = $this->aes256CbcDecrypt($key, $params['header']['wtoken'], $iv);
            if( $token != $channelId ){
                throw new Exception('token err');
            }
            return true;
        }
        throw new Exception('token err');
    }

    //验证数据登录用户
    //version=1.0，此为固定值
    //channelId=66316，此为前面启动游戏 URL 中所带入的 channelId
    //data=n1YuCJnzzhLTLJmKR2UOZj26aRf15Ks33jMMbIxhhNs=，此为 Json 字符串
    //{"token":"cg_login_token"}
    //加密后的结果。CG 会从启动游戏 URL 中所带入的 data 进行解密后得到验证用
    //token 字符串：
    //cg_login_token
    //然后 CG 会进行 Json 的包装成为
    //{"token":"cg_login_token"}
    //再对 Json 字符串进行加密后即得
    //n1YuCJnzzhLTLJmKR2UOZj26aRf15Ks33jMMbIxhhNs=
    public function checkUser($param, $trdobj = null, $cfg = null){
        $param = $this->getParam($param, $cfg);

        $this->pushLog($param, 'check_user', $trdobj);
        try {
            $key = $cfg['trdcfg']['tc_api_prikey'];
            $iv = $cfg['trdcfg']['tc_api_signkey'];
            $channelId = $param['channelId'];
            $reCode = json_decode($this->aes256CbcDecrypt($key, $param['data'], $iv), true)['token'];
            //获取用户数据
            $userDetail = UserModel::where(Meta::UFIELD['uname'], '=', $reCode)->field('username,rec_code')->find();
            if( $userDetail ){
                echo $this->aes256CbcEncrypt($key, json_encode([
                    'channelId' => $channelId,
                    'accountId' => $userDetail->rec_code,
                    'nickName' => $userDetail->username,
                    'errorCode' => 0,
                ]), $iv);exit;
            }
        }catch (\Exception $e){
            $this->pushLog($param, 'check user error：'.$e->getMessage(), $trdobj);
        }

    }


    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    //处理params
    private function getParam($param, $cfg = null){
        //设置平台id
        if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param['param']??$param;
    }

    private function aes256CbcDecrypt($key, $data, $iv)
    {
        $raw_key = base64_decode($key);
        $raw_iv = base64_decode($iv);
        //$raw_data = base64_decode($data);
        $raw_data = $data;

        return openssl_decrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }

    private function aes256CbcEncrypt($key, $data, $iv)
    {
        $raw_key = base64_decode($key);
        $raw_iv = base64_decode($iv);
        //$raw_data = base64_decode($data);
        $raw_data = $data;

        return openssl_encrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }

    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
