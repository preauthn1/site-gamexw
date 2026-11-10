<?php


namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\model\GameBetsSon;
use think\facade\Db;

class Notify_gb extends Notify
{
    private $tcid;

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @throws \Exception
     * @author twt326
     * @date 2022-10-04 18:33
     */
    public function notify($datas, $trdobj,$cfg)
    {
        $param = $datas['param'];
        $this->tcid = $cfg['trdcfg']['tc_id'];
        // 判断有没有 sign 参数
        if (!isset($param['sign'])){
            $this->PushLog($param['action'], '缺少参数 sign', $datas);
            return $this->ReturnData(9999, '缺少参数 sign');
        }
        // 校验签名
        $sign_flag = $trdobj->CheckSign($cfg, $datas);
        if (!$sign_flag) {
            $this->PushLog($param['action'], '签名错误', $datas);
            return $this->ReturnData(207, '签名错误');
        }
        switch((int)$param['action']) {
            case 1: // 查询余额
                $uinfo = UserModel::where(Meta::UFIELD['uname'], $param['uid'])->find();
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                return $this->ReturnData(0, '', $balance);
            case 2: // 结算
                return $this->settlement($param, $trdobj, $cfg);
            case 3: // 取消结算
                return $this->cancelSettlement($param, $trdobj, $cfg);
            case 4: // 游戏离线通知
                break;
            default:
                $this->PushLog($param['action'], '未知操作', $datas);
                return $this->ReturnData(9999, '未知操作');
        }
    }


