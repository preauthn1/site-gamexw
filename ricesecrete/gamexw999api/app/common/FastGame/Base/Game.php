<?php


namespace app\common\FastGame\Base;


use app\common\model\GameApisExtplModel;
use app\common\model\ThirdCostLogsModel;
use app\common\model\UserFreebetsModel;
use app\common\model\UserModel;
use app\model\GameBetsex;
use app\model\GameConfigs;
use app\model\GameInfos;
use app\model\Games;
use app\model\UserBalanceTranslog;
use app\model\UserGames;
use app\model\UserThirds;
use League\Flysystem\Util;
use think\Exception;
use think\facade\Db;

class Game
{
    /** 获取游戏信息
     * @param null $gid 不传从前端获取
     * @param null $lang 不传从前端获取
     * @param bool $rltflag 传值true表示后端获取信息
     * @return \App\Common\RltContent|Games|array|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2022-08-23 14:27
     */
    public static function getGameInfo($gid = null, $lang = null, $rltflag = true)
    {
        if (!$gid) $gid = Params::$gid;
        if (!$lang) $lang = Params::$lang;
        $rlt = Games::where('g_id', $gid)->find();
        if ($rlt) {
            $info = GameInfos::where('gi_gid', $gid)->where('gi_lang', $lang)->find();
            if (!$lang) {
                $info = GameInfos::where('gi_gid', $gid)->limit(1)->find();
            }
            $rlt['infos'] = $info;
        }
        if ($rltflag) {
            if ($rlt) {
                return Rlt::getok($rlt);
            } else {
                return Rlt::get(Rlt::CODE_NOT_MATCH, '未找到游戏信息！');
            }
        } else {
            return $rlt;
        }
    }

    /** 获取游戏列表
     * @param null $state 指定状态为1表示正常游戏，不传表示全部 TODO 可以根据实际需求考虑支持任一或任几状态
     * @param bool $rltflag
     * @return \App\Common\RltContent|array
     * @author twt326
     * @date 2022-08-23 15:42
     */
    public static function getGameList($state = null, $rltflag = true)
    {
        $page = Params::$page;
        $pagesize = Params::$psize;
        $cnt = Games::field('*');
        if ($state !== null) {
            $cnt->where('g_state', $state);
        }
        $q = clone $cnt;
        $q = $q->join('game_infos', "gi_lang='" . Params::$lang . "'", 'left')->limit(($page - 1) * $pagesize, $pagesize)->select();
        $rlt = ['count' => $cnt->count(), 'list' => $q];
        if ($rltflag) {
            return Rlt::getok($rlt);
        } else {
            return $rlt;
        }
    }

