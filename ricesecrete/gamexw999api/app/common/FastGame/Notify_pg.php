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
use app\common\model\UserModel;
use app\model\GameAward;
use app\model\GameBets;
use app\model\GameBetsSon;
use app\model\Games;
use app\model\Users;
use app\model\UserThirds;
use think\facade\Config;
use think\facade\Db;

class Notify_pg extends Notify
{
    public $game_code = 'pg_game';

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
        unset($param['s']);unset($param['action_method']);
        $action = $param['name'];
        $token = $param['token'];
        list($t, $uid) = explode(':',$token);
        $signflag = $trdobj->CheckSign($cfg,$param);
        if($signflag){
            $res = $this->$action($param, $trdobj, $cfg, $uid);
            return $res;
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:'.$action,'签名错误',$cfg]);
            abort(403, 'Signature code');
        }
    }

    function GetBalance($datas, $trdobj,$cfg) {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['balance', 'notify:'.json_encode($datas)]);
        if ($datas['param']['operator_token'] != $cfg['trdcfg']["tc_tag"] || $datas['param']['secret_key'] != $cfg['trdcfg']["tc_api_prikey"]) {
            return [
                'data' => null,
                'error' => [
                    'code' => '1204',
                    'message' => '无效运营商',
                ]
            ];
        }
        $username = myDecrypt(urldecode($datas['param']['operator_player_session']));
        if(!$username){
            //加密版本用户数据兼容
            $username = $datas['param']['operator_player_session'];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
//            $balance = UserModel::where(Meta::UFIELD['uid'], $utuser)->value(Meta::UFIELD['ubalance']);
            $balance = $this->getUserBalance($utuser);
            $return = [
                'data' => [
                    'updated_time' => $this->getMillisecond(),
                    'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $utuser),
                    'balance_amount' => (float)($balance > 0 ? $balance : 0),
                ],
                'error' => null
            ];
            Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['balance', json_encode($return)]);
            return $return;
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['balance', 'Token code', $datas]);
            return [
                'data' => null,
                'error' => [
                    'code' => '3005',
                    'message' => '玩家钱包不存在',
                ]
            ];
        }

    }

    function VerifySession($datas, $trdobj, $cfg) {
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['pg', 'VerifySession:'.json_encode($datas)]);
        if ($datas['param']['operator_token'] != $cfg['trdcfg']["tc_tag"] || $datas['param']['secret_key'] != $cfg['trdcfg']["tc_api_prikey"]) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['VerifySession', '无效运营商', $datas]);
            return [
                'data' => null,
                'error' => [
                    'code' => '1204',
                    'message' => '无效运营商',
                ]
            ];
        }
        $username = myDecrypt(urldecode($datas['param']['operator_player_session']));
        if( !$username ){
            //加密版本用户数据兼容
            $username = $datas['param']['operator_player_session'];
        }
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
            $return = [
                'data' => [
                    'player_name' => $username,
                    'currency' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $utuser),
                ],
                'error' => null
            ];
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['VerifySession', '内部服务器错误', $datas]);
            $return = [
                'data' => null,
                'error' => [
                    'code' => '1200',
                    'message' => '内部服务器错误',
                ]
            ];
        }
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['pg', 'VerifySession-return:'.json_encode($return)]);
        return $return;
    }

    function Cash($datas, $trdobj, $cfg)
    {
        $this->game_code = $datas['param']['game_id'] ?? 'pg';
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['pg', $this->game_code, 'Cash:'.json_encode($datas)]);
        if ($datas['param']['operator_token'] != $cfg['trdcfg']["tc_tag"] || $datas['param']['secret_key'] != $cfg['trdcfg']["tc_api_prikey"]) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['Cash', '无效运营商', $datas]);
            return [
                'data' => null,
                'error' => [
                    'code' => '1204',
                    'message' => '无效运营商',
                ]
            ];
        }
        $s_arr = explode( 'Cash/', $datas['param']['s']);
        switch ($s_arr[1]) {
            case 'Get':
                return $this->GetBalance($datas, $trdobj, $cfg);
                break;
            case 'TransferInOut':
            case 'TranferInOut':
                return $this->bet($datas, $trdobj, $cfg);
                break;
            case 'Adjustment':
                return $this->paysettle($datas, $trdobj, $cfg);
                break;
        }
    }

    function paysettle($datas, $trdobj,$cfg){
        Utils::L($cfg['trdcfg']['tc_id'] . "/log", ['transaction', 'notify:'.json_encode($datas)]);
        $username = myDecrypt(urldecode($datas['param']['player_name']));
        if( !$username ){
            //加密版本用户数据兼容
            $username = $datas['param']['player_name'];
        }
        $currencyCode = $datas['param']['currency_code'];
        $adjustment_transaction_id = $datas['param']['adjustment_transaction_id'];
        $utuser = UserModel::where('rec_code', $username)->value('id');
        if ($utuser) {
            $rate = $cfg['trdcfg']["tc_exchangerate"];
            $ts=time();
            Db::startTrans();
            try {
//                $balance_before = UserModel::where(Meta::UFIELD['uid'], $utuser)->value(Meta::UFIELD['ubalance']);
                $balance_before = $this->getUserBalance($utuser);
                $gb_winprice = bcdiv($datas['param']['transfer_amount'],$rate,2);

                if ($gb_winprice < 0 && abs($gb_winprice) > $balance_before) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['paysettle', '玩家余额不足', $datas]);
                    return [
                        'data' => null,
                        'error' => [
                            'code' => '3202',
                            'message' => '玩家余额不足',
                        ]
                    ];
                }
                $currencyCodeArr = Config::get('custom.currency_code');
                if( !in_array($currencyCode, $currencyCodeArr) ){
                    return [
                        'data' => null,
                        'error' => [
                            'code' => '3005',
                            'message' => '货币不存在',
                        ]
                    ];
                }

                $where = [
                    'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gs_uid'=>$utuser,
                    'gs_sn'=>$adjustment_transaction_id,
                ];
                //判断是否重复注单
                $rlt = Db::name('game_third_special')->where($where)->find();
                if ($rlt) {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['paysettle', '重复请求', $datas]);
                    return [
                        'data' => [
                            'adjust_amount' => (float)$gb_winprice,
                            'balance_before' => (float)($balance_before - $gb_winprice),//重复注单则返回跟上次金额
                            'balance_after' => (float)$balance_before,
                            'updated_time'  => (int)$datas['param']['adjustment_time'],
                        ],
                        'error' => null
                    ];
                }
                $data = [
                    'gs_tcid'=>$cfg['trdcfg']['tc_id'],
                    'gs_uid'=>$utuser,
                    'gs_price'=>trim($gb_winprice,'-'),
                    'gs_sn'=>$adjustment_transaction_id,
                    'gs_type'=>$gb_winprice > 0 ? 1:2,
                    'gs_type_name'=>$datas['param']['transaction_type'] == 900 ? '外部调整':'锦标赛调整',
                    'gs_time'=>$ts,
                    'gs_create_time'=>$ts,
                    'gs_info'=>json_encode($datas['param'])
                ];

                //写入注单
                $rlt = Db::name('game_third_special')->insertGetId($data);
                if ($rlt){
                    $msg = UserModel::betRemarks($adjustment_transaction_id, $cfg['trdcfg']['tc_name'], 0, '活动余额调整');
                    $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
                    if( $gb_winprice > 0 ){
                        User::accountChange($utuser, trim($gb_winprice,'-'), '+', 3, $msg);
                    }else{
                        User::accountChange($utuser, trim($gb_winprice,'-'), '-', 4, $msg);
                    }
//                    $balance_after = UserModel::where(Meta::UFIELD['uid'], $utuser)->value(Meta::UFIELD['ubalance']);
                    $balance_after = $this->getUserBalance($utuser);
                    $balance_after = Third::transExchangerate($balance_after, 1, 2);
                    Db::commit();
                    return [
                        'data' => [
                            'adjust_amount' => (float)$gb_winprice,
                            'balance_before' => (float)$balance_before,
                            'balance_after' => (float)$balance_after,
                            'updated_time'  => (int)$datas['param']['adjustment_time'],
                        ],
                        'error' => null
                    ];
                }else{
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['paysettle', '支付失败', $datas]);
                    return [
                        'data' => null,
                        'error' => [
                            'code' => '3034',
                            'message' => '支付失败',
                        ]
                    ];
                }
            } catch (\Exception $ex) {
                Db::rollback();
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['paysettle', '过程错误', $datas]);
                return  [
                    'data' => null,
                    'error' => [
                        'code' => '3034',
                        'message' => '支付失败',
                    ]
                ];
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['paysettle', '玩家不存在', $datas]);
            return  [
                'data' => null,
                'error' => [
                    'code' => '3004',
                    'message' => '玩家不存在',
                ]
            ];
        }
    }

    function bet($datas, $trdobj,$cfg){
        $datas = $datas['param'];
        Utils::L($cfg['trdcfg']['tc_id'] . "/log",[$datas]);
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $ts=time();
        Db::startTrans();
        try {
            $currencyCode = $datas['currency_code'];
            $username = myDecrypt(urldecode($datas['operator_player_session']));
            if( !$username ){
                //加密版本用户数据兼容
                $username = $datas['operator_player_session'];
            }
            $uinfo = UserModel::where(Meta::UFIELD['uname'], $username)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            if($uinfo){
                $uid = $uinfo[Meta::UFIELD['uid']];
//                $balance = $uinfo[Meta::UFIELD['ubalance'];
                $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);
                $parent_bet_id = $datas['parent_bet_id']; //母订单号
                $transaction_id = $datas['transaction_id']; //交易号
                $gb_sn = $datas['bet_id']; //子订单号
                $bet_amount = bcdiv($datas['bet_amount'],$rate,2);
                $win = bcdiv($datas['win_amount'],$rate,2);
                $bonusAmount = bcdiv($datas['bonus_balance_amount'] ?? 0,$rate,2);
                $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'code', 'g_id');
                //如果是红利游戏
                if($datas['wallet_type']=='B'&&round(floatval($datas['transfer_amount']), 4)==round(floatval($datas['win_amount'])-floatval($datas['bet_amount']), 4)){
                    // 处理实际变化金额返回给游戏商
                    //余额加上变化金额
                    $balance += round(floatval($datas['transfer_amount']), 4);
                }
                //红利游戏按照三方要求直接返回
                if(round(floatval($datas['transfer_amount']), 4)!=round(floatval($datas['win_amount'])-floatval($datas['bet_amount']), 4)){
                    //红利游戏
//                    $win = $bonusAmount + $win;
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['Cash', 'BetFailedException', $datas]);
                    return [
                        'data' => null,
                        'error' => [
                            'code' => '3073',
                            'message' => 'BetFailedException',
                        ]
                    ];
                }
                if ($gid) {
                    $data = [
                        'gb_gid' => $gid,
                        'gb_tcid' => $cfg['trdcfg']['tc_id'],
                        'gb_uid' => $uid,
                        'gb_intime' => $ts,
                        'gb_price' => $bet_amount,
                        'gb_sn' => $gb_sn,
                        'gb_winlose' => $win > 0 ? 3 : 2,
                        'gb_bettime' => $ts,
                        'gb_overtime' => $ts,
                        'gb_winprice' => $win,
                        'gb_cusfield' => $parent_bet_id,
                        'gb_trans_no' => $transaction_id,
                        'gb_betinfo' => json_encode($datas)
                    ];
                    $currencyCodeArr = Config::get('custom.currency_code');
                    if( !in_array($currencyCode, $currencyCodeArr) ){
                        return [
                            'data' => null,
                            'error' => [
                                'code' => '3005',
                                'message' => '货币不存在',
                            ]
                        ];
                    }
                    //参与活动必须使用transaction_id来判断重复注单
                    $_bets = GameBets::where('gb_trans_no', '=', $transaction_id)->find();
                    if( $_bets ){
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '重复请求：transaction_id', $datas]);
                        return [
                            'data' => [
                                'balance_amount' => (float)$balance,
                                'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $uid),
                                'updated_time' => (int)$datas['updated_time'],
                            ],
                            'error' => null
                        ];
                    }
                    $where = ['gb_gid' => $gid, 'gb_tcid' => $cfg['trdcfg']['tc_id'], 'gb_uid' => $uid, 'gb_sn' => $gb_sn];
                    $bets = GameBets::where($where)->find();
                    if ($bets) {
                        Db::rollback();
                        Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '重复请求', $datas]);
                        return [
                            'data' => [
                                'balance_amount' => (float)$balance,
                                'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $uid),
                                'updated_time' => (int)$datas['updated_time'],
                            ],
                            'error' => null
                        ];
