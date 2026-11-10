<?php

namespace app\common\FastGame;

use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\User;
use app\common\model\UserFreebetsModel;
use app\common\model\UserModel;
use app\model\GameBets;
use think\Db;

class Notify_wazdan extends Base\Notify
{
    private $product="WAZDAN";

    /**
     * @inheritDoc
     */
    function notify($datas, $trdobj, $cfg)
    {
        $this->loginfo("notify",[$datas,$cfg]);
        // TODO: Implement notify() method.
    }

    /**
     * 验证用户身份
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return array|int[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function authenticate($datas, $trdobj, $cfg){
        try {
            $auth = $trdobj->CheckSign($cfg,$datas);
            if ($auth) {
                $datas=$datas['param'];
                $uname = $trdobj->utoken($datas['token']);
                if ($uname) {
                    $user = UserModel::where(Meta::UFIELD['uname'], $uname)->find();
                    if ($user && $user->status == 1) {
                        return $this->return([
                            "status" => 0,
                            "user" => [
                                "id" => $user->Meta::UFIELD['uname'],
                                "currency" => $cfg['trdcfg']['tc_currency']
                            ],
                            "funds" => [
                                "balance" => Third::transExchangerate($user->balance, 1),
                                "bonus" => 0
                            ]
                        ]);
                    } else {
                        return $this->return(5);
                    }
                } else {
                    return $this->return(2);
                }
            } else {
                return $this->return(100);
            }
        }catch (\Exception $ex){
            $this->logerr("验证用户身份失败",$datas,$ex);
        }
    }

    function checkUsertoken($datas,$trdobj){
        $uname=$trdobj->utoken($datas['user']['token'],$datas['user']['id']);
        if ($uname) {
            if ($uname==$datas['user']['id']){
                $user=UserModel::where(Meta::UFIELD['uname'],$uname)->where('status',1)->find();
                if ($user && $user->status==1) {
                    return $user;
                }else{
                    return $this->return(5);

                }
            }else{
                return $this->return(100);
            }
        }else{
            return $this->return(2);
        }
    }


    /** 投注
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return int[]
     */
    function getStake($datas, $trdobj, $cfg){
        $this->loginfo("投注",[$datas]);
        $auth = $trdobj->CheckSign($cfg,$datas);
        if (!$auth) {
            return $this->return(100);
        }
        $datas=$datas['param'];
        $user = $this->checkUsertoken($datas, $trdobj);
        if (is_array($user)) {
            $this->logerr("用户验证失败",$datas);
            Db::rollback();
            return $user;
        }

        //验证游戏ID
        $gid=Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$datas['gameId'],'tag','g_id');
        if (!$gid){
            $this->logerr("游戏ID验证失败",$datas);
            Db::rollback();
            return $this->return(100);
        }
        //验证注单
        $bet=(new GameBets())->where('gb_sn',$datas['roundId'])->where('gb_tcid',$cfg['trdcfg']['tc_id'])->find();
        if ($bet){
            Db::rollback();
            return $this->return(0);
        }
        $ts = time();
        Db::startTrans();
        try {
            $amount = Third::transExchangerate($datas['amount'], 0, 4);
            $data = [
                'gb_gid' => $gid,
                //'gb_cusfield'=>$datas['roundId'],
                'gb_tcid' => $cfg['trdcfg']['tc_id'],
                'gb_uid' => $user->id,
                'gb_intime' => $ts,
                'gb_price' => $amount,
                'gb_sn' => $datas['roundId'],
                'gb_winlose' => 1,
                'gb_bettime' => $ts,
                'gb_overtime' => null,
                'gb_winprice' => 0,
                'gb_betinfo' => json_encode($datas)
            ];
            //判断是否免费回合
            $freeRound = $datas['freeRound'];
            if ($freeRound) {
                $data['gb_price'] = 0;
                //核销免费回合
                UserFreebetsModel::VerificationFreeBet($datas['freeRoundInfo']['txId'],$datas['roundId']);
            }else{
                //判断用户余额是否足够
                $ban=UserModel::getBalanceForDoubleWalletByGid($user->id,$cfg['trdcfg']['tc_id'],$gid,$amount,true);
                if (!$ban['ok']) {
                    return $this->return(8);
                }
                //扣减用户余额
                $msg = UserModel::betRemarks($datas['roundId'], $cfg['trdcfg']['tc_name'], $gid);
                $betcjprice = User::accountChange($user->id, $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                //写入注单
                $data['gb_cjprice'] = $betcjprice;
                $res = (new GameBets())->insertGetId($data);
                if (!$res) {
                    Db::rollback();
                    $this->logerr('写入注单错误',[$datas,$data]);
                    return $this->return(100);
                }
                Db::commit();
                return $this->return(0,[
                    "funds"=>[
                        "balance"=>Third::transExchangerate($ban['vban'],1,4)
                    ]
                ]);
            }
            return $this->return(0);
        }catch (\Exception $ex) {
            Db::rollback();
            $this->logerr('投注处理失败', $datas,$ex);
            return $this->return(100);
        }
    }