    /** 在三方平台注册用户
     * @param $uid
     * @param $gid
     * @param $gcfg
     * @param $otherkvs
     * @return UserThirds|array|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function registerThirdUser($uid, $gid, $gcfg, $otherkvs)
    {
        //用户是否已在三方平台注册
        $trduser = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $gcfg['trdcfg']['tc_id'])->find();
        $flag = true;
        $msg = '';
        if (!$trduser) {
            if (!isset($gcfg['spcapis']['reg']) && !isset($gcfg['spcapis']['first'])) {
                //如果是不需要注册的平台 初始化三方平台数据
                (new UserThirds())->save([
                    "ut_uid" => $uid,
                    "ut_intime" => time(),
                    "ut_tcid" => $gcfg['trdcfg']['tc_id']
                ]);
            } else {

                //return Rlt::getok($gcfg);
                $rlt = null;
                $rlt1 = null;
                $rlt2 = null;
                //配置中如果有reg的执行reg
                if (isset($gcfg['spcapis']['reg'])) {

                    $rlt = Third::run($gcfg, $gcfg['spcapis']['reg']['ga_func'], $otherkvs);
                    //return Rlt::getok([$gcfg,$uid,$rlt]);
                    if ($rlt !== false) {
                        $thirdObj = Third::initExtClass($gcfg);
                        $flag = Meta::parseResultData($gcfg['spcapis']['reg'], $rlt, $thirdObj, $gcfg['trdcfg']['tc_id'], $uid, $gid, $otherkvs);
                        Utils::L("{$gcfg['trdcfg']['tc_id']}/reg", ['Game:regend', $flag]);
                    } else {
                        $flag = false;
                        $msg = '三方平台' . $gcfg['trdcfg']['tc_code'] . '用户[' . $uid . ']注册失败！';
                        Utils::L("{$gcfg['trdcfg']['tc_id']}/reg_error", $msg);
                    }
                }
                //配置中如果有first的执行first
                if (isset($gcfg['spcapis']['first']) && $flag) {
                    $rlt1 = Third::run($gcfg, $gcfg['spcapis']['first']['ga_func'], $otherkvs);
                    if ($rlt !== false) {
                        $thirdObj = Third::initExtClass($gcfg);
                        $flag = Meta::parseResultData($gcfg['spcapis']['first'], $rlt1, $thirdObj, $gcfg['trdcfg']['tc_id'], $uid, $gid, $otherkvs);
                    } else {
                        $flag = false;
                        $msg = '三方平台' . $gcfg['trdcfg']['tc_code'] . '用户[' . $uid . ']首次访问接口失败！';
                    }
                }

            }
            // //TODO 这段是测试，要删除
            // if (isset($gcfg['spcapis']['getauthtoken'])){
            //     $ohterkvs['uid'] = $uid;
            //     $rlt3=Third::run($gcfg,$gcfg['spcapis']['getauthtoken']['ga_func'], $ohterkvs);
            //     if ($rlt3) {
            //         $thirdObj = Third::initExtClass($gcfg);
            //         $flag = Meta::parseResultData($gcfg['spcapis']['getauthtoken'], $rlt3, $thirdObj, $gcfg['trdcfg']['tc_id'], $uid, $gid,$otherkvs);
            //     } else {
            //         $flag = false;
            //         $msg = '三方平台' . $gcfg['trdcfg']['tc_code'] . '用户[' . $uid . ']请求token接口失败！';
            //     }
            // }
            $trduser = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $gcfg['trdcfg']['tc_id'])->find();
        }
        return $trduser;
    }

    /** 获取游戏入口地址
     * @param $gid null 游戏ID,为空则request参数中取
     * @param $uid null 用户为空则request参数中取
     * @param bool $rltflag
     * @param $otherkvs 其它需要传入的参数
     * @return string
     * @author twt326
     * @date 2022-08-23 15:46
     */
    public static function getGameUrl($gid = null, $uid = null, $rltflag = true, $otherkvs = [])
    {
        $thirdcost = ThirdCostLogsModel::getCost();
        if ($thirdcost <= 0) {
            if ($rltflag) {
                return Rlt::get(Rlt::CODE_NOT_MATCH, '游戏限制进入，请联系管理员！');
            } else {
                return [
                    'url' => null,
                    'transmsg' => '游戏限制进入，请联系管理员！'
                ];
            }
        }
        if (!$uid) $uid = Params::$uid;
        if (!$gid) $gid = Params::$gid;
        if (!$uid || !$gid) {
            return $rltflag ? Rlt::get(Rlt::CODE_VERIFY_ERR, '参数错误！') : null;
        }
        $url = null;
        $transmsg = null;
        $otherkvs = array_merge($otherkvs, ["uid" => $uid]);
        //拿到游戏配置
        $gcfg = Games::getConfigs($gid);
        //用户是否已经获取地址
        $ug = UserGames::where('ug_uid', $uid)->where('ug_gid', $gid)->where('ug_lang', Params::$lang)->find();

        //转账
        if (env('login_game_is_take_out_other_platform_balance', 1) == 1) {
            $from = UserThirds::where('ut_uid', $uid)->where('ut_state', 1)->limit(1)->find();
            if ($from && $from->ut_tcid != $gcfg['trdcfg']['tc_id']) {
                $transmsg = self::withdrawThird($from->ut_tcid, $uid);
            }
        }
        if ($ug) {
            if (isset($gcfg['apis']['GETUSERURL']) && $gcfg['apis']['GETUSERURL']['ga_rate'] > 0 && $ug['ug_lastupdate'] + $gcfg['apis']['GETUSERURL']['ga_rate'] <= time() || empty($ug['ug_url'])) {
                $rlt2 = Third::run($gcfg, 'GETUSERURL', $otherkvs);
                if ($rlt2) {
                    $thirdObj = Third::initExtClass($gcfg);
                    $flag = Meta::parseResultData($gcfg['apis']['GETUSERURL'], $rlt2, $thirdObj, $gcfg['trdcfg']['tc_id'], $uid, $gid, $otherkvs);
                    Utils::L("{$gcfg['trdcfg']['tc_id']}/login", ['Game:getgameurlend', $flag]);
                } else {
                    $flag = false;
                    $msg = '三方平台' . $gcfg['trdcfg']['tc_code'] . '用户[' . $uid . ']获取游戏[' . $gid . ']地址接口失败！';
                    Utils::L("{$gcfg['trdcfg']['tc_id']}/login_error", $msg);
                }
                $ug = UserGames::where('ug_uid', $uid)->where('ug_gid', $gid)->where('ug_lang', Params::$lang)->find();
                $url = $ug['ug_url'];
            } else {
                $url = $ug['ug_url'];
            }
        } else {//用户还未获取地址
            $trduser = self::registerThirdUser($uid, $gid, $gcfg, $otherkvs);
            if ($trduser) {
                $ug = UserGames::where('ug_gid', $gid)->where('ug_uid', $uid)->where('ug_lang', Params::$lang)->limit(1)->find();
                Utils::L("{$gcfg['trdcfg']['tc_id']}/login_first", ['Game:first', $ug]);
                if (!$ug || $ug && empty($ug->ug_url)) {
                    //正式获取用户游戏URL地址
                    // print_r($gcfg);
                    // print_r($otherkvs);die;
                    // $ohterkvs['token'] = $trduser['']
                    if (isset($gcfg['apis']['GETUSERURL'])) {
                        $ohterkvs['uid'] = $uid;
                        $rlt2 = Third::run($gcfg, 'GETUSERURL', $otherkvs);
                        if ($rlt2) {
                            $thirdObj = Third::initExtClass($gcfg);
                            $flag = Meta::parseResultData($gcfg['apis']['GETUSERURL'], $rlt2, $thirdObj, $gcfg['trdcfg']['tc_id'], $uid, $gid, $otherkvs);
                            Utils::L("{$gcfg['trdcfg']['tc_id']}/login_first", ['Game:getgameurlend', $flag]);
                        } else {
                            $flag = false;
                            $msg = '三方平台' . $gcfg['trdcfg']['tc_code'] . '用户[' . $uid . ']获取游戏[' . $gid . ']地址接口失败！';
                            Utils::L("{$gcfg['trdcfg']['tc_id']}/login_first_error", $msg);
                        }
                    }

                }
            }

            // $rlt2=Third::run($gcfg,'test');*/

            //return Rlt::getok([$rlt,$rlt1,$rlt2,Params::$page,Params::$psize]);
            //再次获取游戏地址
            $ug = UserGames::where('ug_gid', $gid)->where('ug_uid', $uid)->where('ug_lang', Params::$lang)->limit(1)->find();
            if ($ug) {
                $url = $ug['ug_url'];

            }
        }
        //转账
        if (isset($gcfg['apis']['GETBALANCE'])) { //如果有获取余额的表示需要转账操作
            $from = UserThirds::where('ut_uid', $uid)->where('ut_state', 1)->limit(1)->find();
            if ($from && $from->ut_tcid != $gcfg['trdcfg']['tc_id']) {//同平台不用提现再转，直接转三方
                //是否将其他平台余额取出
                if (env('login_game_is_take_out_other_platform_balance', 1) == 1) {
                    $transmsg = self::transBalanceToThird($from->ut_tcid, $gcfg['trdcfg']['tc_id'], $uid, isset($otherkvs['depositMoney']) ? $otherkvs['depositMoney'] : 0);
                }
            } else {
                $transmsg = self::depositThird($gcfg['trdcfg']['tc_id'], $uid, isset($otherkvs['depositMoney']) ? $otherkvs['depositMoney'] : 0);
            }
        }
        if ($rltflag) {
            if ($url) {
                return Rlt::getok([
                    'url' => $url,
                    'transmsg' => $transmsg
                ]);
            } else {
                return Rlt::get(Rlt::CODE_NOT_MATCH, '未获取到游戏入口地址，请联系管理员！');
            }
        } else {
            return [
                'url' => $url,
                'transmsg' => $transmsg
            ];
        }
    }