//                            'data' => [
//                                'balance_amount' => (float)$balance,
//                                'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $uid),
//                                'updated_time' => (int)$datas['updated_time'],
//                            ],
//                            'error' => null
//                        ];
                    } else {
                        if($bet_amount <= 0 && $win <= 0){
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '无效注单数据', $datas]);
                            return [
                                'data' => [
                                    'balance_amount' => (float)$balance,
                                    'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $uid),
                                    'updated_time' => (int)$datas['updated_time'],
                                ],
                                'error' => null
                            ];
                        }
                        $rlt = (new GameBets())->insertGetId($data);
                        if ($rlt) {
                            if ($bet_amount < $balance) {
                                $betcjprice = 0;
                                if ($bet_amount > 0) {
                                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid);
                                    $betcjprice = User::accountChange($uid, $bet_amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
                                    (new GameBets())->where('gb_id', '=', $rlt)->update([
                                        'gb_cjprice' => $betcjprice
                                    ]);
                                }
                                if ($win > 0) {
                                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '结算');
                                    User::accountChange($uid, $win, '+', 2, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $rlt, $bet_amount, $betcjprice);
                                }
//                                $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
                                $balance = $this->getUserBalance($uid);
                                $return = [
                                    'data' => [
                                        'balance_amount' => (float)$balance,
                                        'currency_code' => Meta::initGetField('tccurrency', $cfg['trdcfg']['tc_id'], $gid, $uid),
                                        'updated_time' => (int)$datas['updated_time'],
                                    ],
                                    'error' => null
                                ];
                                Utils::L($cfg['trdcfg']['tc_id'] . "/log", [$return]);
                                Db::commit();
                                return $return;
                            } else {
                                Db::rollback();
                                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '余额不足', $datas]);
                                return [
                                    'data' => null,
                                    'error' => [
                                        'code' => '3202',
                                        'message' => '余额不足无法投注',
                                    ]
                                ];
                            }
                        } else {
                            Db::rollback();
                            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '注单操作失败', $datas]);
                            return [
                                'data' => null,
                                'error' => [
                                    'code' => '3033',
                                    'message' => '投注失败',
                                ]
                            ];
                        }
                    }
                } else {
                    Db::rollback();
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['bet', '游戏标志错误', $cfg['trdcfg']['tc_id'], $this->game_code, $gid, $datas]);
                    return [
                        'data' => null,
                        'error' => [
                            'code' => '1402',
                            'message' => '游戏不存在',
                        ]
                    ];
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '玩家不存在', $datas]);
                Db::rollback();
                return [
                    'data' => null,
                    'error' => [
                        'code' => '3004',
                        'message' => '玩家不存在',
                    ]
                ];
            }
        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error", ['bet', '过程错误', $datas, $ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            return [
                'data' => null,
                'error' => [
                    'code' => '3004',
                    'message' => '过程错误',
                ]
            ];
        }
    }

    function init($datas, $trdobj,$cfg, $uid)
    {
//        $balance = UserModel::where(Meta::UFIELD['uid'], $uid)->value(Meta::UFIELD['ubalance']);
        $balance = $this->getUserBalance($uid);
        $rate = $cfg['trdcfg']["tc_exchangerate"];
        $balance = bcmul($balance, $rate, 2);
        $return = ['status' => "ok", "data" => ['balance' => $balance, "currency" => "USD"]];
        return $return;
    }

    function getMillisecond(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return intval(substr($msectime,0,13));
    }
    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
