<?php
namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;
use app\common\lib\Util;

class Notify_cmd extends Notify
{

    private $game_code = 'cmd';


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
        $datas = $datas['param'];
        if(isset($datas['method'])){
            $Method = $datas['method'];
            $balancePackage = $this->decode($datas['balancePackage'],$cfg);  //解密数据
            $datas['decode_balancePackage'] = $balancePackage;
            Utils::L('notify', ['cmd_data',$datas]);
            unset($datas['decode_balancePackage']);
            $datas['balancePackage'] = $balancePackage;
            if(!$datas['balancePackage']){
                Utils::L('notify', ['cmd_data_error',$datas]);
                return false;
            }
            $model = self::$Method($datas,$trdobj,$cfg);
            return ['return_type'=>'plain','data'=>$model];
        }else{
            if(!isset($datas['token'])){
                Utils::L('notify', ['cmd_token_error',$datas]);
                return 'failed';
            }
            return $trdobj->CheckSign($cfg,$datas);
        }
    }

    //数据加密
    private function encode($data,$config)
    {
        $tc_api_signkey = $config['trdcfg']['tc_api_signkey'];
        $balancePackage = openssl_encrypt(json_encode($data), 'AES-128-CBC',$tc_api_signkey, 0, strrev($tc_api_signkey));
        return $balancePackage;
    }

    //数据解密
    private function decode($data,$config){
        $data = str_replace(' ', '+', rawurldecode($data));
        $tc_api_signkey = $config['trdcfg']['tc_api_signkey'];
        $json = openssl_decrypt($data,'AES-128-CBC',$tc_api_signkey,0,strrev($tc_api_signkey));
        return json_decode($json,true);
    }

    /**
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $StatusCode = 100, $StatusMessage = '',  $PackageId = '', $Balance = 0, $cfg , $show = 1)
    {
        $arr = [];
        $time = intval(time() * 10000000 + 621355968000000000);
        $arr['StatusCode'] = $StatusCode;
        $arr['StatusMessage'] = $StatusMessage;
        $arr['PackageId'] = $PackageId;
        if($show == 1){
            $arr['Balance'] = floatval($Balance);
        }
        $arr['DateReceived'] = $time;
        $arr['DateSent'] = $time;
        $encode = $this->encode($arr,$cfg);
        Utils::L('notify', ['cmd_ReturnData',$arr]);
        return $encode;
    }

    private function getUserBalance($uid, $cfg){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }


    //查询余额
    private function GetBalance($data, $trdobj, $cfg)
    {
        $SourceName = isset($data['balancePackage']['SourceName']) ? $data['balancePackage']['SourceName']:'';
        $code = 100; $msg = 'success';
        try {
            $uid =  UserModel::where(Meta::UFIELD['uname'],$SourceName)->value(Meta::UFIELD['uid']);
            $balance = $this->getUserBalance($uid);
        } catch (\Throwable $e) {
            $code = 1; $msg = 'failed';
        }
        return $this->ReturnData($code, $msg,$data['packageId'] ,$balance,$cfg);
    }

    //投注
    private function DeductBalance($data, $trdobj, $cfg)
    {
        $da = $data['balancePackage'];
        $log_key = 'cmd_DeductBalance_return';
        $redis_key = 'cmd_DeductBalance_';
        $SourceName = isset($da['SourceName']) ? $da['SourceName']:'';
        Db::startTrans();
        try {
        $user = UserModel::where(Meta::UFIELD['uname'],$SourceName)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
        $ReferenceNo = $da['ReferenceNo']; //注单号
        $money = trim($da['TransactionAmount'],'-'); //交易金额
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
        $aflag = Redis::get($redis_key . $gid . "_" . $ReferenceNo);
        $userBalance = $this->getUserBalance($user[Meta::UFIELD['uid']]);
         if(!$aflag) {
             Redis::setex($redis_key . $gid . "_" . $ReferenceNo, 1, 60);//60秒内不接受重复得action
             $amount = Third::transExchangerate($money,0);  //金额转换
             $data['SocTransId'] = "0";
             $bets_info = [
                 'gb_tcid' => $cfg['trdcfg']['tc_id'],
                 'gb_gid' => $gid ??0,
                 'gb_uid' => $user['id'],
                 'gb_intime' => time(),
                 'gb_price' => $amount,
                 'gb_sn' => $ReferenceNo,
                 'gb_winlose' => 1,
                 'gb_bettime' => time(),
                 'gb_betinfo' => json_encode($data),
             ];
             $msg = UserModel::betRemarks($ReferenceNo, $cfg['trdcfg']['tc_name'], $gid);
             if ($amount < $userBalance) {
                 $betcjprice = User::accountChange($user[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                 $bets_info['gb_cjprice'] = $betcjprice;
                 (new GameBets())->insert($bets_info);
                 Utils::L('notify', [$log_key,'投注成功',$data]);
                 $code = 100; $msg = 'Deduct Balance Succeed';
                 Db::commit();
             }else{
                 Utils::L('notify', [$log_key,'投注失败,余额不足',$data]);
                 $code = -1;
                 $msg = '投注失败，该账号余额不足';
                 Db::rollback();
             }
         }else{
             $code = 1; $msg = '订单重复请求';
             Utils::L('notify', [$log_key,'订单重复请求',$data]);
         }
        } catch (\Throwable $e) {
            Utils::L('notify', [$log_key,'注单错误',$e->getMessage()]);
            $code = 1; $msg = '注单错误';
        }
        return $this->ReturnData($code, $msg,$data['packageId'] ,$this->getUserBalance($user[Meta::UFIELD['uid']]) ??0,$cfg);
    }

    //投注结果
    private function UpdateBalance($data, $trdobj, $cfg)
    {
        $this->cash($data, $cfg);
        $da = $data['balancePackage'];
        $code = 100;$msg = 'Update Balance Succeed'; $log_key = 'cmd_UpdateBalance_return';
        $redis_key = 'cmd_UpdateBalance_';
        $where = [];
        Db::startTrans();
        try {
            if(!isset($da['TicketDetails'])) {
                $da['TicketDetails'][] = $da;
            }
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
                foreach ($da['TicketDetails'] as $v) {
                    $SourceName = isset($v['SourceName']) ? $v['SourceName'] : '';
                    $user = UserModel::where(Meta::UFIELD['uname'], $SourceName)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
                    $aflag = Redis::get($redis_key . $gid . "_" . $v['ReferenceNo'] . $user->id);
                    if ($user) {
                        if (!$aflag) {
                            Redis::setex($redis_key . $gid . "_" . $v['ReferenceNo'] . $user->id, 1, 30);
                            if($da['ActionId'] == 3001){
                                $bid = $this->cashoutbetrecord($user->id,$gid,$v['ReferenceNo'],$cfg);
                                $where = ['gb_id' => $bid];
                            }else{
                                $where = ['gb_gid' => $gid, 'gb_uid' => $user->id,'gb_sn' => $v['ReferenceNo']];
                            }
                            $bet = GameBets::where($where)->find();
                            if ($bet) {
                                $ms = '结算';
                                if ($bet->gb_winlose > 1 || $bet->gb_winlose == 0) {
                                    $bet->gb_cusfield = json_encode([
                                        'gb_winlose' => $bet->gb_winlose,
                                        'gb_overtime' => $bet->gb_overtime,
                                        'gb_winprice' => $bet->gb_winprice,
                                    ]);
                                    $ms = '重新结算';
                                }
                                if ($v['TransactionAmount'] > 0) {
                                    $money = $v['TransactionAmount'];
                                } else {
                                    $money = trim($v['TransactionAmount'], '-');
                                }
                                $amount = Third::transExchangerate($money, 0);  //金额转换
                                $bet->gb_winlose = $amount > 0 ? 3 : 2;
                                $operation = '+';
                                if ($bet->gb_winlose == 2 && $amount < $user[Meta::UFIELD['ubalance']]) {
                                    $operation = '-';
                                }
                                $msg = UserModel::betRemarks($v['ReferenceNo'], $cfg['trdcfg']['tc_name'], $gid, $ms);
                                if( $operation == '+' ){
                                    User::accountChange($user[Meta::UFIELD['uid']], $amount, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $bet->gb_id, $bet->gb_price, $bet->gb_cjprice);
                                }else{
                                    $betcjprice = User::accountChange($user[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    $bet->gb_cjprice = $betcjprice;
                                }
                                $bet->gb_overtime = time();
                                $bet->gb_winprice = $amount;
                                $bet->gb_betinfo = json_encode($data);
                                $bet->save();
                                Db::commit();
                            } else {
                                Utils::L('notify', [$log_key, '订单不存在']);
                                Db::rollback();
                                $code = 1;
                                $msg = '订单不存在';
                            }
                        } else {
                            $code = 1;
                            $msg = '订单请求频繁';
                            Utils::L('notify', [$log_key, '订单请求频繁', $data]);
                        }
                    } else {
                        Utils::L('notify', [$log_key, '用户不存在']);
                        Db::rollback();
                        $code = 1;
                        $msg = '用户不存在';
                    }
                }
        } catch (\Throwable $e) {
            Utils::L('notify', [$log_key,'更新余额错误',$e->getMessage()]);
            Db::rollback();
            $code = 1; $msg = '更新余额错误';
        }
        return $this->ReturnData($code, $msg,$data['packageId'] ,0,$cfg,2);
    }


    //获取注单ID
    private function cash($da, $cfg)
    {
        $da = $da['balancePackage'];
        if(!empty($da['TicketDetails'])){
            $da = $da['TicketDetails'][0];
        }
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
        $uid = UserModel::where(Meta::UFIELD['uname'], $da['SourceName'])->lock(true)->value(Meta::UFIELD['uid']);
        $bet = GameBets::where(['gb_gid' => $gid,'gb_uid' => $uid,'gb_winlose'=>1])->where([['gb_betinfo->SocTransId','=','0']])->json(['gb_betinfo'])->column('gb_id,gb_betinfo');
        if($bet){
            foreach ($bet as $v){
                $v['gb_betinfo']['SocTransId'] = $this->GetSocTransId($v['gb_betinfo']['balancePackage']['ReferenceNo'],$cfg);
                GameBets::where(['gb_id'=>$v['gb_id']])->update(['gb_betinfo'=>json_encode($v['gb_betinfo'])]);
            }
        }
    }

    //获取三方数据id
    private function GetSocTransId($no, $cfg){
        $url = $cfg['trdcfg']['tc_api_urlpre'] . '?Method=betrecordbyrefno&PartnerKey=' . $cfg['trdcfg']['tc_api_signkey'] . '&RefNo='.$no;
        $rlt = Util::getRequest($url);
        $SocTransId = "0";
        if(!empty($rlt['Data']) > 0){
            $SocTransId = strval($rlt['Data'][0]['SocTransId']);
        }
        return $SocTransId;
    }

    //获取关联注单id
    private function cashoutbetrecord($uid,$gid,$no,$cfg){
        $bet = GameBets::where(['gb_gid' => $gid,'gb_uid' => $uid])->where([['gb_betinfo->SocTransId','<>','0']])->json(['gb_betinfo'])->column('gb_id,gb_betinfo');
        $bid = 0;
        if($bet){
            foreach ($bet as $v){
                $SocTransId = $v['gb_betinfo']['SocTransId'];
                $url = $cfg['trdcfg']['tc_api_urlpre'] . '?Method=cashoutbetrecord&PartnerKey=' . $cfg['trdcfg']['tc_api_signkey'] . '&SocTransId='.$SocTransId;
                $rlt = Util::getRequest($url);
                if(!empty($rlt['Data'])) {
                    foreach ($rlt['Data'] as $s) {
                        if ($s['CashOutDangerStatus'] == 'S' && $s['CashOutRefNo'] == $no) {
                            $bid = $v['gb_id'];
                        }
                    }
                }
            }
        }

        return $bid;
    }


}