    /** 将fromtcid指定的三方平台余额转移到 totcid指定的三方平台中
     * 通过平台帐户进行中转
     * @param $fromtcid
     * @param $totcid
     * @param $uid default null 为NULL表示从Pamars中获取
     * @param float $price default 0 转全部余额，有值则表示转指定余额
     * @return array['transamount','targetban'] 转入金额，目标平台金额 targetban=false表示目标平台余额获取失败 ；targetban=false 表示转账到目标平台失败，此值为false不阻碍流程
     * @author twt326
     * @date 2022-09-22 22:25
     */
    public static function transBalanceToThird($fromtcid, $totcid, $uid = null, $price = 0)
    {
        Utils::L("{$fromtcid}/trans", ['转账：', $fromtcid, $totcid, $uid]);
        $uid = $uid ?? Params::$uid;
        $transban = 0;

        //提现到平台
        $ban = self::withdrawThird($fromtcid, $uid);
        //if ($ban && $ban>0) {
        //转账到to
        $transban = self::depositThird($totcid, $uid, $price);
        //}
        //获取to余额
        //$toban=self::getThirdBalance($totcid,$uid);
        return [
            'targetban' => $transban['targetban'],
            'withdrawban' => $ban,
            'transamount' => $transban['transamount']
        ];
    }

