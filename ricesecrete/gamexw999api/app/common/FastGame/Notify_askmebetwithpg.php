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
use app\common\model\GameBetsModel;
use app\common\model\UserModel;
use app\model\GameAward;
use app\model\GameBets;
use app\model\GameBetsSon;
use app\model\Games;
use app\model\Users;
use app\model\UserThirds;
use think\facade\Config;
use think\facade\Db;

class Notify_askmebetwithpg extends Notify
{
    /*
    INSERT INTO `dq_third_configs` ( `tc_name`, `tc_intime`, `tc_code`, `tc_set_code`, `tc_api_urlpre`, `tc_api_prikey`, `tc_api_signkey`, `tc_tag`, `tc_currency`, `tc_exchangerate`, `tc_token`, `tc_tokentime`, `tc_proxyconfig`, `tc_taskconfig`, `tc_taskvalue`, `tc_langmap`, `tc_status`, `sort`, `icon`, `tc_getbetlisttype`, `tc_useridentify`, `tc_min_bet`, `tc_max_bet`, `country`, `tc_special_status`, `simple_icon`, `tc_timezone`) VALUES
    ('askmebetwithpg', '2024-01-02 01:43:43', 'ASKMEBETWITHPG', NULL, 'https://test.ambsuperapi.com', '24b51db0-bea4-4f8b-a3cb-fa8b76ca21ef', NULL, 'T12test', 'THB', '1.00000000', '', 0, NULL, NULL, NULL, '{\"en\":\"en\",\"th\":\"th\",\"zh\":\"zh\"}', 1, 0, 'logo.jpg', 'bygame', 'default', '0.10', '100000.00', NULL, 0, 'icon.jpg', 0);

    执行完后将下面的36改为插入的id

    INSERT INTO `dq_game_apis` (`ga_tcid`, `ga_gid`, `ga_func`, `ga_route`, `ga_rtype`, `ga_gparam`, `ga_hparam`, `ga_pparam`, `ga_type`, `ga_rltfield`, `ga_keyfield`, `ga_rate`, `ga_needsign`, `ga_taskcfg`, `ga_taskvalue`, `ga_urlpre`) VALUES
    (36, 0, 'GETUSERURL', '/seamless/logIn', 'post', '', '{\"Authorization\":\"{authorization}\"}', '{\"username\":\"{uname}\",\"productId\":\"PGSOFT2\",\"gameCode\":\"{gtag}\",\"isMobileLogin\":true,\"sessionToken\":\"{Token}\",\"currency\":\"{tccurrency}\",\"language\":\"{lang}\"}', 'other', NULL, '{\"rightflag\":{\"key\":\"code\",\"value\":\"0\"},\"tcgameurl\":{\"key\":\"data.url\"},\"tcusername\":{\"key\":\"data.username\"}}', 0, 3, NULL, NULL, NULL),
    (36, 0, 'GETGAMELIST', '/games', 'get', 'productId=PGSOFT2', '{\"Authorization\":\"{authorization}\"}', '{}', 'other', '', '{\"rightflag\":{\"key\":\"code\",\"value\":\"0\"}}', 1, 3, '', '', '');

*/
    private $product="PGSOFT2";

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

    }

    function checksign($trdobj,$datas,$cfg){
        if(!$trdobj->CheckSign($cfg,$datas)) {
            die(json_encode([
                'id'=>$datas['param']['id'],
                'statusCode' => 30001,
                'timestampMillis'=>$this->getMillisecond(),
                'productId'=>$this->product
            ]));
        }
    }


    function checkBalance($datas,$trdobj,$cfg){
        $auth=$trdobj->MakeSign($cfg,$datas)['authorization'];
        $header=['Authorization'=>$auth];
        $p=$datas['param'];
        $user = UserModel::where( Meta::UFIELD['uname'],$p['username'])->where('status',1)->find();
        if ($user) {
        /*if (!isset($datas['header']['Authorization'])){
            $token=$datas['param']['sessionToken'];
            $mytoken=Redis::hget($this->product.'_usertoken',$user->id,2);
            if ($token==$mytoken){
                return [
                    'id'=>$datas['param']['id'],
                    'statusCode' => 0,
                    'timestampMillis'=>$this->getMillisecond(),
                    'productId'=>$this->product
                ];
            }else {
                return [
                    'id'=>$datas['param']['id'],
                    'statusCode' => 30001,
                    'timestampMillis'=>$this->getMillisecond(),
                    'productId'=>$this->product
                ];
            }
        }
        $this->checksign($trdobj,$datas,$cfg);*/



            $token=$trdobj->utoken($user->id);
            if ($token==$p['sessionToken']) {
                $balance = floatval(Third::transExchangerate($user[Meta::UFIELD['ubalance']], 1, 2));
                return [
                    "header"=>$header,
                    "id"=>$p['id'],
                    "statusCode"=>0,
                    "timestampMillis"=>$this->getMillisecond(),
                    "productId"=>$this->product,
                    "currency"=>$cfg['trdcfg']['tc_currency'],
                    "balance"=>$balance,
                    "username"=>$user[Meta::UFIELD['uname']]
                ];
            }else{
                return [
                    "header"=>$header,
                    'id'=>$datas['param']['id'],
                    'statusCode' => 30001,
                    'timestampMillis'=>$this->getMillisecond(),
                    'productId'=>$this->product
                ];
            }
        }else{
            return [
                "header"=>$header,
                'id'=>$datas['param']['id'],
                'statusCode' => 10001,
                'timestampMillis'=>$this->getMillisecond(),
                'productId'=>$this->product
            ];
        }
    }

    function settleBets($datas,$trdobj,$cfg){
        $auth=$trdobj->MakeSign($cfg,$datas)['authorization'];
        $header=['Authorization'=>$auth];
        $ts=time();
        //$this->checksign($trdobj,$datas,$cfg);
        $p=$datas['param'];
        $txns=$p['txns'][0];
        if ($txns['status']=='SETTLED'){

            $betid=$txns['id'];//$txns['dataGetBetDetail']['betId'] txnId 这三个ID都是一样的
            //经观察发现roundId第二段是表示母单 TODO 后面还有两段，要观察
            $betgrpid=explode('-',$txns['roundId'])[1];
            $bettransid=$txns['roundId'];
            Db::startTrans();
            try{
                $user=UserModel::where(Meta::UFIELD['uname'],$p['username'])->where('status',1)->lock(true)->find();
                if (!$user){
                    Db::rollback();
                    return [
                        "header"=>$header,
                        'id'=>$p['id'],
                        'statusCode' => 10001,
                        'timestampMillis'=>$this->getMillisecond(),
                        'productId'=>$this->product];
                }
                $ubalance=$this->getBalance($user[Meta::UFIELD['uid']]);
                //判断定单是否已处理
                $bet=GameBets::where('gb_sn',$betid)->where('gb_tcid',$cfg['trdcfg']['tc_id'])->find();
                if (!$bet){
                    //用户是否存在

                    //余额是否满足
                    $betprice=Third::transExchangerate($txns['betAmount'],0,2);//投注金额
                    $betwinprice=Third::transExchangerate($txns['payoutAmount'],0,2);//派彩金额

                    if ($ubalance<$betprice){//余额不足
                        Db::rollback();
                        return [
                            "header"=>$header,
                            'id'=>$p['id'],
                            'statusCode' => 10002,
                            'timestampMillis'=>$this->getMillisecond(),
                            'productId'=>$this->product
                        ];
                    }
                    //处理注单
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $txns['gameCode'], 'tag', 'g_id');
                    if (!$gid){
                        Db::rollback();
                        return [
                            "header"=>$header,
                            'id'=>$p['id'],
                            'statusCode' => 40003,
                            'timestampMillis'=>$this->getMillisecond(),
                            'productId'=>$this->product
                        ];
                    }
                    $rlt = false;
                    //加减余额
                    $afterbalance=$ubalance;
                    $betcjprice = 0;
                    if ($betprice > 0) {
                        $afterbalance-=$betprice;
                        $msg = UserModel::betRemarks($betid, $cfg['trdcfg']['tc_name'], $gid);
                        $betcjprice = User::accountChange($user->id, $betprice, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                    }

                    $bettime=strtotime($txns['dataGetBetDetail']['date']);
                    $data = [
                        'gb_gid' => $gid,
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_uid' => $user->id,
                        'gb_intime' => $ts,
                        'gb_price' => $betprice,
                        'gb_cjprice' => $betcjprice,
                        'gb_sn' => $betid,
                        'gb_winlose' => $betwinprice > 0 ? 3 : 2,
                        'gb_bettime' => $bettime,
                        'gb_overtime' => $bettime,
                        'gb_winprice' => $betwinprice,
                        'gb_cusfield' => $betgrpid,
                        'gb_trans_no' => $bettransid,
                        'gb_betinfo' => json_encode($datas)
                    ];


                    //写注单
                    $gbid=(new GameBets())->insertGetId($data);
                    if(!$gbid) {
                        Db::rollback();
                        return [
                            "header"=>$header,
                            'id'=>$p['id'],
                            'statusCode' => 50001,
                            'timestampMillis'=>$this->getMillisecond(),
                            'productId'=>$this->product
                        ];
                    }
                    if ($betwinprice > 0) {
                        $afterbalance+=$betwinprice;
                        $msg = UserModel::betRemarks($betid, $cfg['trdcfg']['tc_name'], $gid, '结算');
                        User::accountChange($user->id, $betwinprice, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $gbid, $betprice, $betcjprice);
                    }
                    $beforbalance=Third::transExchangerate($ubalance,1,2);
                    $afterbalance=Third::transExchangerate($afterbalance,1,2);

                    Db::commit();
                    return [
                        "header"=>$header,
                        "id"=>$betid,
                        "statusCode"=> 0,
                        "timestampMillis"=>$this->getMillisecond(),
                        "productId"=>$this->product,
                        "currency"=>$cfg['trdcfg']['tc_currency'],
                        "balanceBefore"=>floatval($beforbalance),
                        "balanceAfter"=>floatval($afterbalance),
                        "username"=>$p['username']
                    ];

                }else {
                    Db::rollback();
                    $beforbalance=Third::transExchangerate($ubalance,1,2);
                    return [
                        "header"=>$header,
                        "id"=>$betid,
                        "statusCode"=> 0,
                        "timestampMillis"=>$this->getMillisecond(),
                        "productId"=>$this->product,
                        "currency"=>$cfg['trdcfg']['tc_currency'],
                        "balanceBefore"=>floatval($beforbalance),
                        "balanceAfter"=>floatval($beforbalance),
                        "username"=>$p['username']

                    ];
                }
            }catch (\Exception $ex){
                Db::rollback();
                Utils::L("{$cfg['trdcfg']['tc_id']}/notify_error",[
                    $datas,
                    $ex->getMessage(),
                    $ex->getTrace()
                ]);
                return [
                    "header"=>$header,
                    "id" => $p['id'],
                    "statusCode" => 50001,
                    "timestampMillis" => $this->getMillisecond(),
                    "productId" => $this->product
                ];
            }
        }/*else{
            return [
                'id'=>$datas['param']['id'],
                'statusCode' => 30001,
                'timestampMillis'=>$this->getMillisecond(),
                'productId'=>$this->product
            ];
        }*/


    }


    function getMillisecond(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return intval(substr($msectime,0,13));
    }

    function getBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