    /**
     * 下注/结算
     * 当玩家游戏下注时呼叫该 API，通知该局游戏结果。
     * 当玩家参与活动时呼叫该 API，通知本次活动结果。
     * 确认玩家投注余额是否足够，根据总输赢进行加/减款后，回传玩家剩余金额。
     * 检查方式：余额是否大于投注(bet 参数)
     * @param $params
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function settlement($params, $trdobj = null, $cfg = null): array
    {
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'settlement', $trdobj);

        try {
            $roundId = $param['orderNo'];
            $amount = $param['bet'];
            $amount = Third::transExchangerate($amount, 0, 2);
            $award = $param['win'];
            $award = Third::transExchangerate($award, 0, 2);


            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $param['uid'])->find();

            $gameCode = $param['gameCode'];
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameCode, 'code', 'g_id');

            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                return $this->ReturnData(101, '用户不存在');
            }
            if( $amount > $userBalance ){
                return $this->ReturnData(106, '余额不足');
            }

            //获取注单数据,并判断是否重复下单 订单号重复:102
            $bet = GameBetsSon::where('gb_sn', $roundId)->find();
            if( $bet && $amount > 0 ){
                return $this->ReturnData(102, '订单号重复');
            }

            $ts=time();
            //下分（下注）
            $data = [
                'gb_gid'=>$gid,
                'gb_tcid'=>$cfg['trdcfg']['tc_id'],
                'gb_uid'=>$uinfo[Meta::UFIELD['uid']],
                'gb_intime'=>$ts,
                'gb_price'=>$amount,
                'gb_sn'=>$roundId,
                'gb_winlose'=> $award > 0 ? 3 : 2,
                'gb_bettime'=>$ts,
                'gb_overtime'=>$ts,
                'gb_winprice'=> $award,
                'gb_betinfo'=>json_encode($param)
            ];
        }catch (\Exception $e){
            $this->pushLog($param, 'settlement error：'.$e->getMessage(), $trdobj);
            $returnData['error'] = 3033;
            return $returnData;
        }
        Db::startTrans();
        try {
            if ($bet) {
                // 更新
                $updateData = [
                    'gb_winlose'    => $amount - $award > 0 ? 3 : 2,
                    'gb_winprice'   => $award,
                    'gb_overtime'   => $ts,
                    'gb_betinfo'    => json_encode($param)
                ];
                GameBetsSon::where('gb_sn', $bet['gb_sn'])->update($updateData);
            } else {
                //写入注单
                $rlt=(new GameBetsSon())->insertGetId($data);
                if( !$rlt ){
                    $returnData['error'] = 3033;
                    Db::rollback();
                    return $returnData;
                }
                $data['gb_id'] = $rlt;
                //写入下注信息
                $betCjPrice = 0;
                if( $amount > 0){
                    $remarks = '下注';
                    $transferGameCode = env('gb_transfer_game_code');
                    if( $transferGameCode ){
                        $transferGameCode = explode(',', $transferGameCode);
                        if($transferGameCode && in_array($gameCode, $transferGameCode) ){
                            $remarks = '充值到三方平台';
                        }
                    }

                    $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, $remarks);
                    $betCjPrice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                }
                $data['gb_cjprice'] = $betCjPrice;
                if( $betCjPrice ){
                    (new GameBetsSon())->where('gb_id', $rlt)->update(['gb_cjprice' => $betCjPrice]);
                }
                $bet = $data;#赋值，下面用到
            }


            if( $award > 0 ){
                $remarks = '结算';
                $transferGameCode = env('gb_transfer_game_code');
                if( $transferGameCode ){
                    $transferGameCode = explode(',', $transferGameCode);
                    if( $transferGameCode && in_array($gameCode, $transferGameCode) ){
                        $remarks = '[' . $cfg['trdcfg']['tc_id'] . ']提现';
                    }
                }
                //结算
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, $remarks);
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet['gb_id'], $bet['gb_price'], $bet['gb_cjprice']);
//                if( !$rlt ){
//                    $returnData['error'] = 3033;
//                    Db::rollback();
//                    return $returnData;
//                }
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            Db::commit();
            return $this->ReturnData(0, '', $balance);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'settlement error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(9999, '系统错误');
        }
    }

    /**
     * 取消结算
     * 当结算请求(2.2)失败时(请求超时/未收到响应)呼叫此 API。
     * 当对方收到[无法取消]的回复时，该笔订单将会放弃取消，并恢复游戏记录
     * 当对方收到[成功]或[订单号不存在]的回复时，该笔订单将会进行取消处理◆ 相同订单号多次请求时，请直接返回状态（幂等）
     * 重试机制：该请求也失败时，我方会进行重试，重试间隔为1 分钟，上限为10次
     * @param $params
     * @param $trdobj
     * @param $cfg
     * @return array
     */
    private function cancelSettlement($params, $trdobj = null, $cfg = null): array
    {
        $param = $this->getParam($params, $cfg);
        $this->pushLog($param, 'refund', $trdobj);
        $roundId = $param['orderNo'];
        //获取用户数据
        $uinfo = UserModel::where(Meta::UFIELD['uname'], $param['uid'])->find();
        if (!$uinfo) {
            $msg = '用户不存在';
            $this->pushLog([$param, $trdobj, $cfg, $uinfo], $msg, $param, 'error');
            return $this->ReturnData(101, $msg);
        }
        //获取注单
        $bet = GameBetsSon::where('gb_sn', $roundId)->find();
        if( !$bet ){
            //没有注单
            $msg = '订单号不存在';
            $this->pushLog([$param, $trdobj, $cfg, $uinfo], $msg, $param, 'error');
            return $this->ReturnData(104, $msg);
        }
        //判断是否处理
        if($bet->gb_winlose == 0){
            //已处理
            $msg = '重复取消';
            $this->pushLog([$param, $trdobj, $cfg, $uinfo], $msg, $param, 'error');
            return $this->ReturnData(9999, $msg);
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
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $param['gameCode'], 'code', 'g_id');

            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '退款');
            User::accountChange($uinfo[Meta::UFIELD['uid']], $bet->gb_price, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet['gb_id'], $bet['gb_price'], $bet['gb_cjprice']);
            //给用户发送消息通知
            UserMsgModel::add_msg($uinfo[Meta::UFIELD['uid']], '游戏退款', lang('订单号：').$roundId, 30, $bet->gb_id);
            Db::commit();
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            return $this->ReturnData(0, '', $balance);
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'refund error：'.$e->getMessage(), $trdobj, 'error');
            return $this->ReturnData(9999, '系统错误');
        }
    }

    //处理params
    private function getParam($param, $cfg = null){
        //设置平台id
        if( isset($cfg['trdcfg']['tc_id']) && $cfg['trdcfg']['tc_id'] ){
            $this->tcid = $cfg['trdcfg']['tc_id'];
        }
        return $param['param']??$param;
    }

    /**
     * @param int $code
     * @param string $msg
     * @param string $gold
     * @return array
     */
    private function ReturnData(int $code = 0, string $msg = '', string $gold = ''): array
    {
        $arr['code'] = $code;
        if ($code == 0){
            $arr['gold'] = $gold;
        } else {
            $arr['message'] = $msg;
        }
        return $arr;
    }

    private function pushLog($action, $msg, $data, $dir = 'log'){
        Utils::L($this->tcid.'/'.$dir, [$action, $msg, $data]);
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return round($balance, 4);
    }
}
