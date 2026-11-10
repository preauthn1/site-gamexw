<?php


namespace app\common\FastGame;

use app\common\model\UserModel;
use app\common\FastGame\Base\{Game, Meta, Notify, Third, User, Utils};
use app\model\GameBets;
use think\facade\Db;

class Notify_middle extends Notify
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
        $returnData = [
            'code' => 0,
        ];
        try {
            $accounts = $this->getUserRecCode($param['playername']);
            //验证签名
            if( !$trdobj->CheckSign($cfg, $datas) ){
                $this->pushLog($param, 'sign error：', [$datas, $trdobj], 'error');
                $returnData['code'] = 207;
                return $returnData;
            }

            //获取用户数据
            $userDetail = UserModel::where(Meta::UFIELD['uname'], '=', $accounts)->find();
            $userBalance = $this->getUserBalance($userDetail[Meta::UFIELD['uid']]);
            $returnData['Amount'] = $userBalance;
            return $returnData;
        }catch (\Exception $e){
            $this->pushLog($param, 'balance error：'.$e->getMessage(), [$datas, $trdobj], 'error');
        }
    }

    //游戏返奖
    public function send_result($datas, $trdobj = null, $cfg = null){
        $param = $this->getParam($datas, $cfg);
        $this->pushLog($param, 'result', $trdobj);
        $returnData = [
            'code' => 0,
            'error' => null,
        ];
        try {
            $userId = $this->getUserRecCode($param['playername']);
            $gameId = $param['GameId'];
            $roundId = $param['LogID'];
            $amount = round($param['BetAmount'], 4);
            $amount = Third::transExchangerate($amount, 0, 2);
            $award = round($param['WinAmount'], 4);
            $award = Third::transExchangerate($award, 0, 2);

            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $gameId, 'code', 'g_id');
            //获取用户数据
            $uinfo = UserModel::where(Meta::UFIELD['uname'], '=', $userId)->find();
            $userBalance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            if( !$uinfo ){
                $returnData['code'] = 101;
                $returnData['error'] = '用户不存在';
                $this->pushLog($param, '用户不存在', [$datas, $trdobj], 'error');
                return $returnData;
            }
            if( $amount > $userBalance ){
                $returnData['code'] = 9999;#用户余额不足
                $returnData['error'] = '用户余额不足';#用户余额不足
                $this->pushLog($param, '用户余额不足', [$datas, $trdobj], 'error');
                return $returnData;
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
            $this->pushLog($param, 'result error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['code'] = 9999;
            $returnData['error'] = '系统异常';
            return $returnData;
        }

        Db::startTrans();
        try {
            //写入注单
            $rlt=(new GameBets())->insertGetId($data);
            if( !$rlt ){
                $returnData['code'] = 9999;
                $returnData['error'] = '写入注单错误';
                Db::rollback();
                return $returnData;
            }
            //写入下注信息
            $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid);
            $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
            (new GameBets())->where('gb_id', '=', $rlt)->update([
                'gb_cjprice' => $betcjprice
            ]);
            $data['gb_cjprice'] = $betcjprice;
            if( $award ){
                $msg = UserModel::betRemarks($roundId, $cfg['trdcfg']['tc_name'], $gid, '结算');
                $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $award, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $data['gb_price'], $data['gb_cjprice']);
            }
            //获取用户余额返回
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
            $returnData['Amount'] = $balance;
            Db::commit();
            return $returnData;
        }catch (\Exception $e){
            Db::rollback();
            $this->pushLog($param, 'result error：'.$e->getMessage(), $trdobj, 'error');
            $returnData['error'] = 9999;
            return $returnData;
        }
    }

    //获取用户邀请码
    private function getUserRecCode($playername){
        return explode('_', $playername)[1];
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
        return round($balance, 4);
    }
}