    /** 一次性获取和更新（用户三方游戏表中的余额）用户在三方平台的余额
     * @param $uid
     * @return array|bool array(tcid=>array('msg'=>errormsg)|amount|false)
     */
    public static function getThirdBalanceAll($uid = null)
    {
        $rtn = [];
        $uid = $uid ?? Params::$uid;
        if ($uid) {
            //查询所有用户已注册三方主动平台（有配置获取余额接口的）
            $rlt = Db::query("select ut_tcid from dq_user_thirds 
left join dq_game_apis on ut_tcid=ga_tcid and ga_func='GETBALANCE'
where ut_uid=$uid and ga_id is not null");
            for ($i = 0; $i < count($rlt); $i++) {
                try {
                    $rtn[$rlt[$i]['ut_tcid']] = self::getThirdBalance($rlt[$i]['ut_tcid'], $uid);
                } catch (\Exception $ex) {
                    $rtn[$rlt[$i]['ut_tcid']] = ['msg' => $ex->getMessage()];
                }
            }
            return $rtn;
        } else {
            return false;
        }
    }

    /** 一次性提现一个用户所有三方平台余额
     * @param null $uid
     * @return array|bool
     * @author twt326
     * @date 2022-10-22 3:50
     */
    public static function withdrawThirdAll($uid = null)
    {
        $rtn = [];
        $uid = $uid ?? Params::$uid;
        if ($uid) {
            //查询所有用户已注册三方主动平台（有配置提现接口的）
            $rlt = Db::query("select ut_tcid from dq_user_thirds 
left join dq_game_apis on ut_tcid=ga_tcid and ga_func='WITHDRAW'
where ut_uid=$uid and ga_id is not null");
            for ($i = 0; $i < count($rlt); $i++) {
                try {
                    $rtn[$rlt[$i]['ut_tcid']] = self::withdrawThird($rlt[$i]['ut_tcid'], $uid);
                } catch (\Exception $ex) {
                    $rtn[$rlt[$i]['ut_tcid']] = ['msg' => $ex->getMessage()];
                }
            }
            return $rtn;
        } else {
            return false;
        }
    }

    /**
     * 对于从三方平台提现时发生错误的的后续处理
     * 必须是提现失败的记录(日志状态为 1未知原因导致可能的失败 或2 逻辑明确转账失败)
     * @param $transid 转账ID
     * @param bool $backmoney 是否增加余额到平台用户钱包 默认false
     * @param string $loginfo 要记入处理日志的信息，建议是处理人姓名和理由之类的
     * @return string|true 返回===true表示处理成功 ，否则返回处理失败的提示信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2023-01-18 1:40
     */

    public static function rollbackWithdrawThird($transid, $backmoney = false, $loginfo = '')
    {
        $rec = UserBalanceTranslog::where('ubtl_id', $transid)->find();
        if ($rec) {
            if ($rec->ubtl_type == 4 && ($rec->ubtl_status == 2 || $rec->ubtl_status == 1)) {
                Db::startTrans();
                try {
                    $flag = true;
                    if ($backmoney) {
                        $flag = User::accountChange_old($rec->ubtl_uid, $rec->ubtl_amount, '+', 3, '从三方平台提现失败补余额[三方平台：' . $rec->ubtl_tcid . '，提现记录：' . $transid . ']');
                    }
                    if ($flag) {
                        $rec->ubtl_status = $backmoney ? 4 : 5;
                        $rec->ubtl_errproc = time();
                        $rec->ubtl_errprocinfo = $loginfo;
                        $rec->save();
                        Db::commit();
                        return true;
                    } else {
                        Db::rollback();
                        return "处理失败：余额增加失败或提现记录更新失败！";
                    }
                } catch (\Exception $ex) {
                    Db::rollback();
                    return "处理错误：" . $ex->getMessage();
                }
            } else {
                return "当前提现记录不符合恢复条件（记录类型：三方平台提现，转账状态：失败或准备状态）";
            }
        } else {
            return "没有找到需要处理的提现记录";
        }
    }

    /**
     * 从 $fromtcid 指定的三方平台转出余额到平台
     * @param $fromtcid
     * @param $uid default null 为NULL表示从Pamars中获取
     * @return bool|float
     * @author twt326
     * @date 2022-09-22 22:26
     */
    public static function withdrawThird($fromtcid, $uid = null)
    {
        //Utils::L("{$fromtcid}/withdtaw",['提现：',$fromtcid,$uid]);
        $rtn = false;
        $uid = $uid ?? Params::$uid;
        //获取from余额
        $ban = self::getThirdBalance($fromtcid, $uid, true);
        if ($ban && $ban > 0) {
            $last = UserBalanceTranslog::where('ubtl_uid', $uid)->where('ubtl_status', 3)->order('ubtl_id', 'desc')->limit(1)->find();
            if (!$last) {
                $dt = [
                    'ubtl_type' => 4,
                    'ubtl_tcid' => $fromtcid,
                    'ubtl_uid' => $uid,
                    'ubtl_oldban' => 0,
                    'ubtl_newban' => $ban,
                    'ubtl_amount' => $ban,
                    'ubtl_intime' => time(),
                    'ubtl_status' => 1
                ];
            } else {
                $dt = [
                    'ubtl_type' => 4,
                    'ubtl_tcid' => $fromtcid,
                    'ubtl_uid' => $uid,
                    'ubtl_oldban' => $last['ubtl_newban'],
                    'ubtl_newban' => $last['ubtl_newban'] + $ban,
                    'ubtl_amount' => $ban,
                    'ubtl_intime' => time(),
                    'ubtl_status' => 1
                ];
            }
            $transid = Db::name('user_balance_translog')->insertGetId($dt);
            if ($transid) {
                //提现到平台
                $cfgs = Games::getConfigs(null, $fromtcid);
                $ohterkvs = [
                    'uid' => $uid,
                    'o_amount' => $ban,
                    'o_transid' => $transid
                ];
                $rlt = Third::run($cfgs, 'WITHDRAW', $ohterkvs);
                if ($rlt) {
                    //TODO if $rlt!=$ban 说明实际提现金额与预期不符，需要记录日志并通知管理员（并非必要，除非实际有发生这样的情况，根据情况处理）
                    $rtn = $ban;
                    Db::startTrans();
                    try {
                        $flag = UserBalanceTranslog::where('ubtl_id', $transid)->find()->save([
                            'ubtl_status' => 3
                        ]);
                        if ($flag) {
                            $utrd = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $fromtcid)->find();
                            if ($utrd) {
                                $utrd->ut_state = 0;
                                $utrd->ut_lastout = time();
                                $utrd->ut_balance = 0;
                                $utrd->ut_balanceout = $ban;
                                $flag = $utrd->save();
                                if ($flag) {
                                    $flag = User::accountChange_old($uid, $ban, '+', 101, '三方平台[' . $fromtcid . ']提现');
                                    //UserModel::where('id',$uid)->inc('balance',$ban)->update();
                                }
                            } else {
                                $flag = false;
                            }
                        }
                        if ($flag) {
                            Db::commit();
                        } else {
                            Db::rollback();
                        }

                    } catch (\Exception $ex) {
                        $rtn = false;
                        Db::rollback();
                        Utils::L('ThirdErrLogs', [
                            'msg' => '三方平台提现已成功，但余额和变账记录未生成，转账记录状态也未变更',
                            'suggest' => '将转账记录状态变更为3，用户余额增加并增加变账记录',
                            'track' => [
                                'ubtl_id' => $transid,
                                'withdraw_amount' => $ban,
                                'withdraw_uid' => $uid,
                                'from_tcid' => $fromtcid,
                                'exception' => $ex->getMessage()
                            ]]);
                    }
                } else {
                    $rtn = false;
                    $flag = false;
                    UserBalanceTranslog::where('ubtl_id', $transid)->find()->save([
                        'ubtl_status' => 2
                    ]);
                    //$backflag=User::accountChange($uid,$changeban,'+',3,'充值到三方平台失败退回[三方平台：'.$totcid.'，充值记录：'.$transid.']');

                    Utils::L('ThirdErrLogs', [
                        'msg' => '三方平台提现未成功，可能需要检查',
                        'suggest' => '查询三主平台提现记录是否成功，如成功需要增加用户平台余额',
                        'track' => [
                            'ubtl_id' => $transid,
                            'withdraw_amount' => $ban,
                            'withdraw_uid' => $uid,
                            'from_tcid' => $fromtcid
                        ]]);
                }
            } else {
                $rtn = false;
            }
        }
        return $rtn;
    }

    /**
     * 对于转账到三方平台时发生错误的的后续处理
     * 必须是转账失败的记录(日志状态为 1未知原因导致可能的失败 或2 逻辑明确转账失败)
     * @param $transid 转账ID
     * @param bool $backmoney 是否退回余额到平台用户钱包 默认false
     * @param string $loginfo 要记入处理日志的信息，建议是处理人姓名和理由之类的
     * @return string|true 返回===true表示处理成功 ，否则返回处理失败的提示信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2023-01-18 1:40
     */

    public static function rollbackDepositThird($transid, $backmoney = false, $loginfo = '')
    {
        $rec = UserBalanceTranslog::where('ubtl_id', $transid)->find();
        if ($rec) {
            if ($rec->ubtl_type == 3 && ($rec->ubtl_status == 2 || $rec->ubtl_status == 1)) {
                Db::startTrans();
                try {
                    $flag = true;
                    if ($backmoney) {
                        $flag = User::accountChange_old($rec->ubtl_uid, $rec->ubtl_amount, '+', 3, '充值到三方平台失败退回[三方平台：' . $rec->ubtl_tcid . '，充值记录：' . $transid . ']');
                    }
                    if ($flag) {
                        $rec->ubtl_status = $backmoney ? 4 : 5;
                        $rec->ubtl_errproc = time();
                        $rec->ubtl_errprocinfo = $loginfo;
                        $rec->save();
                        Db::commit();
                        return true;
                    } else {
                        Db::rollback();
                        return "处理失败：用户余额增加失败！";
                    }
                } catch (\Exception $ex) {
                    Db::rollback();
                    return "处理错误：" . $ex->getMessage();
                }
            } else {
                return "当前转账记录不符合恢复条件（记录类型：平台转三方，转账状态：失败或准备状态）";
            }
        } else {
            return "没有找到需要恢复的转账记录";
        }
    }

    /**
     * 从平台转出余额到 $totcid 指定的三方平台
     * @param $totcid
     * @param $uid default null 为NULL表示从Pamars中获取
     * @param float $price default 0 默认转全部余额，指定值则传指定余额,负数表示不转
     * @return bool|float
     * @author twt326
     * @date 2022-09-22 22:26
     */
    public static function depositThird($totcid, $uid = null, $price = 0)
    {
        Utils::L("{$totcid}/deposit", ['充值：', $totcid, $uid]);
        $rtn = false;
        $uid = $uid ?? Params::$uid;
        Utils::L("{$totcid}/deposit", ['充值：进入：', $totcid, $uid]);
        $price = floatval($price);
        if ($price < 0) {
            $toban = self::getThirdBalance($totcid, $uid);
            return [
                'targetban' => $toban,
                'transamount' => 0
            ];
        }
        $flag = false;
        $tflag = false;
        Db::startTrans();
        $tflag = true;
        try {
            //获取平台余额
            $ban = UserModel::where('id', $uid)->lock(true)->value('balance');
            //Utils::L("{$totcid}/deposit",['充值：获取平台余额：',$ban]);
            $changeban = 0;
            if ($ban && $ban > 0) {
                if ($price > 0 && $price <= $ban) {
                    $changeban = $price;//如果指定转入金额的情况
                } else {
                    $changeban = $ban;
                }
                $cfgs = Games::getConfigs(null, $totcid);
                //汇率尽量对齐
                Third::initExtClass($cfgs);
                if (isset($cfgs['trdcfg']['tc_exchangeint']) && $cfgs['trdcfg']['tc_exchangeint'] == 1) {
                    $thirdcurrency = floor(Third::transExchangerate($changeban, 1));//如果三方要求充值整数
                } else {
                    $thirdcurrency = intval(Third::transExchangerate($changeban, 1) * 100) / 100;
                }
                $changeban = intval(Third::transExchangerate($thirdcurrency, 0) * 100) / 100;
                $dt = [
                    'ubtl_type' => 3,
                    'ubtl_tcid' => $totcid,
                    'ubtl_uid' => $uid,
                    'ubtl_oldban' => $ban,
                    'ubtl_newban' => $ban - $changeban,
                    'ubtl_amount' => $changeban,
                    'ubtl_intime' => time(),
                    'ubtl_status' => 1
                ];

                $transid = Db::name('user_balance_translog')->insertGetId($dt);
                if ($transid) {
                    /*$flag=UserModel::where('id',$uid)->find()->save([
                     'balance'=>0
                     ]);*/
                    $flag = User::accountChange_old($uid, $changeban, '-', 100, '充值到三方平台[' . $totcid . ']');
                    if ($flag) {
                        Db::commit();
                    } else {
                        $flag = false;
                        Db::rollback();
                        return [
                            'targetban' => 0,
                            'transamount' => false
                        ];
                    }
                    $tflag = false;
                    //充值到三方
                    //$cfgs=Games::getConfigs(null,$totcid);
                    $ohterkvs = [
                        'uid' => $uid,
                        'o_amount' => $changeban,
                        'o_transid' => $transid
                    ];
                    $rlt = Third::run($cfgs, 'DEPOSIT', $ohterkvs);
                    if ($rlt) {
                        //TODO 如果返回结果的充值金额与预期不符，需要记录日志并通知管理员（并非必要，除非实际有发生这样的情况，根据情况处理）
                        Db::startTrans();
                        $tflag = true;

                        $flag = UserBalanceTranslog::where('ubtl_id', $transid)->find()->save([
                            'ubtl_status' => 3
                        ]);
                        if ($flag) {
                            $utrd = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $totcid)->find();
                            if ($utrd) {
                                $utrd->ut_state = 1;
                                $utrd->ut_lastin = time();
                                $utrd->ut_balance = $utrd->ut_balance + $changeban;
                                $utrd->ut_balancein = $changeban;
                                $flag = $utrd->save();
                                /*if ($flag){
                                 $flag=Users::where('u_id',$uid)->find()->save([
                                 'u_balance'=>0
                                 ]);*/
                                if ($flag) $rtn = $changeban;
                                //}
                            } else {
                                $flag = false;
                            }
                        }


                    } else {
                        $rtn = false;
                        $flag = false;
                        UserBalanceTranslog::where('ubtl_id', $transid)->find()->save([
                            'ubtl_status' => 2
                        ]);
                        //$backflag=User::accountChange($uid,$changeban,'+',3,'充值到三方平台失败退回[三方平台：'.$totcid.'，充值记录：'.$transid.']');
                        User::accountChange_old($uid, 0, '-', 3, '充值到三方平台失败[' . ($totcid == 18 ? 'GW' : $totcid) . ']');
                        Utils::L('ThirdErrLogs', [
                            'msg' => '三方平台充值发生错误,用户余额可能需退回',
                            'suggest' => '如果transid不存在，不用处理，如果存在，则检查对应的变账记录是否存在，如果变账记录存在则需要验证三方平台充值是否成功，如成功，则将转账日志状态设置为3，否则删除转账记录，退回用户余额，变账记录更新',
                            'track' => [
                                'ubtl_id' => $transid,
                                'deposit_amount' => $changeban,
                                'deposit_uid' => $uid,
                                'to_tcid' => $totcid
                            ]]);

                    }
                } else {
                    $rtn = false;
                }
            } else {
                $flag = false;
                $rtn = 0;
            }
            if ($flag) {
                if ($tflag) Db::commit();
            } else {
                if ($tflag) Db::rollback();
            }
        } catch (\Exception $ex) {
            $rtn = false;
            Db::rollback();
            Utils::L('ThirdErrLogs', [
                'msg' => '三方平台充值发生错误',
                'suggest' => '如果transid不存在，不用处理，如果存在，则检查对应的变账记录是否存在，如果变账记录存在则需要验证三方平台充值是否成功，如成功，则将转账日志状态设置为3，否则删除转账记录，退回用户余额，变账记录更新',
                'track' => [
                    'ubtl_id' => $transid ?? '',
                    'deposit_amount' => $changeban ?? '',
                    'deposit_uid' => $uid,
                    'to_tcid' => $totcid,
                    'exception' => $ex->getMessage()
                ]]);
        }
        $toban = self::getThirdBalance($totcid, $uid);
        return [
            'targetban' => $toban,
            'transamount' => $rtn
        ];
        //return $rtn;
    }

