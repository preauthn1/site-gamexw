<?php


namespace app\common\FastGame;

use app\common\model\UserModel;
use app\model\GameBetsSon;
use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\model\GameBets;
use think\Exception;
use think\facade\Db;

class Notify_luckysport extends Notify
{
    private $tcid = 0;

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

    //获取用户余额
    public function balance($datas, $trdobj = null, $cfg = null){
        $param = $this->getParam($datas, $cfg);
        $this->pushLog($param, 'balance', $trdobj);
        $returnData = [];
        try {
            //验证token
            $this->checkToken($param);
            $userId = $param['user_id'];
            //获取用户数据
            $userDetail = $this->getUserDetail($userId);
            $userBalance = $this->getUserBalance($userDetail[Meta::UFIELD['uid']]);
            $returnData['balance'] = $userBalance;
            return $returnData;
        }catch (\Exception $e){
            $this->pushLog($param, 'balance error：'.$e->getMessage(), [$datas, $trdobj], 'error');
        }
    }

    //内转
    public function exchange($datas, $trdobj = null, $cfg = null){
        $url = request()->url();
        $params = $datas['param'];
        $action = substr($url, $lastSlashPosition = strrpos($url, '/') + 1).'Exchange';
        $this->pushLog($params, $url, $trdobj);
        return $this->$action($params, $trdobj, $cfg);
//        switch ($action){
//            case 'make':
//                $this->make($params, $trdobj, $cfg);
//                break;
//
//        }
//        dd($action);

    }

    //下注相关
    public function bet($datas, $trdobj = null, $cfg = null){
        $url = request()->url();
        $params = $datas['param'];
        $action = substr($url, $lastSlashPosition = strrpos($url, '/') + 1);
        $this->pushLog($params, $url, $trdobj);
        return $this->$action($params, $trdobj, $cfg);
//        switch ($action){
//            case 'make':
//                //下注
//                return $this->make($params, $trdobj, $cfg);
//            case 'lost':
//                //输
//                return $this->lose($params, $trdobj, $cfg);
//            case 'win':
//                //赢
//                return $this->win($params, $trdobj, $cfg);
//            case 'rollback':
//                //重新标记订单’
//                return $this->rollback($params, $trdobj, $cfg);
//
//        }
    }

    //exchange payout
    public function payout($params, $trdobj = null, $cfg = null){
        return $this->win($params, $trdobj, $cfg);
    }