    /** 回滚
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return int[]
     */
    function rollbackStake($datas, $trdobj, $cfg){
        //由于我方出现投注异常情况下并不会写入注单，因此不需要处理回滚
        return $this->return(0);
    }

    /** 游戏结束
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return int[]
     */
    function gameClose($datas, $trdobj, $cfg){
        //游戏结束不需要处理，但有必要的话，可以在这里清除用户TOKEN
        return $this->return(0);
    }


    /** 结算
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return int[]
     */
    function returnWin($datas, $trdobj, $cfg){
        $this->loginfo("结算",[$datas]);
        $auth = $trdobj->CheckSign($cfg,$datas);
        if (!$auth) {
            return $this->return(100);
        }
        $datas=$datas['param'];
        $user = $this->checkUsertoken($datas, $trdobj);
        if (is_array($user)) {
            $this->logerr("用户验证失败",$datas);
            Db::rollback();
            return $user;
        }

        //验证游戏ID
        $gid=Game::getGamesInfoBy($cfg['trdcfg']['tc_id'],$datas['gameId'],'tag','g_id');
        if (!$gid){
            $this->logerr("游戏ID验证失败",$datas);
            Db::rollback();
            return $this->return(100);
        }
        //验证注单
        $bet=(new GameBets())->where('gb_sn',$datas['roundId'])->where('gb_tcid',$cfg['trdcfg']['tc_id'])->find();
        if (!$bet){
            $this->logerr('不存在的注单',$datas);
            return $this->return(0);
        }
        $ts = time();
        Db::startTrans();
        try {
            $amount = Third::transExchangerate($datas['amount'], 1, 4);
            if ($amount>0) {
                $msg = UserModel::betRemarks($datas['roundId'], $cfg['trdcfg']['tc_name'], $gid,'结算');
                $wincj = User::accountChange($user->id, $amount, '+', 2,$msg,true, $cfg['trdcfg']['tc_id'], $gid,$bet->gb_id,$bet->gb_price,$bet->gb_cjprice);
                $bet->gb_wincjprice = $wincj;
            }
            $bet->gb_winlose=$amount > 0 ? 3 : 2;
            $bet->gb_overtime = $ts;
            $bet->gb_trans_no = $datas['transactionId'];
            $bet->save();
            Db::commit();
            return $this->return(0);
        }catch (\Exception $ex) {
            Db::rollback();
            $this->logerr('投注结算处理失败', $datas,$ex);
            return $this->return(100);
        }
    }

    /** 获取余额
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return int[]
     */
    function getFunds($datas, $trdobj, $cfg){
        $user = $this->checkUsertoken($datas, $trdobj);
        if (is_array($user)) {
            $this->logerr("用户验证失败",$datas);
            Db::rollback();
            return $user;
        }
        $ban=UserModel::getBalanceForDoubleWalletByGid($user->id,$cfg['trdcfg']['tc_id']);
        return $this->return(0,[
            "funds"=>[
                "balance"=>Third::transExchangerate($ban,1,4)
            ]
        ]);
    }

    function return($code,$data=[]){
        if (!is_array($code)) {
            $data['code'] = $code;
        }
        $this->loginfo("传达三方",$data);
        return $data;
    }

}