    public static function depositThird_oldbak($totcid, $uid = null)
    {
        Utils::L("{$totcid}/deposit", ['充值：', $totcid, $uid]);
        $rtn = false;
        $uid = $uid ?? Params::$uid;
        Utils::L("{$totcid}/deposit", ['充值：进入：', $totcid, $uid]);
        $flag = false;
        $tflag = false;
        Db::startTrans();
        $tflag = true;
        try {
            //获取平台余额
            $ban = UserModel::where('id', $uid)->lock(true)->value('balance');
            //Utils::L("{$totcid}/deposit",['充值：获取平台余额：',$ban]);
            $changeban = 0;
            if ($ban && $ban > 0) {
                $cfgs = Games::getConfigs(null, $totcid);
                //汇率尽量对齐
                Third::initExtClass($cfgs);
                $changeban = intval(Third::transExchangerate(intval(Third::transExchangerate($ban, 1) * 100) / 100, 0) * 100) / 100;
                $dt = [
                    'ubtl_type' => 3,
                    'ubtl_tcid' => $totcid,
                    'ubtl_uid' => $uid,
                    'ubtl_oldban' => $ban,
                    'ubtl_newban' => $ban - $changeban,
                    'ubtl_amount' => $changeban,
                    'ubtl_intime' => time(),
                    'ubtl_status' => 1
                ];

                $transid = Db::name('user_balance_translog')->insertGetId($dt);
                if ($transid) {
                    /*$flag=UserModel::where('id',$uid)->find()->save([
                     'balance'=>0
                     ]);*/
                    $flag = User::accountChange_old($uid, $changeban, '-', 100, '充值到三方平台[' . $totcid . ']');
                    if ($flag) {
                        Db::commit();
                    } else {
                        $flag = false;
                        Db::rollback();
                    }
                    $tflag = false;
                    //充值到三方
                    //$cfgs=Games::getConfigs(null,$totcid);
                    $ohterkvs = [
                        'uid' => $uid,
                        'o_amount' => $changeban,
                        'o_transid' => $transid
                    ];
                    $rlt = Third::run($cfgs, 'DEPOSIT', $ohterkvs);
                    if ($rlt) {
                        Db::startTrans();
                        $tflag = true;
                        $flag = UserBalanceTranslog::where('ubtl_id', $transid)->find()->save([
                            'ubtl_status' => 3
                        ]);
                        if ($flag) {
                            $utrd = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $totcid)->find();
                            if ($utrd) {
                                $utrd->ut_state = 1;
                                $utrd->ut_lastin = time();
                                $utrd->ut_balance = $utrd->ut_balance + $changeban;
                                $utrd->ut_balancein = $changeban;
                                $flag = $utrd->save();
                                /*if ($flag){
                                 $flag=Users::where('u_id',$uid)->find()->save([
                                 'u_balance'=>0
                                 ]);*/
                                if ($flag) $rtn = $changeban;
                                //}
                            } else {
                                $flag = false;
                            }
                        }


                    }
                } else {
                    $rtn = false;
                }
            } else {
                $flag = false;
                $rtn = 0;
            }
            if ($flag) {
                if ($tflag) Db::commit();
            } else {
                if ($tflag) Db::rollback();
            }
        } catch (\Exception $ex) {
            Db::rollback();
            Utils::L('ThirdErrLogs', [
                'msg' => '三方平台充值发生错误',
                'suggest' => '如果transid不存在，不用处理，如果存在，则检查对应的变账记录是否存在，如果变账记录存在则需要验证三方平台充值是否成功，如成功，则将转账日志状态设置为3，否则删除转账记录，退回用户余额，变账记录更新',
                'track' => [
                    'ubtl_id' => $transid,
                    'deposit_amount' => $changeban,
                    'deposit_uid' => $uid,
                    'to_tcid' => $totcid,
                    'exception' => $ex->getMessage()
                ]]);
        }
        $toban = self::getThirdBalance($totcid, $uid);
        return [
            'targetban' => $toban,
            'transamount' => $rtn
        ];
        //return $rtn;
    }

    /**查询 $fromtcid 指定三方平台的余额
     *
     * @param $fromtcid
     * @param $uid default null 为NULL表示从Pamars中获取
     * @param $byWithdraw 是否是提现时查询余额，默认false，此参数非在框架底层调用时请保持false
     * @return bool|float
     * @author twt326
     * @date 2022-09-22 22:27
     */
    public static function getThirdBalance($fromtcid, $uid = null, $byWithdraw = false)
    {
        $uid = $uid ?? Params::$uid;
        $cfgs = Games::getConfigs(null, $fromtcid);
        $otherkvs = ['uid' => $uid];
        if ($byWithdraw) $otherkvs['byWithdraw'] = true;//如果三方要求提现整数，这里加参数进行后续处理
        $rlt = Third::run($cfgs, 'GETBALANCE', $otherkvs);
        $amount = false;
        if ($rlt) {
            $amount = $rlt['tcbalance'];
            if ($uid) {
                $temp = UserThirds::where('ut_uid', $uid)->where('ut_tcid', $fromtcid)->find();
                if (empty($temp)) {
                    UserThirds::create([
                        'ut_uid' => $uid,
                        'ut_tcid' => $fromtcid,
                        'ut_balancein' => $amount,
                        'ut_balance' => $amount,
                        'ut_balanceout' => $amount,
                        'ut_intime' => time(),
                        'ut_state' => 0
                    ]);
                } else {
                    //更新用户当前三方余额
                    UserThirds::where('ut_uid', $uid)->where('ut_tcid', $fromtcid)->update([
                        'ut_balance' => $amount
                    ]);
                }
            }
        }
        return $amount;
    }

    /**
     * 给指定用户赠送免费游戏机会
     * @param $uid 用户ID
     * @param $gid 游戏ID
     * @param $betpricecount 赠送免费游戏的总投注金额
     * @param $freebetsDatas 赠送免费游戏的接口中需要的非标准参数
     * @param $otherkvs 其他参数,freebetEndtime如果有应在其中
     * @param $staffid 赠送免费游戏的操作员工ID
     * @return true|array
     */
    public static function freeBetsToUser($uid, $gid, $freebetsDatas, $staffid)
    {
        $flag = true;
        try {
            $apiname = 'GIVEFREEBETS';
            //拿到游戏配置
            $gcfg = Games::getConfigs($gid);
            //判断此游戏是否有配置赠送免费游戏的接口
            if (!isset($gcfg['apis'][$apiname])) {
                return [
                    'code' => 0,
                    'msg' => '当前平台不支持赠送免费游戏'
                ];
            }
            $thirduser = self::registerThirdUser($uid, $gid, $gcfg, []);
            if (!$thirduser) {
                return [
                    'code' => 0,
                    'msg' => '三方平台用户注册失败'
                ];
            }
            $freebetsDatas['runfunc'] = $apiname;
            $st = time();
            $freebetsDatas['freebetStarttime'] = isset($freebetsDatas['freebetStarttime']) ? $freebetsDatas['freebetStarttime'] : $st;
            $freebetsDatas['freebetEndtime'] = isset($freebetsDatas['freebetEndtime']) ? $freebetsDatas['freebetEndtime'] : 0;
            $freebetsDatas['freebetRounds'] = isset($freebetsDatas['freebetRounds']) ? intval($freebetsDatas['freebetRounds']) : 1;
            $freebetsDatas['freebetRounds'] = $freebetsDatas['freebetRounds'] < 1 ? 1 : $freebetsDatas['freebetRounds'];
            $betrate = GameConfigs::getConfigs($gid);
            //处理倍率
            if (!isset($freebetsDatas['betrate']) || !is_int($freebetsDatas['betrate']) || $freebetsDatas['betrate'] <= 0) {
                return [
                    'code' => 0,
                    'msg' => '投注倍率设置不符合要求[要求大于0的整数]'
                ];
            }
            if ($betrate->gc_freebet_rate > 0 && $betrate->gc_freebet_rate != $freebetsDatas['betrate']) {
//                return [
//                    'code'=>0,
//                    'msg'=>'投注倍率不可变更，请联系客服'
//                ];
            }
            if ($betrate->gc_freebet_rate == 0) {
                $betrate->gc_freebet_rate = $freebetsDatas['betrate'];
                $betrate->save();
            }

            $freebetEndtime = $freebetsDatas['freebetEndtime'];
            $freebetPriceCnt = $freebetsDatas['freebetPriceCnt'];
            //判断是否有附加API模板
            $tpldata = null;
            $tplo = GameApisExtplModel::whereIn('gae_gid', [$gid, 0])->where('gae_tcid', $gcfg['trdcfg']['tc_id'])->where('gae_apiname', $apiname)->select()->toArray();
            if (count($tplo) > 0) {
                //有通用模板和游戏指定模板时优先选择游戏指定模板
                $idx = count($tplo) == 1 ? 0 : ($tplo[0]['gae_gid'] == $gid ? 0 : 1);
                $validateFlag = GameApisExtplModel::procClearTemps($tplo[$idx], $freebetsDatas);
                if ($validateFlag !== true) {
                    return $validateFlag;
                }
                $trdobj = Third::initExtClass($gcfg);
                $freebetsDatas['freebetPriceCnt'] = Third::transExchangerate($freebetsDatas['freebetPriceCnt'], 1, 0);
                $trdobj->transOtherKvs($freebetsDatas);
                $tpldata = [
                    'get' => Meta::parseMateData(Meta::cusParseMateData($freebetsDatas, $tplo[$idx]['gae_param_gettpl'], false), $gcfg['trdcfg']['tc_id'], $gid, $uid),
                    'post' => Meta::parseMateData(Meta::cusParseMateData($freebetsDatas, $tplo[$idx]['gae_param_posttpl'], false), $gcfg['trdcfg']['tc_id'], $gid, $uid),
                    'head' => Meta::parseMateData(Meta::cusParseMateData($freebetsDatas, $tplo[$idx]['gae_param_headtpl'], false), $gcfg['trdcfg']['tc_id'], $gid, $uid)
                ];
            }
            Utils::L('freebets', ["处理后的参数模板", $tpldata]);
            //写入免费赠送记录
            $data = [
                'ufg_uid' => $uid,
                'ufg_tcid' => $gcfg['trdcfg']['tc_id'],
                'ufg_gid' => $gid,
                'ufg_freerounds' => $freebetsDatas['freebetRounds'],
                'ufg_intime' => $st,
                'ufg_endfreetime' => $freebetEndtime,
                'ufg_pricecount' => $freebetPriceCnt,
                'ufg_roundspricecount' => $freebetsDatas['freebetRounds'] * $freebetPriceCnt,
                'ufg_betsinfo' => $tpldata == null ? json_encode($freebetsDatas) : json_encode($tpldata),
                'ufg_staffid' => $staffid
            ];
            $idsn = UserFreebetsModel::insertGetId($data);
            //合并参数
            $kvs = array_merge($freebetsDatas, ['uid' => $uid, 'tcid' => $gcfg['trdcfg']['tc_id'], 'gid' => $gid, 'freesn' => $idsn,
                'apitpl' => $tpldata]);
            Utils::L('freebets', ["合并后的参数列表", $kvs]);
            //调取三方接口
            $flag = Third::run($gcfg, $apiname, $kvs);
            $ukvs = [];
            //更新赠送成功
            /*if($flag){
                //添加用户到免费回合
                $ukvs=["freesn"=>$idsn,'uid'=>$uid];
                $flag=Third::run($gcfg,'CREATEFREEBETUSER',$ukvs);
                if ($flag) {
                    UserFreebetsModel::find($idsn)->update(['ufg_state' => 1]);
                }
            }*/
            if ($flag) {
                $flag = UserFreebetsModel::find($idsn);
                Utils::L('freebets', ["准备更新赠送记录", $idsn, $flag]);
                if ($flag) {
                    $flag->ufg_state = 1;
                    $flag->save();
                } else {
                    Utils::L('freebetsErr', ['=====赠送执行失败=====', $uid, $gid, $freebetsDatas, $staffid, $ukvs, $idsn]);
                }
            } else {
                Utils::L('freebetsErr', ['=====赠送执行失败=====', $uid, $gid, $freebetsDatas, $staffid, $ukvs]);
            }
            return $flag ? true : false;
        } catch (\Exception $ex) {
            Utils::L('freebetsErr', ['=====赠送执行错误=====', $uid, $gid, $freebetsDatas, $staffid, $ex->getMessage(), $ex->getTraceAsString()]);
            return false;
        }
    }

    /** 根据指定字段值获取游戏信息 未来可扩展
     * @param int $tcid 三方平台ID
     * @param mixed $kv 指定字段的值
     * @param string $type id,code,tag
     * @param string $field 要返回的字段，所有字段：null 指定字段：逗号隔开的字段名
     * @return null
     * @author twt326
     * @date 2022-10-06 16:51
     */
    public static function getGamesInfoBy($tcid, $kv, $type = 'id', $field = 'g_tag')
    {
        $rlt = null;
        self::buildGamesInfoCache();
        if (in_array($type, ['id', 'code', 'tag'])) {
            $c = Redis::hget("trd_gamesinfo_{$tcid}_{$type}s", $kv);
            if ($c) {
                $c = json_decode($c, true);
                if ($field) {
                    $field = explode(',', $field);
                    if (count($field) == 1) {
                        return isset($c[$field[0]]) ? $c[$field[0]] : null;
                    } else {
                        $rlt = [];
                        foreach ($field as $v) {
                            if (isset($c[$v])) {
                                $rlt[$v] = $c[$v];
                            }
                        }
                    }
                } else {
                    return $c;
                }
            } else {
                self::buildGamesInfoCache();
                $c = Redis::hget("trd_gamesinfo_{$tcid}_{$type}s", $kv);
                if ($c) {
                    $rlt = self::getGamesInfoBy($tcid, $kv, $type, $field);
                }
            }
        }
        return $rlt;
    }

    public static function buildGamesInfoCache($rebuild = false)
    {
        $c = Redis::get('trd_gamesinfo');
        if ($rebuild || !$c) {
            if (!$c || $c && time() - $c > 3) {//避免高并发下重复执行
                Redis::set('trd_gamesinfo', time());
                $games = Games::where('g_state', 1)->where('g_is_del', 0)->field('g_id,g_tcid,g_code,g_tag,g_cj,g_state,g_exfield')->select();
                if ($games) {
                    $games = $games->toArray();

                    foreach ($games as $v) {
                        $vstr = json_encode($v);
                        Redis::hset('trd_gamesinfo_' . $v['g_tcid'] . '_ids', $v['g_id'], $vstr);
                        Redis::hset('trd_gamesinfo_' . $v['g_tcid'] . '_codes', $v['g_code'], $vstr);
                        Redis::hset('trd_gamesinfo_' . $v['g_tcid'] . '_tags', $v['g_tag'], $vstr);
                    }


                    /** 不同平台如果tag或code有重复的情况下会导致缓存逻辑上存在覆盖的问题
                     * $ids = array_column($games, null, 'g_id');
                     * foreach ($ids as $k=>$v) {
                     * Redis::hset('trd_gamesinfo_'.$v['g_tcid'].'_ids',$k,json_encode($v));
                     * }
                     * $codes = array_column($games, null, 'g_code');
                     * foreach ($codes as $k=>$v) {
                     * Redis::hset('trd_gamesinfo_'.$v['g_tcid'].'_codes',$k,json_encode($v));
                     * }
                     * $tags = array_column($games, null, 'g_tag');
                     * foreach ($tags as $k=>$v) {
                     * Redis::hset('trd_gamesinfo_'.$v['g_tcid'].'_tags',$k,json_encode($v));
                     * }
                     **/
                }
            }
        }
    }

    /**
     * 取得注单详细结果连结
     * @param $gb_id
     * @return void
     */
    public static function getBetDetail($gb_id)
    {
        $betInfo = Db::table('dq_game_bets_hot')->where('gb_id', $gb_id)->find();
        if (empty($betInfo)) {
            $betInfo = Db::table('dq_game_bets')->where('gb_id', $gb_id)->find();
        }
        if (empty($betInfo)) {
            return false;
        }
        if (empty($betInfo['gb_betinfo'])) {
            $ex = GameBetsex::where('gbe_id', $gb_id)->find();
            if ($ex) {
                $betInfo['gb_betinfo'] = $ex['gbe_betinfo'];
            }
        }
        $cfgs = Games::getConfigs(null, $betInfo['gb_tcid']);
        $ohterkvs = ['uid' => $betInfo['gb_uid'], 'betInfo' => $betInfo, 'gbsn' => $betInfo['gb_sn']];
        if (isset($cfgs['apis']['GETBETDETAIL'])) {
            $rlt = Third::run($cfgs, 'GETBETDETAIL', $ohterkvs);
            return $rlt['tcgamedetailurl'] ?? false;
        } else {
            return Third::run($cfgs, 'parsebetinfo', $ohterkvs);
        }
    }

    /**
     * 设置用户rtp
     * @param $tcid 平台id
     * @param $userIds 用户id数组
     * @param int $rtp RTP
     * @return string|null
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setUserRtp($tcid, $userIds, $rtp = 0)
    {
        $cfgs = Games::getConfigs(null, $tcid);
        $ohterkvs = ['uid' => 0, 'user_ids' => $userIds, 'rtp' => $rtp];
        if (isset($cfgs['apis']['SETUSERRTP'])) {
            $rlt = Third::run($cfgs, 'SETUSERRTP', $ohterkvs);
            return $rlt['successNum'] ?? $rlt;
        } else {
            return false;
        }
    }

    /**
     * 单个获取用户rtp
     * @param $tcid 平台id
     * @param $uid 用户id
     * @return string|null
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getOneUserRtp($tcid, $uid)
    {
        $cfgs = Games::getConfigs(null, $tcid);
        $ohterkvs = ['uid' => $uid];
        if (isset($cfgs['apis']['GETONEUSERRTP'])) {
            $rlt = Third::run($cfgs, 'GETONEUSERRTP', $ohterkvs);
            return $rlt['returnData'] ?? 0;
        } else {
            return false;
        }
    }

    /**
     * 执行third方法
     *
     * @param $func
     * @param $otherkvs
     * @param $tcid
     * @param $gid gid和tcid有一个就行
     * @return void
     */
    public static function thirdRun($func, &$otherkvs, $tcid = null, $gid = null)
    {
        $cfg = Games::getConfigs($gid, $tcid);
        if ($cfg) {
            Third::run($cfg, $func, $otherkvs);
        }
    }

}
