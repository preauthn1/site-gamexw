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

class Notify_png extends Notify
{
    private $tcid = 98;

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
        $xmlString = file_get_contents('php://input');
        $xmlob = new \SimpleXMLElement($xmlString);

        $action = $xmlob->getName();
        $this->pushLog($action, 'png请求xml', $xmlString);
        $param = json_decode(json_encode($xmlob),true);

        return $this->$action($param, $trdobj, $cfg);
    }

    private function arrayToXml($data, $rootElement = '<response/>', $xml = null)
    {
        if ($xml === null) {
            $xml = new \SimpleXMLElement($rootElement);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXml($value, "<$key/>", $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }


    //用户认证
    private function authenticate($params, $trdobj = null, $cfg = null){

        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $currency = $cfg['trdcfg']['tc_currency'];
            $userId = $param['username'];
            $language = $param['language'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            if (!$uid) {
                //用户不存在
                $this->pushLog($params, '用户名不存在', $trdobj);
                $responseData = [
                    'statusCode' => 4,
                    'statusMessage' => 'WRONGUSERNAMEPASSWORD'
                ];
                return $this->ReturnData($responseData, '<authenticate/>');
            }
            $userBalance = $this->getUserBalance($uid);

            // 处理请求并准备返回的数据
            $responseData = [
                'statusCode' => 0,
                'statusMessage' => 'ok',
                'externalId' => $userId,
                'userCurrency' => $currency,
                'country' => 'SE',
                'language' => $language,
                'birthdate' => '2000-01-02',
                'registration' => '2024-05-07',
                'real' => Third::transExchangerate($userBalance, 1, 2)
            ];
            // 将数据转换为 XML 格式
            $xmlResponse = $this->arrayToXml($responseData,'<authenticate/>');
            $arr['xml'] = $xmlResponse;
            $arr['type'] = 'xml';
            return $arr;

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<authenticate/>');
        }
    }

    //获取余额
    private function balance($params, $trdobj = null, $cfg = null){
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'get balance', $trdobj);
        try {
            $currency = $cfg['trdcfg']['tc_currency'];
            $userId = $param['externalId'];
            //获取用户数据
            $uid = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->value(Meta::UFIELD['uid']);
            if (!$uid) {
                //用户不存在
                $this->pushLog($params, '用户名不存在', $trdobj);
                $responseData = [
                    'statusCode' => 4,
                    'statusMessage' => 'WRONGUSERNAMEPASSWORD'
                ];
                return $this->ReturnData($responseData, '<balance/>');
            }
            $userBalance = $this->getUserBalance($uid);

            // 处理请求并准备返回的数据
            $responseData = [
                'statusCode' => 0,
                'statusMessage' => 'ok',
                'real' => Third::transExchangerate($userBalance, 1, 2)
            ];

            // 将数据转换为 XML 格式
            $xmlResponse = $this->arrayToXml($responseData,'<balance/>');
            $arr['xml'] = $xmlResponse;
            $arr['type'] = 'xml';
            return $arr;

        }catch (\Exception $e){
            $this->pushLog($param, 'get balance error：'.$e->getMessage(), $trdobj);
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<balance/>');
        }
    }

    //取消
    private function cancelReserve($datas, $trdobj, $cfg) {
        $param = $this->getParam($datas, $cfg);
        $this->pushLog($param,'取消', $trdobj);
        try {
            $username = $datas['externalId'];
            $betId = $datas['roundId'];
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
            if (!$uinfo) {
                //用户不存在
                $this->pushLog($datas, '用户名不存在', $trdobj);
                $responseData = [
                    'statusCode' => 4,
                    'statusMessage' => 'WRONGUSERNAMEPASSWORD'
                ];
                return $this->ReturnData($responseData, '<cancelReserve/>');
            }
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            //获取注单
            $bet = GameBets::where('gb_sn', '=', $betId)->find();
            if (!$bet) {
                //注单不存在
                $this->pushLog($datas, '注单不存在', $trdobj);
                $responseData = [
                    'statusCode' => 0,
                    'externalTransactionId' => '',
                    'statusMessage' => 'OK'
                ];
                return $this->ReturnData($responseData, '<cancelReserve/>');
            }
            if ($bet->gb_winlose == 0) {
                $responseData = [
                    'statusCode' => 0,
                    'externalTransactionId' => $datas['transactionId'],
                    'statusMessage' => 'OK'
                ];
                return $this->ReturnData($responseData, '<cancelReserve/>');
            }
            //计算该退回给用户多少钱
            $userAddAmount = $bet->gb_price - $bet->gb_winprice;
            if ($userAddAmount > 0) {
                $symbol = '+';
                $type = 3;
            } else {
                $symbol = '-';
                $type = 4;
                $userAddAmount = abs($userAddAmount);
                if ($userAddAmount > $userBalance) {
                    $this->pushLog($datas, '用户余额不足', $trdobj);
                    $responseData = [
                        'statusCode' => 1,
                        'statusMessage' => 'NOTENOUGHMONEY'
                    ];
                    return $this->ReturnData($responseData, '<cancelReserve/>');
                }
            }
            $gid = $bet->gb_gid;

        } catch (\Exception $e) {
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<cancelReserve/>');
        }

        Db::startTrans();
        try {
            $bet->gb_winlose = 0;
            $bet->gb_betinfo = json_encode($datas);
            $rlt = $bet->save();
            if (!$rlt) {
                Db::rollback();
                $this->pushLog($datas, '取消注单保存失败', $trdobj);
                $responseData = [
                    'statusCode' => 1,
                    'statusMessage' => 'INTERNAL'
                ];
                return $this->ReturnData($responseData, '<authenticate/>');
            }
            if ($userAddAmount > 0) {
                //增加或减少用户余额
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid, '注单回滚');
                if ($symbol == '+') {
                    $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $userAddAmount, $symbol, $type, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<authenticate/>');
        }

        // 处理请求并准备返回的数据
        $responseData = [
            'statusCode' => 0
        ];

        // 将数据转换为 XML 格式
        $xmlResponse = $this->arrayToXml($responseData,'<cancelReserve/>');
        $arr['xml'] = $xmlResponse;
        $arr['type'] = 'xml';
        return $arr;
    }

    //常规结算
    private function release($params, $trdobj, $cfg){

        $param = $this->getParam($params, $cfg);

        $this->pushLog($param, '常规结算', $trdobj);

        try {
            $userId = $param['externalId'];
            $ts = time();
            $betId = $param['roundId'];
            $transactionId = $param['transactionId'];

            $award = Third::transExchangerate($param['real'], 0, 2);

            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            if( !$uinfo ){
                $responseData = [
                    'statusCode' => 4,
                    'real' => 0.00,
                    'statusMessage' => 'WRONGUSERNAMEPASSWORD'
                ];
                return $this->ReturnData($responseData, '<release/>');
            }
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);

            if($betId == 0){//当回合id为0的时候，说明游戏结束，用业务id去查，并且改游戏状态
                $responseData = [
                    'statusCode' => 0,
                    'externalTransactionId' => $transactionId,
                    'real' => Third::transExchangerate($balance, 1, 2)
                ];
                return $this->ReturnData($responseData, '<release/>');
            }else{
                $bet = GameBets::where('gb_sn', '=', $betId)->find();
            }

            if(!$bet){

                $responseData = [
                    'statusCode' => 0,
                    'externalTransactionId' => $transactionId,
                    'real' => Third::transExchangerate($balance, 1, 2)
                ];
                return $this->ReturnData($responseData, '<release/>');
            }

            $gid = $bet->gb_gid;

            $bet->gb_overtime = $ts;



            $bet->gb_winprice += $award;

            $win = $bet->gb_winprice - $bet->gb_price;
            $bet->gb_winlose = $win > 0 ? 3 : 2;

            $bet->gb_trans_no = $transactionId;
            $bet->gb_betinfo = json_encode($params);
            $rlt = $bet->save();
            if( !$rlt ){
                Db::rollback();
                $this->pushLog($params, '修改注单失败', $trdobj);
                $responseData = [
                    'statusCode' => 1,
                    'real' => Third::transExchangerate($balance, 1, 2),
                    'statusMessage' => 'INTERNAL'
                ];
                return $this->ReturnData($responseData, '<release/>');
            }

            if ($award > 0) {
                $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid,'注单结算');
                User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);

            }

            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            // 处理请求并准备返回的数据
            $responseData = [
                'statusCode' => 0,
                'statusMessage' => 'ok',
                'real' => Third::transExchangerate($balance, 1, 2)
            ];

            // 将数据转换为 XML 格式
            $xmlResponse = $this->arrayToXml($responseData,'<release/>');
            $arr['xml'] = $xmlResponse;
            $arr['type'] = 'xml';
            return $arr;

        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'set balance error：'.$e->getMessage(), $trdobj);
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<release/>');
        }
}