    //退款
    public function refund($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['selections'][0]['betslip_id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];
        Db::startTrans();
        try {
            $roundId = $params['selections'][0]['betslip_id'];
            $winAmount = round($params['transaction']['amount'] / 100, 4);
            $winAmount = Third::transExchangerate($winAmount, 0, 2);

            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);
            if( !$uinfo ){
                Db::rollback();
                $returnData['code'] = 1006;
                $returnData['httpcode'] = 400;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }

            //获取注单
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3003;
                $returnData['message'] = 'asked order not exist when settle request';
                return $returnData;
            }
            $ts=time();
            $update = [
                'gb_winlose' => -1,
                'gb_overtime' => $ts,
                'gb_betinfo' => json_encode($params),
            ];
            //修改订单已结算为赢
            $res = $bet->save($update);
            if( !$res ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $this->pushLog($params, 'order update error', $trdobj, 'error');
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                return $returnData;
            }

            if( $winAmount > 0 ){
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid, '退款');
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $winAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid);
                (new GameBets())->where('gb_id', '=', $bet->gb_id)->update([
                    'gb_cjprice' => $betcjprice
                ]);
            }elseif ( $winAmount < 0 ){
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid, '退款');
                User::accountChange($uinfo[Meta::UFIELD['uid']], $winAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            }

            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'win error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }
    }

    //回滚
    public function rollback($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['selections'][0]['betslip_id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        Db::startTrans();
        try {
            $roundId = $params['selections'][0]['betslip_id'];
            $winAmount = abs(round($params['transaction']['amount'] / 100, 4));
            $winAmount = Third::transExchangerate($winAmount, 0, 2);

            $status = $params['selections'][0]['status']; //状态（open 下单，win赢钱，lose输钱）

            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);
            if( !$uinfo ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 1006;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }

            //获取注单
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3003;
                $returnData['message'] = 'asked order not exist when settle request';
                return $returnData;
            }
            $ts=time();
            if( $status == 'open' ){
                $update = [
                    'gb_price' => $winAmount,
                    'gb_winlose' => 0,
                    'gb_winprice' => 0,
                    'gb_betinfo' => json_encode($params),
                ];
            }elseif ( $status == 'win' ){
                $update = [
                    'gb_winlose' => 3,
                    'gb_winprice' => $winAmount,
                    'gb_overtime' => $ts,
                    'gb_betinfo' => json_encode($params),
                ];
            }elseif ( $status == 'lose' ){
                $update = [
                    'gb_winlose' => 2,
                    'gb_overtime' => $ts,
                    'gb_betinfo' => json_encode($params),
                ];
            }else{
                Db::rollback();
                $returnData['httpcode'] = 400;
                $this->pushLog($params, 'bad request', $trdobj, 'error');
                $returnData['code'] = 2004;
                $returnData['message'] = 'bad request';
                return $returnData;
            }
            //修改订单已结算为赢
            $res = $bet->save($update);
            if( !$res ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $this->pushLog($params, 'order update error', $trdobj, 'error');
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                return $returnData;
            }

            if( $status == 'open' ){
                //写入下注信息
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid);
                $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $winAmount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid);
                (new GameBets())->where('gb_id', '=', $bet->gb_id)->update([
                    'gb_cjprice' => $betcjprice
                ]);
            }else if( $status == 'win' ){
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid, '结算');
                User::accountChange($uinfo[Meta::UFIELD['uid']], $winAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            }

            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'win error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }
    }

    //赢
    public function win($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['selections'][0]['betslip_id']??$params['selections']['betslip_id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        Db::startTrans();
        try {
            $roundId = $params['transaction']['betslip_id'];
            $winAmount = round($params['transaction']['amount'] / 100, 4) * -1;#TODO 三方小于0为加用户余额，大于0为减少用户余额
            $winAmount = Third::transExchangerate($winAmount, 0, 2);
            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);
            if( !$uinfo ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 1006;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }

            //获取注单
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            //TODO 这里不能加未结算状态，三方可能重复请求赢的状态，第二笔才真正结算
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3003;
                $returnData['message'] = 'asked order not exist when settle request';
                return $returnData;
            }
            $ts=time();
            //修改订单已结算为赢
            $res = $bet->save([
                'gb_winlose' => 3,
                'gb_winprice' => $winAmount,
                'gb_overtime' => $ts,
                'gb_betinfo' => json_encode($params),
            ]);
            if( !$res ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $this->pushLog($params, 'order update error', $trdobj, 'error');
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                return $returnData;
            }

            if( $winAmount != 0 ){
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $winAmount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            }

            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'win error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }
    }

    public function lost($params, $trdobj = null, $cfg = null){
        return $this->lose($params, $trdobj, $cfg);
    }

    //输
    public function lose($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['selections'][0]['betslip_id']??$params['selections']['betslip_id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        try {
            $roundId = $params['transaction']['betslip_id'];
            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);
            if( !$uinfo ){
                $returnData['code'] = 1006;
                $returnData['httpcode'] = 400;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }

            //获取注单
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            //TODO 这里不能加未结算状态，三方可能重复请求，第二笔才真正结算
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3003;
                $returnData['message'] = 'asked order not exist when settle request';
                return $returnData;
            }
            $ts=time();
            //修改订单已结算为输，输了不用给用户加钱
            $res = $bet->save([
                'gb_winlose' => 2,
                'gb_overtime' => $ts,
                'gb_betinfo' => json_encode($params),
            ]);
            if( !$res ){
                $returnData['httpcode'] = 400;
                $this->pushLog($params, 'order update error', $trdobj, 'error');
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                return $returnData;
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            return $returnData;
        }catch (\Exception $e){
            $this->pushLog($params, 'lose error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }
    }

    //下注
    private function make($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['betslip']['id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        Db::startTrans();
        try {
            $gameId = $params['betslip']['bets'][0]['sport_id'];
            $roundId = $params['betslip']['id'];
            $amount = abs(round($params['transaction']['amount'] / 100, 4));#TODO 三方小于0为加用户余额，大于0为减少用户余额
            $amount = Third::transExchangerate($amount, 0, 2);
            $orderAmount = Third::transExchangerate($params['betslip']['sum'], 0, 2);
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->handleGameId($gameId), 'code', 'g_id');
            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);

            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 1006;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }
            if( $amount > $userBalance ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 2001;#用户余额不足
                $returnData['message'] = 'not enough money';#用户余额不足
                $this->pushLog($params, '用户余额不足', [$params, $trdobj], 'error');
                return $returnData;
            }
            //判断订单是否存在
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            $bet = GameBets::where($condition)->find();
            if( $bet ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3002;
                $returnData['message'] = 'order already exist';
                return $returnData;
            }
            $ts=time();
            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$orderAmount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> 1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=> 0,
                'gb_betinfo'=>json_encode($params)
            ];
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if( !$rlt ){
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                Db::rollback();
                $returnData['httpcode'] = 400;
                return $returnData;
            }
            //写入下注信息
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            (new GameBets())->where('gb_id', '=', $rlt)->update([
                'gb_cjprice' => $betcjprice
            ]);
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'make error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }

    }



    //payout取出（exchange专用）
    public function payoutExchange($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['transaction']['betslip_id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        Db::startTrans();
        try {
            $mainRoundId = $returnData['id'];
            $amount = abs(round($params['transaction']['amount'] / 100, 4));#TODO 三方小于0为加用户余额，大于0为减少用户余额
            $amount = Third::transExchangerate($amount, 0, 2);
            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);
            if( !$uinfo ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 1006;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }
            //获取主订单信息
            $condition = [];
            $condition[] = ['gb_sn', '=', $mainRoundId];
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3003;
                $returnData['message'] = 'asked order not exist when settle request';
                return $returnData;
            }
            $ts=time();
            //修改主订单信息
            $rlt = $bet->save([
                'gb_winlose' => $amount > 0 ? 3 : 2,
                'gb_winprice' => $amount,
                'gb_overtime' => $ts,
                'gb_betinfo' => json_encode($params),
            ]);

            if( $amount > 0 ){
                $msg = UserModel::betRemarks($mainRoundId, $cfg['trdcfg']['tc_name'], $bet->gb_gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $bet->gb_gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
            }
            //批量更改子订单信息
            foreach ($params['selections'] as $selections){
                $roundId = $selections['bet_id'];
                //获取订单信息
                $condition = [];
                $condition[] = ['gb_sn', '=', $roundId];
                $betSon = GameBetsSon::where($condition)->find();
                if( !$betSon ){
                    Db::rollback();
                    $returnData['httpcode'] = 400;
                    $returnData['code'] = 3003;
                    $returnData['message'] = 'asked order not exist when settle request';
                    return $returnData;
                }
                //profit为输赢，派彩金额为下注金额加上输赢金额
                $profit = Third::transExchangerate($selections['profit'], 0, 2);
                $winAmount = $betSon->gb_price + $profit;

                $betSon->save([
                    'gb_winlose' => $winAmount > 0 ? 3 : 2,
                    'gb_winprice' => $winAmount,
                    'gb_overtime' => $ts,
                    'gb_betinfo' => json_encode($params),
                ]);
            }

            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'win error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }
    }

    //下注（exchange专用）
    private function makeExchange($params, $trdobj = null, $cfg = null){
        $params = $this->getParam($params, $cfg);
        $returnData = [
            'id' => $params['betslip']['id'],//合作伙伴指定的交易的唯一标识符。
            'ext_transaction_id' => $params['transaction']['id'], //LuckySports 分配的交易的唯一标识符。
            'user_id' => $params['transaction']['ext_player_id'],//合作方对玩家的唯一标识
            'operation' => $params['transaction']['operation'],//该交易代表的操作，操作列表在本文档的其他部分提供，参考操作代码
            'amount' => $params['transaction']['amount'],//
            'currency' => $cfg['trdcfg']['tc_currency'], //玩家使用的货币
            'balance' => null,//th
        ];

        Db::startTrans();
        try {
            $gameId = $params['betslip']['bets'][0]['sport_id'];
            $roundId = $params['betslip']['bets'][0]['id'];
            $mainRoundId = $params['betslip']['id'];
            $amount = abs(round($params['transaction']['amount'] / 100, 4));#TODO 三方小于0为加用户余额，大于0为减少用户余额
            $amount = Third::transExchangerate($amount, 0, 2);
            $orderAmount = Third::transExchangerate($params['betslip']['sum'], 0, 2);
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'code', 'g_id');
            //获取用户数据
            $uinfo = $this->getUserDetail($params['transaction']['ext_player_id']);

            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 1006;
                $returnData['message'] = 'player not found';
                $this->pushLog($params, '用户不存在', [$params, $trdobj], 'error');
                return $returnData;
            }
            if( $amount > $userBalance ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 2001;#用户余额不足
                $returnData['message'] = 'not enough money';#用户余额不足
                $this->pushLog($params, '用户余额不足', [$params, $trdobj], 'error');
                return $returnData;
            }
            //操作子订单
            //判断订单是否存在
            $condition = [];
            $condition[] = ['gb_sn', '=', $roundId];
            $betSon = GameBetsSon::where($condition)->find();
            if( $betSon ){
                Db::rollback();
                $returnData['httpcode'] = 400;
                $returnData['code'] = 3002;
                $returnData['message'] = 'order already exist';
                return $returnData;
            }
            $ts=time();
            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$orderAmount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> 1,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_cusfield'=>$params['betslip']['id'],
                'gb_winprice'=> 0,
                'gb_betinfo'=>json_encode($params)
            ];
            //写入注单
            $rlt=(new GameBetsSon())->insertGetId($data);
            if( !$rlt ){
                $returnData['code'] = 9999;
                $returnData['message'] = 'system error';
                Db::rollback();
                $returnData['httpcode'] = 400;
                return $returnData;
            }
            //操作主订单
            $condition = [];
            $condition[] = ['gb_sn', '=', $mainRoundId];
            $condition[] = ['gb_winlose', '=', 1];
            $bet = GameBets::where($condition)->find();
            if( !$bet ){
                //主订单不存在写入
                //下分（下注）
                $data = [
                    'gb_gid'=>$gid,
                    'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                    'gb_intime'=>$ts,
                    'gb_price'=>$orderAmount,
                    'gb_sn'=>$mainRoundId,
                    'gb_winlose'=> 1,
                    'gb_bettime'=>$ts,
                    'gb_overtime'=>$ts,
                    'gb_cusfield'=>$roundId,
                    'gb_winprice'=> 0,
                    'gb_betinfo'=>json_encode($params)
                ];
                //写入注单
                $rlt=(new GameBets())->insertGetId($data);
                if( !$rlt ){
                    $returnData['code'] = 9999;
                    $returnData['message'] = 'system error';
                    Db::rollback();
                    $returnData['httpcode'] = 400;
                    return $returnData;
                }
                $bet = GameBets::where('gb_id', '=', $rlt)->find();
            }else{
                //存在则修改
                $bet->gb_price += $orderAmount;
                $bet->gb_betinfo = json_encode($params);
                $rlt = $bet->save();
                if( !$rlt ){
                    $returnData['code'] = 9999;
                    $returnData['message'] = 'system error';
                    Db::rollback();
                    $returnData['httpcode'] = 400;
                    return $returnData;
                }
            }


            //写入下注信息
            $msg = UserModel::betRemarks($mainRoundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            $bet->save([
                'gb_cjprice' => $bet->gb_cjprice + $betcjprice
            ]);
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['balance'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $returnData['httpcode'] = 400;
            $this->pushLog($params, 'make error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['message'] = 'system error';
            return $returnData;
        }

    }

    //处理游戏id
    private function handleGameId($gameId){
        return $gameId;
        if( is_numeric($gameId) ){
            return $gameId;
        }
        //有些格式为sr:sport:117，只需要最后面数字
        $arr = explode(':', $gameId);
        if( isset($arr[count($arr) - 1]) ){
            return $arr[count($arr) - 1];
        }
        return $gameId;
    }

    //验证token 签名需要三方后台配置，暂不设置
    private function checkToken($params){
        if(!$params){
            throw new Exception('参数不存在');
        }
        return true;
    }

    //获取用户数据
    private function getUserDetail($userId){
        return UserModel::where('rec_code', '=', $userId)->find();
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
        return round($balance, 4) * 100;
    }
}
