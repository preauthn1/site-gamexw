<?php


namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;

class Notify_pb extends Notify
{
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
        return false;
    }

    private $game_code = 'football';

    function sport($datas, $trdobj, $cfg){
        $param = $datas['param'];
        $url = explode('sport/',$param['s']);
        $action_arr = explode('/', $url[1]);
        //用到的方法 ping  获取余额  下注
        $action = $action_arr[count($action_arr) - 1];
        $arr = ['ping', 'balance'];

        //ping 不用验签
        if($action != 'ping'){
            $signflag = $trdobj->CheckSign($cfg,$param);
            if($signflag){
                if(in_array($action, $arr)){
                    $res = $this->$action($param, $trdobj, $cfg);
                }else{
                    $res = $this->action($param, $trdobj, $cfg);
                }
            } else {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:'.$action,'签名错误',$param]);
                $r['ErrorCode'] = '-6';
                return $r;
            }
        }else{
            $res = $this->ping($param, $trdobj, $cfg);
        }
        return $res;
    }

    function ping($datas, $trdobj, $cfg){

        $arr['ErrorCode'] = 0;
        $arr['Timestamp'] = $this->get_timestamp();
        $arr['Result']['Available'] = true;
        return $arr;
    }

    private function get_timestamp(){
        $g_date = strtotime(gmdate('Y-m-d H:i:s'));
        $g_date_4 = $g_date - 14400;
        $format = str_replace('/', 'T', date('Y-m-d/H:i:s', $g_date_4));
        return $format;
    }

    function balance($datas, $trdobj, $cfg){
    }



    function action($datas, $trdobj, $cfg){

        // "TransactionId": 1177217, 交易纪录ID
        // "TransactionType": "DEBIT"
        //  "TransactionDate": "2022-06-14T07:32:16", 交易纪录时间
        //   "Amount": 100 金额
        // "awayPitcher": 客队投手
        // "awayPitcherName": 客队投手名称
        // "awayTeam": 客队
        // "betType": 投注类型
        // "cancellationStatus": 取消状态
        // "category": 类别
        // "competitors": 不适用
        // "currencyCode": 币别
        // "eventDateFm": 赛事时间
        // "eventName": 赛事名称
        // "handicap": 让球盘让球数
        // "headToHead": 对抗赛
        // "homePitcher": 主队投手
        // "homePitcherName": 主队投手名称
        // "homeTeam": 主队
        // "inPlay": 指出是否为滚球比赛
        // "inplayScore": 滚球分数
        // "isResettle": 是否有重新结算
        // "league": 联賽
        // "leagueId": 联賽 ID
        // "loginId": 昵称
        // "odds": 赔率
        // "oddsFormat": 赔率格式
        // "parentEventName": 如果客人选择”优胜冠军盘”，則这一项会显示选择的人名或队名
        // "parlayMixOdds": 过关盘赔率/混合过关赔率
        // "parlaySelections": 混合过关选项
        // "period": 期间状态
        // "product": 体育博彩
        // "result": 结果
        // "scores": 比分
        // "selection": 选项
        // "settleDateFm": 注单结算日期
        // "sport": 体育项目
        // "sportId": 体育项目 ID
        // "stake": 注金
        // "status": 状态
        // "toRisk":下注风险
        // "toWin": 期望彩金
        // "turnover": 交易额
        // "userCode": 会员账号
        // "volume": 总投注量
        // "wagerDateFm": 下注时间
        // "wagerId": 注单号
        // "wagerType: 注单类型
        // "winLoss": 输赢金额




        $usercode = $datas['usercode']; //用户名
        $actions = $datas['Actions'];

        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:request_data', $datas]);

        $utuser = UserThirds::where('ut_trdname', $usercode)->find();
        $rate = $cfg['trdcfg']["tc_exchangerate"];//汇率
        if ($utuser) {
            foreach($actions as $k => $v){
                $type = strtolower($v['Name']);//操作类型
                //$k 是索引  因为actions 中可能不止一个action  循环到哪个key 就是用哪个key
                $arr = $this->$type($datas, $trdobj, $cfg, $utuser->ut_uid, $rate, $k);
            }
        } else {
            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['Token error']);
            $arr['ErrorCode'] = '-1';
        }
        return $arr;
    }

    //下注 1
    public function  betted($datas, $trdobj, $cfg, $uid, $rate, $k){


        $ts = time();
        $actions =  $datas['Actions
        https://363gm.com
        '][$k];
        // $round_id = $data['roundId'];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $bet = GameBets::where('gb_gid', $gid)->where(['gb_sn' => $actions['WagerInfo']['WagerId']])->find();
//        $bet = GameBets::where('gb_gid', $gid)->where('gb_sn', 'like', "%{$actions['WagerInfo']['WagerId']}%")->find();


        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $balance = $this->getUserBalance($uinfo[Meta::UFIELD['uid']]);

            if ($bet) {
                $return = [
                    'Result' => [
                        'UserCode' => $datas['usercode'],
                        'AvailableBalance' => $balance,
                        'Actions' => [
                            [
                                'Id' => $actions['Id'],
                                'TransactionId' => $actions['Transaction']['TransactionId'],
                                'WagerId' => $actions['WagerInfo']['WagerId'],
                                'ErrorCode' => 0,
                            ]
                        ]
                    ],
                    'ErrorCode' => 0,
                    'Timestamp' => $this->get_timestamp(),
                ];
            }else{
                // $amount = $actions['Transaction']['Amount'];
                // 存在 ToRisk 就是 选择了 更佳赔率 是风险金额 是可变的。 如果不存在就是 普通 Stake
                $amount = $actions['WagerInfo']['ToRisk'] ?? $actions['WagerInfo']['Stake'];
                $amount = bcdiv($amount, $rate, 2);
                if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {


                    $msg = UserModel::betRemarks($actions['WagerInfo']['WagerId'], $cfg['trdcfg']['tc_name'], $gid);
                    $betcjprice = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg, true, $cfg['trdcfg']['tc_id'], $gid);
//                    if ($rlt){
                        //存在就是串关
//                        $bet = [];
//                        if(isset($actions['WagerInfo']['Legs'])){
//                            foreach($actions['WagerInfo']['Legs'] as $k => $v){
//                                $bet[] = [
//                                    'gb_gid' => $gid,
//                                    'gb_tcid' => $cfg['trdcfg']['tc_id'],
//                                    'gb_uid' => $uid,
//                                    'gb_intime' => $ts,
//                                    'gb_price' => $amount,
//                                    'gb_sn' => $actions['WagerInfo']['WagerId'] . '_' . ($k+1),
//                                    'gb_winlose' => 1,
//                                    'gb_bettime' => $ts,
//                                    'gb_overtime' => '',
//                                    'gb_winprice' => 0,
//                                    'gb_betinfo' => json_encode($datas)
//                                ];
//                            }
//                        }else{
//                            $bet[] = [
//                                'gb_gid' => $gid,
//                                'gb_tcid' => $cfg['trdcfg']['tc_id'],
//                                'gb_uid' => $uid,
//                                'gb_intime' => $ts,
//                                'gb_price' => $amount,
//                                'gb_sn' => $actions['WagerInfo']['WagerId']. '_1',
//                                'gb_winlose' => 1,
//                                'gb_bettime' => $ts,
//                                'gb_overtime' => '',
//                                'gb_winprice' => 0,
//                                'gb_betinfo' => json_encode($datas)
//                            ];
//                        }
                        $bet = [
                            'gb_gid' => $gid,
                            'gb_tcid' => $cfg['trdcfg']['tc_id'],
                            'gb_uid' => $uid,
                            'gb_intime' => $ts,
                            'gb_price' => $amount,
                            'gb_cjprice' => $betcjprice,
                            'gb_sn' => $actions['WagerInfo']['WagerId'],
                            'gb_winlose' => 1,
                            'gb_bettime' => $ts,
                            'gb_overtime' => '',
                            'gb_winprice' => 0,
                            'gb_betinfo' => json_encode($datas)
                        ];
                        $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'])->find();

                        //写入注单
                        /* @GameBets $GameBetsModel*/
                        $GameBetsModel = app()->make(GameBets::class);
                        $res = $GameBetsModel->insertGetId($bet);

                        if ($res){
                            $balance = $this->getUserBalance($uid);
                            $return = [
                                'Result' => [
                                    'UserCode' => $datas['usercode'],
                                    'AvailableBalance' => $balance,
                                    'Actions' => [
                                        [
                                            'Id' => $actions['Id'],
                                            'TransactionId' => $actions['Transaction']['TransactionId'],
                                            'WagerId' => $actions['WagerInfo']['WagerId'],
                                            'ErrorCode' => 0,
                                        ]
                                    ]
                                ],
                                'ErrorCode' => 0,
                                'Timestamp' => $this->get_timestamp()
                            ];
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet', '注单写入成功', $return]);
                            Db::commit();
                        } else {
                            Db::rollback();
                            $return = [
                                'ErrorCode' => -1,
                            ];
                            $bet['msg'] = '注单写入失败';
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','过程错误', $bet]);
                        }
//                    } else {
//                        Db::rollback();
//                        $return = [
//                            'ErrorCode' => -1,
//                        ];
//                        $bet['msg'] = '注单扣款失败';
//                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','过程错误', $bet]);
//                    }

                }else{
                    $return['ErrorCode'] = '-1';
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:bet','过程错误', ['Amount' => $amount, 'uinfo' => $uinfo]]);
                }
            }

        } catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:bet','过程错误',$ex->getMessage(), $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }
        return $return;
    }


    //已接受 1  如果是接受了最佳赔率  注金可能会发生变化  用户余额也随之增加
    //例子: 下注100 赔率1 选择最佳赔率。赔率发生变化 为 2 。 彩金还是100 但是 注金下降至50。
    public function accepted($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        //更新注单
        /* @GameBets $GameBetsModel*/
        //存在调整余额的标识时候
        $wh_like = $wh = $upd = [];
//        $wh_like[] = ['gb_sn', 'like', "%".$actions['WagerInfo']['WagerId']."%"];
        $wh['gb_winlose'] = 1; //未结算的时候
        $wh['gb_gid'] = $gid;
        $wh['gb_sn'] = $actions['WagerInfo']['WagerId'];
        if(isset($actions['Transaction'])){
            //TODO 不加锁的原因：存在串关订单 如果加锁会导致 串关订单只能更新第一条
            $GameBets = GameBets::where($wh)->find();
//            $GameBets = GameBets::where($wh)->where($wh_like)->find();

//            $aflag = Redis::get("pb_accepted_" . $gid . "_" . $action_id);
            if(isset($GameBets['gb_id'])){
//                Redis::setex("pb_accepted_" . $gid . "_" . $action_id, 1, 60);//60秒内不接受重复得action
                Db::startTrans();
                try {
                    if($actions['Transaction']['TransactionType'] == 'CREDIT'){
                        //加钱操作
                        $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();

                        $amount = $actions['Transaction']['Amount']; //要增加的金额
                        // $amount = $actions['WagerInfo']['ToRisk'];
                        $amount = bcdiv($amount, $rate, 2);
                        $msg = UserModel::betRemarks($actions['WagerInfo']['WagerId'], $cfg['trdcfg']['tc_name'], $gid, '赔率调整，注金返还');
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
//                        if ($rlt){

                            $torisk = $actions['WagerInfo']['ToRisk'];//注单金额更新成新的
                            $torisk = bcdiv($torisk, $rate, 2);
                            $upd['gb_price'] = $torisk;
                            $upd['gb_betinfo'] = json_encode($datas);

//                            $wh['gb_winlose'] = 1;
//                            $wh['gb_sn'] = $actions['WagerInfo']['WagerId'];
                            // if(isset($actions['WagerInfo']['Legs'])){
                            //     foreach($actions['WagerInfo']['Legs'] as $k => $v){
                            //         $wh['gb_sn'] = $actions['WagerInfo']['WagerId'] . '_' . ($k+1);
                            //         $GameBetsModel->where($wh)->update($upd);
                            //     }
                            // }else{
                            //     $wh['gb_sn'] = $actions['WagerInfo']['WagerId'];
                            // }
                            $GameBets->save($upd);

                            $return = [
                                'Result' => [
                                    'UserCode' => $datas['usercode'],
                                    'AvailableBalance' => $this->getUserBalance($uid),
                                    'Actions' => [
                                        [
                                            'Id' => $actions['Id'],
                                            'TransactionId' => $actions['Transaction']['TransactionId'],
                                            'WagerId' => $actions['WagerInfo']['WagerId'],
                                            'ErrorCode' => 0,
                                        ]
                                    ]
                                ],
                                'ErrorCode' => 0,
                                'Timestamp' => $this->get_timestamp()
                            ];
                            Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:accepted', '更新订单成功', $return]);

                            Db::commit();
                        }
//                    }
                }catch (\Exception $ex) {
                    Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:accepted', '更新注金过程错误', $ex->getMessage(), $ex->getTraceAsString()]);
                    Db::rollback();
                    $return['ErrorCode'] = '-1';
                }
            }else{
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:accepted', '注单不存在', $datas]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }
        }else{
            try {
                //不用调整注金 跟用户余额的时候  只根据订单号 去修改 原始数据 订单状态更新为已接受
                $upd['gb_betinfo'] = json_encode($datas);
                $GameBets = GameBets::where($wh)->find();

                if(isset($GameBets['gb_id'])){
                    $GameBets->save($upd);

                    $balance = $this->getUserBalance($uid);
                    $return = [
                        'Result' => [
                            'UserCode' => $datas['usercode'],
                            'AvailableBalance' => $balance,
                            'Actions' => [
                                [
                                    'Id' => $actions['Id'],
                                    'WagerId' => $actions['WagerInfo']['WagerId'],
                                    'ErrorCode' => 0,
                                ]
                            ]
                        ],
                        'ErrorCode' => 0,
                        'Timestamp' => $this->get_timestamp()
                    ];
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:accepted', '更新订单成功', $return]);
                    Db::commit();
                }else{
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:accepted', '注单不存在', $datas]);
                    Db::rollback();
                    $return['ErrorCode'] = '-1';
                }

            }catch (\Exception $ex) {
                Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:accepted', '更新注单过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }


        }

        return $return;
    }



    //已返回 0
    public function rollbacked($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        $GameBetsModel = app()->make(GameBets::class);
        Db::startTrans();
        try {
            $gb_sn = $actions['WagerInfo']['WagerId'];
            $wh = $upd = [];
            $wh['gb_winlose'] = 1; //未结算注单
            $wh['gb_gid'] = $gid;
            $wh['gb_sn'] = $gb_sn;
//            $wh_like[] = ['gb_sn', 'like', "%{$gb_sn}%"];

            $upd['gb_winlose'] = 0; // 无效注单
            $upd['gb_betinfo'] = json_encode($datas); //重新更新原始注单
            $upd['gb_overtime'] = time();

            $GameBets = GameBets::where($wh)->find();
//            $GameBets = GameBets::where($wh)->where($wh_like)->find();

                if(isset($GameBets['gb_id'])){
                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '注单返回，注金返还');
                    $rlt = User::accountChange($uid, $GameBets['gb_price'], '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
//                    if($rlt){
                        $GameBets->save($upd);
//                        $GameBetsModel->where($wh)->where($wh_like)->update($upd);

                        $return = [
                            'Result' => [
                                'UserCode' => $datas['usercode'],
                                'AvailableBalance' => $this->getUserBalance($uid),
                                'Actions' => [
                                    [
                                        'Id' => $actions['Id'],
                                        'TransactionId' => $actions['Transaction']['TransactionId'],
                                        'WagerId' => $actions['WagerInfo']['WagerId'],
                                        'ErrorCode' => 0,
                                    ]
                                ]
                            ],
                            'ErrorCode' => 0,
                            'Timestamp' => $this->get_timestamp()
                        ];
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:rollbacked', '更新订单成功', $return]);
                        Db::commit();

//                    }
                }else{
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:rollbacked', '注单不存在', $datas]);
                    Db::rollback();
                    $return['ErrorCode'] = '-1';
                }


        }catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:rollbacked', '更新注单过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }
        return $return;
    }



    //已拒绝 0
    public function rejected($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        //更新注单
        /* @GameBets $GameBetsModel*/
        Db::startTrans();
        try {
            $gb_sn = $actions['WagerInfo']['WagerId'];
            $wh = $upd = [];
            $wh['gb_winlose'] = 1; //未结算注单
            $wh['gb_gid'] = $gid;
            $wh['gb_sn'] = $gb_sn;
//            $wh_like[] = ['gb_sn', 'like', "%{$gb_sn}%"];

            $upd['gb_winlose'] = 0;//调整为 无效注单
            $upd['gb_betinfo'] = json_encode($datas); //重新更新原始注单
            $upd['gb_overtime'] = time();

            $GameBets = GameBets::where($wh)->find();
//            $GameBets = GameBets::where($wh)->where($wh_like)->find();


            if(isset($GameBets['gb_id']) && $actions['Transaction']['TransactionType'] == 'CREDIT'){
                $amount = $actions['Transaction']['Amount']; //要增加的金额
                $amount = bcdiv($amount, $rate, 2);
                $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '注单被拒绝，注金返还');
                $rlt = User::accountChange($uid, $amount, '+', 3, $msg);
//                if ($rlt){
                    $GameBets->save($upd);
//                    $GameBetsModel->where($wh)->where($wh_like)->update($upd);

                    $return = [
                        'Result' => [
                            'UserCode' => $datas['usercode'],
                            'AvailableBalance' => $this->getUserBalance($uid),
                            'Actions' => [
                                [
                                    'Id' => $actions['Id'],
                                    'TransactionId' => $actions['Transaction']['TransactionId'],
                                    'WagerId' => $actions['WagerInfo']['WagerId'],
                                    'ErrorCode' => 0,
                                ]
                            ]
                        ],
                        'ErrorCode' => 0,
                        'Timestamp' => $this->get_timestamp()
                    ];
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:rejected', '更新订单成功', $return]);
                    Db::commit();
//                }
            }else{
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:rejected', '该订单不存在或已处理', $datas]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }
        }catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:rejected', '更新注单过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }


        return $return;
    }


    //已结算 234
    public function settled($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        Db::startTrans();

        try {
            $wh = $upd = [];
            $gb_sn = $actions['WagerInfo']['WagerId'];
            $wh['gb_winlose'] = 1; //未结算注单
            $wh['gb_gid'] = $gid;
            $wh['gb_sn'] = $gb_sn;
//            $wh_like[] = ['gb_sn', 'like', "%{$gb_sn}%"];


            $upd['gb_betinfo'] = json_encode($datas); //重新更新原始注单
            $upd['gb_overtime'] = time();
            $GameBets = GameBets::where($wh)->find();
//            $GameBets = GameBets::where($wh)->where($wh_like)->find();

            if(isset($GameBets['gb_id'])){
                //存在Transaction的话 就是赢的
                if(isset($actions['Transaction'])){
                    $upd['gb_winlose'] = 3;
                    $upd['gb_winprice'] = bcdiv($actions['Transaction']['Amount'], $rate, 2); ;//彩金
                }else{
                    $upd['gb_winlose'] = 2;
                }
                $GameBets->save($upd);

                $return = [
                    'Result' => [
                        'UserCode' => $datas['usercode'],
                        'AvailableBalance' => $this->getUserBalance($uid),
                    ],
                    'ErrorCode' => 0,
                    'Timestamp' => $this->get_timestamp()
                ];
                if(isset($actions['Transaction'])) {
                    $return['Result']['Actions'] =  [
                        [
                            'Id' => $actions['Id'],
                            'TransactionId' => $actions['Transaction']['TransactionId'],
                            'WagerId' => $actions['WagerInfo']['WagerId'],
                            'ErrorCode' => 0,
                        ]
                    ];
                }else{
                    $return['Result']['Actions'] =  [
                        [
                            'Id' => $actions['Id'],
                            'WagerId' => $actions['WagerInfo']['WagerId'],
                            'ErrorCode' => 0,
                        ]
                    ];
                }
                    Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:settled', '更新订单成功', $return]);
                Db::commit();
            }else{
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:settled', '订单不存在', $datas]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }


        }catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:settled', '更新注单过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }


        return $return;

    }



    //已取消 0
    public function cancelled($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        Db::startTrans();
        try {
            $wh = $upd = [];
            $gb_sn = $actions['WagerInfo']['WagerId'];
            $wh['gb_winlose'] = 1; //未结算注单
            $wh['gb_gid'] = $gid;
            $wh['gb_sn'] = $gb_sn;
//            $wh_like[] = ['gb_sn', 'like', "%{$gb_sn}%"];

            $upd['gb_winlose'] = 0; // 无效注单
            $upd['gb_betinfo'] = json_encode($datas); //重新更新原始注单
            $upd['gb_overtime'] = time();

            $GameBets = GameBets::where($wh)->find();
//            $GameBets = GameBets::where($wh)->where($wh_like)->find();

            if(isset($GameBets['gb_id'])){
                    $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '注单取消，注金返还');
                    $rlt = User::accountChange($uid, $GameBets['gb_price'], '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
//                    if($rlt){
                        $GameBets->save($upd);
//                        $GameBetsModel->where($wh)->where($wh_like)->update($upd);

//                        $uinfo = UserModel::where(Meta::UFIELD['uid'], $uid)->lock(true)->field(Meta::UFIELD['ubalance'])->find();
//                        $balance = bcmul($uinfo[Meta::UFIELD['ubalance']], $rate, 2);
                        $return = [
                            'Result' => [
                                'UserCode' => $datas['usercode'],
                                'AvailableBalance' => $this->getUserBalance($uid),
                                'Actions' => [
                                    [
                                        'Id' => $actions['Id'],
                                        'TransactionId' => $actions['Transaction']['TransactionId'],
                                        'WagerId' => $actions['WagerInfo']['WagerId'],
                                        'ErrorCode' => 0,
                                    ]
                                ]
                            ],
                            'ErrorCode' => 0,
                            'Timestamp' => $this->get_timestamp()
                        ];

                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:cancelled', '更新订单成功', $return]);
                        Db::commit();

//                    }

            }else{
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:cancelled', '订单不存在', $datas]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }

        }catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:cancelled', '更新注单过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }
        return $return;
    }


    //未结算(取消结算) 1
    public function unsettled($datas, $trdobj, $cfg, $uid, $rate, $k){
        $actions =  $datas['Actions'][$k];
        $gid = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
        Db::startTrans();
        try {

            $wh = $upd = [];
            $gb_sn = $actions['WagerInfo']['WagerId'];
            $wh['gb_gid'] = $gid;
            $wh['gb_sn'] = $gb_sn;
//            $wh_like[] = ['gb_sn', 'like', "%{$gb_sn}%"];

            $upd['gb_winlose'] = 1; // 改为未结算注单
            $upd['gb_winprice'] = 0; //彩金改为0
            $upd['gb_betinfo'] = json_encode($datas); //重新更新原始注单
            $upd['gb_overtime'] = time();

//            $GameBets = GameBets::where($wh)->where($wh_like)->find();
            $GameBets = GameBets::where($wh)->find();
            if(isset($GameBets['gb_id'])){
                    $amount = $actions['Transaction']['Amount']; //要调整的金额
                    $amount = bcdiv($amount, $rate, 2);
                    if($actions['Transaction']['TransactionType'] == 'CREDIT'){
                        // CREDIT 加钱
                        $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '注单取消结算，注金返还');
                        $rlt = User::accountChange($uid, $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id'], $gid, $GameBets['gb_id'], $GameBets['gb_price'], $GameBets['gb_cjprice']);
                    }else{
                        // DEBIT 扣钱
                        $msg = UserModel::betRemarks($gb_sn, $cfg['trdcfg']['tc_name'], $gid, '注单取消结算，扣除');
                        $rlt = User::accountChange($uid, $amount, '-', 4, $msg, true, $cfg['trdcfg']['tc_id'], $gid);

                    }

//                    if($rlt){
//                        $GameBetsModel->where(['gb_winlose', '>', 1])->where($wh_like)->update($upd);
//                        $GameBetsModel->where(['gb_winlose', '>', 1])->where($wh)->update($upd);
                        $GameBets->save($upd);
                        $return = [
                            'Result' => [
                                'UserCode' => $datas['usercode'],
                                'AvailableBalance' => $this->getUserBalance($uid),
                                'Actions' => [
                                    [
                                        'Id' => $actions['Id'],
                                        'TransactionId' => $actions['Transaction']['TransactionId'],
                                        'WagerId' => $actions['WagerInfo']['WagerId'],
                                        'ErrorCode' => 0,
                                    ]
                                ]
                            ],
                            'ErrorCode' => 0,
                            'Timestamp' => $this->get_timestamp()
                        ];
                        Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:unsettled', '更新订单成功', $return]);
                        Db::commit();
//                    }
            }else{
                Utils::L($cfg['trdcfg']['tc_id'] . "/log",['notify:unsettled', '注单不存在', $datas]);
                Db::rollback();
                $return['ErrorCode'] = '-1';
            }

        }catch (\Exception $ex) {
            Utils::L($cfg['trdcfg']['tc_id'] . "/error",['notify:unsettled', '取消结算过程错误', $ex->getMessage(), $datas, $ex->getTraceAsString()]);
            Db::rollback();
            $return['ErrorCode'] = '-1';
        }
        return $return;
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }


    

}