//下注
private function reserve($datas, $trdobj, $cfg) {

    $param = $this->getParam($datas, $cfg);

    $this->pushLog($param, '常规下注', $trdobj);
    try {
        $username = $datas['externalId'];
        $betId = $datas['roundId'];
        $gameCode = $datas['gameId'];
        $amount = $datas['real'];
        $transactionId = $datas['transactionId'];
        $amount = Third::transExchangerate($amount, 0, 4);
        $currency = $datas['currency'];
        if ($amount < 0) {
            $this->pushLog($datas, '下注余额为负', $trdobj);
            $responseData = [
                'statusCode' => 7,
                'statusMessage' => 'NOTENOUGHMONEY'
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $username)->find();
        if (!$uinfo) {
            //用户不存在
            $this->pushLog($datas, '用户名不存在', $trdobj);
            $responseData = [
                'statusCode' => 4,
                'statusMessage' => 'WRONGUSERNAMEPASSWORD'
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        if ($amount > $balance) {
            $this->pushLog($datas, '用户余额不足', $trdobj);
            $responseData = [
                'statusCode' => 7,
                'statusMessage' => 'NOTENOUGHMONEY',
                'real' => Third::transExchangerate($balance, 1, 2)
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        //判断注单是否存在,存在直接返回
        $bet = GameBets::where('gb_sn', '=', $betId)->find();
        if ($bet) {
            $responseData = [
                'statusCode' => 0,
                'externalTransactionId' => $transactionId,
                'real' => Third::transExchangerate($balance, 1, 2),
                'statusMessage' => 'OK'
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'tag', 'g_id');
        if(!$gid){
            $responseData = [
                'statusCode' => 0,
                'externalTransactionId' => $transactionId,
                'real' => Third::transExchangerate($balance, 1, 2),
                'statusMessage' => 'ok'
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        $ts = time();
        $data = [
            'gb_gid' => $gid,
            'gb_cusfield'=>$datas['roundId'],
            'gb_tcid' => $cfg['trdcfg']['tc_id'],
            'gb_uid' => $uinfo[Meta::UFIELD['uid']],
            'gb_intime' => $ts,
            'gb_price' => $amount,
            'gb_sn' => $betId,
            'gb_winlose' => 1,
            'gb_bettime' => $ts,
            'gb_overtime' => null,
            'gb_winprice' => 0,
            'gb_betinfo' => json_encode($datas)
        ];
    } catch (\Exception $e) {
        $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
        $responseData = [
            'statusCode' => 1,
            'statusMessage' => 'INTERNAL'
        ];
        return $this->ReturnData($responseData, '<reserve/>');
    }
    Db::startTrans();
    try {
        //扣减用户余额
        $msg = UserModel::betRemarks($betId, $cfg['trdcfg']['tc_name'], $gid);
        $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

        //写入注单
        $data['gb_cjprice'] = $betcjprice;
        $res = (new GameBets())->insertGetId($data);
        if (!$res) {
            Db::rollback();
            $this->pushLog($datas, '写入注单错误', $trdobj);
            $responseData = [
                'statusCode' => 1,
                'statusMessage' => 'INTERNAL'
            ];
            return $this->ReturnData($responseData, '<reserve/>');
        }
        $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
        Db::commit();
        // 处理请求并准备返回的数据
        $responseData = [
            'statusCode' => 0,
            'statusMessage' => 'ok',
            'real' => Third::transExchangerate($balance, 1, 2)
        ];

        // 将数据转换为 XML 格式
        $xmlResponse = $this->arrayToXml($responseData,'<reserve/>');
        $arr['xml'] = $xmlResponse;
        $arr['type'] = 'xml';
        return $arr;

    } catch (\Exception $e) {
        Db::rollback();
        $this->pushLog($datas, $e->getMessage(), $trdobj, 'error');
        $responseData = [
            'statusCode' => 1,
            'statusMessage' => 'INTERNAL'
        ];
        return $this->ReturnData($responseData, '<reserve/>');
    }
}


/**
 * @param string $code
 * @param string $msg
 * @param array $data
 * @return array
 */
private function ReturnData(array $responseData = [], string $rootname = '<authenticate/>'): array
{
    // 将数据转换为 XML 格式
    $xmlResponse = $this->arrayToXml($responseData,$rootname);
    $arr['xml'] = $xmlResponse;
    $arr['type'] = 'xml';
    return $arr;
}

//处理params
private function getParam($param, $cfg = null){
    //设置平台id
    if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
        $this->tcid = $cfg['trdcfg']['tc_id'];
    }
    return $param['param']??$param;
}

private function pushLog($action, $msg, $data, $dir = 'notify'){
    Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
}

private function getUserBalance($uid){
    $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
    return $balance;
}
}
