<?php

namespace app\common\service;

use app\api\factory\ActionFactory;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Game;
use app\common\lib\Respons;
use app\common\model\ActionApplyModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\GameBetsModel;
use app\common\model\GameInfosModel;
use app\common\model\PddWithdrawalRecordModel;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\SignRecordModel;
use app\common\model\TagModel;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use app\common\model\TaskStageRecordModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserAgentConfigModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserGoogleModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserLoginModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserTagModel;
use app\common\model\UserVerifyModel;
use app\common\model\UserWaterModel;
use app\common\model\UserWithdrawalModel;
use app\common\redis\CommomRedis;
use app\common\Utils;
use app\model\GameBets;
use app\model\ThirdConfigs;
use think\facade\Cache;
use think\facade\Db;

class MemberService
{

    // 会员提现总计缓存key
    const WITHDRAW_CACHE_KEY = 'member:withdraw';

    // 会员充值总计缓存key
    const RECHARGE_CACHE_KEY = 'member:recharge';

    /**
     * 获取所有IP登陆重复用户数
     * @return type
     */
    public static function getLoginIpTotal()
    {
        $redis = Cache::store('redis');
        $redis_key = "MemberService:getLoginIpTotal";
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        $result = UserLoginModel::field('COUNT(DISTINCT uid) AS total, login_ip')
            ->group('login_ip')->having('total > 1')
            ->order('total DESC')->select()
            ->column('total', 'login_ip');
        $redis->set($redis_key, $result, 86400);
        return $result;
    }

    /**
     * 判断是否是重复用户IP
     * @param type $ip
     * @return type
     */
    public static function isRepeatByIP($ip)
    {
        $count = 0;
        if (!empty($ip)) {
            $result = UserLoginModel::field("login_ip, GROUP_CONCAT(uid SEPARATOR ',') AS uids")->where('login_ip', $ip)->group('login_ip')->find();
            if ($result) {
                $uids = array_unique(explode(',', $result['uids']));
                $count = count($uids);
            }
        }
        return $count;
        /*$ips = self::getLoginIpTotal();
        if (is_array($ip)) {
            $result = 0;
            foreach ($ip as $_ip) {
                if (isset($ips[$_ip])) {
                    $result = $ips[$_ip];
                    break;
                }
            }
            return $result;
        } else {
            return $ips[$ip] ?? 0;
        }*/
    }

    /**
     * @param array $user
     * @param string $code
     * @param string $check_type
     * @return bool
     * @throws \app\common\exception\MessageException
     */
    public static function checkCode($user, $code, $check_type)
    {
        $ApiRedis = new ApiRedis();
        $isVerify = UserGoogleModel::isVerfit($user['id'], $check_type);
        if ($isVerify) {
            $google = UserGoogleModel::verification($user['id'], $check_type, $code);
            if ($google) {
                ajax_return($google, 110);
            }
        } elseif ($user['mail'] || $user['phone']) {
            if (env('account_first_check', 'email') == 'email') {
                $redis_key = ($user['mail'] ?: $user['phone']) . "_code";
            } else {
                $redis_key = ($user['phone'] ?: $user['mail']) . "_code";
            }
            $redisCode = $ApiRedis->get($redis_key);
            if (empty($code) || $redisCode != $code) {
                ajax_return("验证码错误", 120);
            }
            $ApiRedis->delete($redis_key);
        } else {
            ajax_return("请先绑定邮箱或手机号码", 130);
        }

        return true;
    }

    /**
     * 获取用户字段信息
     * @param $uid
     * @param $field
     * @return array|mixed|string|Db|\think\Model
     */
    public static function getUserByUid($uid, $field = null)
    {
        if (empty($uid)) {
            return '';
        }
        try {
            $user = Db::name('user')->where('id', $uid)->field($field ?? '*')->find();
            if (empty($user)) {
                return '';
            }
            return is_string($field) && isset($user[$field]) ? $user[$field] : $user;
        } catch (\Exception $exception) {
            return $uid;
        }
    }

    /**
     * 获取账号
     * @param $uid
     * @return mixed|string
     */
    public static function getUsernameByUid($uid)
    {
        $user = self::getUserByUid($uid, 'mail,phone,username');
        if (empty($user)) {
            return '';
        }

        return $user['mail'] ?? ($user['phone'] ?? $user['username']);
    }


    /**
     * 根据树节点获取顶级代理账号
     * @param $tree
     * @return mixed|string
     */
    public static function getTopAgentByTree($tree, $isUid = false)
    {
        $tree_arr = explode("/", trim($tree, '/'));
        if (count($tree_arr) <= 1) { // 树节点1个时说明自己就是顶级代理
            return '';
        }
        if ($isUid) {
            return $tree_arr[0];
        }

        $user = self::getUserByUid($tree_arr[0], 'mail,phone,username');
        if (empty($user)) {
            return '';
        }

        return $user['mail'] ?? ($user['phone'] ?? $user['username']);
    }


    /**
     * 获取用户字段信息
     * @param $uid
     * @param $field
     * @return array|mixed|string|Db|\think\Model
     */
    public static function getUidByKeyword($keyword)
    {
        if (empty($keyword)) {
            return 0;
        }
        try {
            $where_field = is_numeric($keyword) ? 'id|username|mail|phone' : 'username|mail|phone';
            return UserModel::where([[$where_field, '=', $keyword]])->value("id");
        } catch (\Exception $exception) {
            return $keyword;
        }
    }

    /**
     * @param $uid
     * @return void
     */
    public static function getTagByUid($uid)
    {
        $redis = new CommomRedis();
        $redis_key = "MemberService:getTagByUid:v6:{$uid}";
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        if (empty($uid)) {
            return [];
        }
        $tag_id = UserTagModel::where('uid', $uid)->distinct(true)->column('tag_id');
        if (empty($tag_id)) {
            return [];
        }
        $tags = TagModel::where('id', 'in', $tag_id)->order('id DESC')->limit(4)->field('name,color')->select();
        $redis->set($redis_key, $tags, 300);
        return $tags;
    }


    /**
     * @param $uid
     * @return void
     */
    public static function clearCacheTagByUid($uid)
    {
        $redis = new CommomRedis();
        $redis_key = "MemberService:getTagByUid:v6:{$uid}";
        $redis->delete($redis_key);
    }

    /**
     * 用户标签禁止某一些动作
     * @param $uid
     * @param $type
     * @return bool
     */
    public static function tagBan($uid, $type)
    {
        if (!in_array($type, ['is_withdraw', 'is_recharge', 'is_activity', 'is_game', 'is_reward', 'is_task'])) {
            return false;
        }
        $tag_id = self::getUserTagId($uid);
        if (empty($tag_id)) {
            return false;
        }
        $val = TagModel::where('id', 'in', $tag_id)->where($type, -1)->value($type);
        return $val == -1;
    }

    /**
     * 用户标签禁止用户提现
     * @param $uid
     * @return bool
     */
    public static function tagBanWithdraw($uid)
    {
        return self::tagBan($uid, 'is_withdraw');
    }

    /**
     * 用户标签限制用户充值
     * @param $uid
     * @return bool
     */
    public static function tagBanRecharge($uid)
    {
        $tag_id = self::getUserTagId($uid);
        if (empty($tag_id)) {
            return false;
        }
        $val = TagModel::where('id', 'in', $tag_id)->where('is_recharge', '<>', 0)->order('is_recharge ASC')->value('is_recharge');
        return $val;
    }


    /**
     * 获取用户标签
     * @param $uid
     * @return bool
     */
    public static function getUserTagId($uid)
    {
        $tag_id = UserTagModel::where('uid', $uid)->distinct(true)->column('tag_id');
        return empty($tag_id) ? [] : $tag_id;
    }

    /**
     * 清零用户流水
     * @param int|array $uids
     * @param $admin_name
     * @param $field
     * @return false|void
     */
    public static function cleanSavings($uids, $msg = "清零流水", $admin_name = '', $field = 'savings')
    {
        $where = [];
        if (is_numeric($uids)) {
            $where['id'] = $uids;
        } elseif (is_array($uids)) {
            $where[] = ['id', 'IN', $uids];
        } else {
            return false;
        }
        UserModel::where($where)->update([$field => 0]);
        //清除指定流水

        //真金钱包才有指定流水（暂时，后续还会增加彩金钱包的指定流水）
        if ($field == 'savings') {
            $_list = UserAwardCfgsModel::where('uac_uid', 'in', $uids)->where('uac_done', '=', 0)->select();
            $appointWater = 0;
            foreach ($_list as $_v) {
                $detail = UserAwardCfgsModel::where('uac_id', '=', $_v['uac_id'])->find();
                if (!$detail) {
                    continue;
                }
                $appointWater += $detail->uac_water - $detail->uac_waterdone;
                $detail->uac_done = time();
                $detail->uac_waterdone = $detail->uac_water;
                $detail->save();
            }
            if ($appointWater) {
                $msg .= ",指定流水:" . $appointWater;
            }
        }
        UserAdminLogModel::addLogs($uids, $msg, $admin_name);
    }

    /**
     * 获取用户所有余额
     * @param int $uid 用户ID
     * @return float|int|string
     */
    public static function getAllBalance($uid, $isContainChild = false, $timeWhere = [])
    {
        if (empty($uid)) {
            return 0;
        }
        if ($isContainChild) {
            //是否包含下级
            $userDetail = UserModel::alias('a')->where('id', '=', $uid)->where($timeWhere)->find();
            if ($userDetail) {
                $sql = UserModel::where([['tree', 'like', $userDetail['tree'] . '%']])->field('id')->buildSql();
                $whereStr = 'id in (' . $sql . ')';
                $whereStr1 = 'gb_uid in (' . $sql . ')';
                $whereStr2 = 'ut_uid in (' . $sql . ')';
            } else {
                return 0;
            }
        } else {
            $whereStr = 'id = ' . $uid;
            $whereStr1 = 'gb_uid = ' . $uid;
            $whereStr2 = 'ut_uid = ' . $uid;
        }
        $balance = UserModel::where($whereStr)->sum(Db::raw('`live_fund_money`+`balance`'));
//        $game_price = GameBets::where($whereStr1)->where('gb_winlose', 1)->sum('gb_price');
        $game_price = 0;
        $ut_balance = Db::name("user_thirds")->where($whereStr2)->sum("ut_balance");
        return sprintf4f(floatval($balance) + floatval($game_price) + floatval($ut_balance));
    }

    /**
     * 用户余额低于配置金额时自动清除用户提现流水限制
     * @param array $user 用户信息数组
     * @param float $thirdBalance 三方总余额
     * @return bool
     */
    public static function clearUserSavings(array $user, $thirdBalance = 0)
    {
        if (empty($user) || !isset($user['id']) || !isset($user['balance']) || !isset($user['savings'])) {
            return false;
        }
        $redis = new ApiRedis();
        $redis_key = "clearUserSavings:{$user['id']}";
        if ($redis->has($redis_key)) {
            return false;
        }
        // 最小金额 大于0时有效 用户余额低于配置金额时自动清除用户提现流水限制
        $min_balance = env("MIN_BALANCE_CLEAN_WATER", 0);
        if ($min_balance <= 0 || $user['savings'] <= 0) {
            return false;
        }
        $redis->set($redis_key, 1, 300);
        //查看是否有未结算的注单
        $isHasNoSettleOrder = false;
        $_condition = [];
        $_condition[] = ['gb_uid', '=', $user['id']];
        $_condition[] = ['gb_winlose', '=', 1];
        if (GameBetsModel::where($_condition)->field('gb_id')->limit(1)->find()) {
            $isHasNoSettleOrder = true;
        }
        if (GameBets::where($_condition)->field('gb_id')->limit(1)->find()) {
            $isHasNoSettleOrder = true;
        }
        if (!$isHasNoSettleOrder && $user['balance'] + $thirdBalance < $min_balance) {
            $all_balance = self::getAllBalance($user['id']);
            if ($all_balance < $min_balance) {
                self::cleanSavings($user['id'], "余额低于{$min_balance}自动清零流水{$user['savings']}", '系统');
                return true;
            }
        }
        return false;
    }

    //清除用户彩金流水
    public static function clearUserCjsavings($user, $uid = 0)
    {
        if ($uid && !$user) {
            $user = UserModel::where('id', '=', $uid)->find();
        }
        if (empty($user) || !isset($user['id']) || !isset($user['cjbalance']) || !isset($user['cjsavings'])) {
            return false;
        }
        $redis = new ApiRedis();
        $redis_key = "clearUsercjSavings:{$user['id']}";
        if ($redis->has($redis_key)) {
            return false;
        }
        // 最小金额 大于0时有效 用户余额低于配置金额时自动清除用户提现流水限制
        $min_balance = env("MIN_CJ_BALANCE_CLEAN_WATER", 0);
        if ($min_balance <= 0 || $user['cjsavings'] <= 0) {
            return false;
        }
        $redis->set($redis_key, 1, 300);

        if ($user['cjbalance'] < $min_balance) {
            self::cleanSavings($user['id'], "彩金余额低于{$min_balance}自动清零彩金流水{$user['cjsavings']}", '系统', 'cjsavings');
            return true;
        }
        return false;
    }

    /**
     * 记录用户流水明细
     * @param int $uid 用户ID
     * @param float|int $water
     * @param int $type 类型：0充值，1返水，2活动奖励，3返佣，4推广奖励，5任务，7人工增加 11,12 赠送彩金,13 领取码奖励 14 VIP升级彩金 15抽奖流水 88真金注单 99彩金注单 100彩金注单赢 101彩金变更（奖励或扣减）
     * @param string $remark
     * @param int $origin_id
     * @param float $amount
     * @param int $multiple
     * @return void
     */
    public static function addUserWaterDetail(int $uid, $water, int $type, string $remark = '', int $origin_id = 0, $amount = 0, int $multiple = 0)
    {
        if (empty($uid) || empty($water) || !is_numeric($type)) {
            return;
        }
        //当前流水，根据类型获取真金还是彩金的流水，类型99,100,101时，获取彩金的流水
        if (in_array($type, [99, 100, 101])) {
            $field = 'cjsavings';
        } else {
            $field = 'savings';
        }
        $user = Db::name('user')->where('id', '=', $uid)->field($field)->find();
        $savings = $user[$field] ?? 0;

        Db::name('user_water')->insert([
            'uid' => $uid,
            'water' => $water,
            'type' => $type,
            'remark' => $remark,
            'origin_id' => $origin_id,
            'amount' => $amount,
            'multiple' => $multiple,
            'current_savings' => $savings,
            'create_time' => time(),
        ]);
    }

    /**
     * 缓存会员充值提现总计
     * @param int $timeWithdrawal
     * @param int $timeRecharge
     * @return void
     */
    public static function buildUserRechargeWithdraw($timeWithdrawal = 0, $timeRecharge = 0)
    {
        $limit = intval(env('TOTAL_RECHARGE_WITHDRAWAL_LIMIT', 3000));
        $redis = new CommomRedis();
        $uidArr = UserWithdrawalModel::where('update_time', '>', $timeWithdrawal)->order('update_time asc')->limit($limit)->column('uid');
        $withdraw = UserWithdrawalModel::whereIn('status', '3,4')
            ->whereIn('uid', $uidArr)
            ->field("SUM(arrival_prices) AS amount,uid")->group('uid')->select();
        //获取最后时间，下次进来接着这个时间执行
        $timeArr = UserWithdrawalModel::whereIn('status', '3,4')
            ->where('update_time', '>', $timeWithdrawal)
            ->order('update_time asc')->limit($limit)->column('update_time');
        $lastTime = $timeArr ? end($timeArr) : $timeWithdrawal;

        foreach ($withdraw as $val) {
            $redis->handle()->hset(self::WITHDRAW_CACHE_KEY, $val['uid'], $val['amount']);
        }
        $uidArr = UserOfflineModel::where('update_time', '>', $timeRecharge)->order('update_time asc')->limit($limit)->column('uid');
        $recharge = UserOfflineModel::whereIn('status', '3,4')
            ->whereIn('uid', $uidArr)
            ->field("SUM(arrival_price) AS amount,uid")->group('uid')->select();
        //获取最后时间，下次进来接着这个时间执行
        $timeArr1 = UserOfflineModel::whereIn('status', '3,4')
            ->where('update_time', '>', $timeRecharge)
            ->order('update_time asc')->limit($limit)->column('update_time');
        $lastTime1 = $timeArr1 ? end($timeArr1) : $timeRecharge;

        foreach ($recharge as $val) {
            $redis->handle()->hset(self::RECHARGE_CACHE_KEY, $val['uid'], $val['amount']);
        }
        return [$lastTime, $lastTime1];
    }

    public static function getWithdrawByUid($uid)
    {
        $redis = new CommomRedis();
        $amount = $redis->handle()->hget(self::WITHDRAW_CACHE_KEY, $uid);
        return intval($amount);
    }

    public static function getRechargeByUid($uid)
    {
        $redis = new CommomRedis();
        $amount = $redis->handle()->hget(self::RECHARGE_CACHE_KEY, $uid);
        return intval($amount);
    }

    /**
     * 根据邀请码获取UID
     * @param $rec_code
     * @return int
     */
    public static function getUidByReCode($rec_code): int
    {
        $redis = new CommomRedis();
        $key = "MEMBER:RECODE2UID";
        $uid = $redis->handle()->hget($key, $rec_code);
        if (empty($uid)) {
            $uid = Db::name('user')->where('rec_code', $rec_code)->value('id');
            if (!empty($uid)) {
                $redis->handle()->hset($key, $rec_code, $uid);
            }
        }
        return intval($uid);
    }

    //获取用户流水详情
    public function getUserWaterDetail()
    {
        $param = request()->param();
        $w_water = Utils::sysInfo("w_water");
        $username = $param['uid'] ?? '';
        if (is_numeric($username)) {
            $uid = UserModel::where('id|phone', $username)->value('id');
        } else {
            $uid = UserModel::where('mail|username', $username)->value('id');
        }

        if (isset($param['set']) && $param['set'] == 1) {
            //充值金额
            $total_price1 = UserOfflineModel::where([['uid', '=', $uid], ['status', 'in', '3,4']])->sum('arrival_price');
            //人工调整金额
            $total_price2 = ArtificialBalanceModel::alias("a")->where([['b.uid', '=', $uid], ['a.a_type', 'in', '1,3']])->join("dq_user_balance_mx b", "a.mx_id=b.id")->sum('a.price');
            //人工调整所需流水
            $total_price2_multiple = ArtificialBalanceModel::alias("a")->where([['b.uid', '=', $uid], ['a.a_type', 'in', '1,3']])->join("dq_user_balance_mx b", "a.mx_id=b.id")->sum('a.water*a.price');
            //代理返佣
            $total_price2_Agent1 = AgentRakeBackModel::where([['uid', '=', $uid], ['status', 'in', '4,3']])->sum('savings');
            $total_price2_Agent2 = AgentRakeBackModel::where([['uid', '=', $uid], ['status', 'in', '4,3']])->sum('money');
            //活动
            $action_apply_price = ActionApplyModel::where('ps_status', '=', '1')->where('uid', '=', $uid)->sum('water');
            $action_apply_price2 = ActionApplyModel::where('ps_status', '=', '1')->where('uid', '=', $uid)->sum(Db::raw('gd_amount+ck_hl_amount'));
            //任务
            $taskPrice = TaskRecordModel::where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->where('receive_status', '=', TaskRecordModel::RECEIVED_STATUS)->where('uid', '=', $uid)->sum('reward_amount_or_integral');
            $taskWater = TaskRecordModel::where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->where('receive_status', '=', TaskRecordModel::RECEIVED_STATUS)->where('uid', '=', $uid)->sum('reward_amount_or_integral*savings');
            //阶段任务
            $taskStagePrice = TaskStageRecordModel::where('uid', '=', $uid)->sum('reward_amount');
            $taskStageWater = TaskStageRecordModel::where('uid', '=', $uid)->sum('reward_amount*savings');
            //等级奖励
            $levelPrice = UserLevelRewardModel::where('uid', '=', $uid)->sum('reward_amount');
            $levelWater = UserLevelRewardModel::alias('a')
                ->join('dq_user_level b', 'b.id=a.level_id')
                ->where('a.uid', '=', $uid)
                ->sum('a.reward_amount*b.savings');
            //推广流水
            $agentSellLogPrice = AgentSellLogModel::where([['uid', '=', $uid], ['status', '=', 3]])->sum('money');
            $agentSellLogWater = AgentSellLogModel::alias('a')
                ->join('dq_agent_setting b', 'b.id=a.tg_set_id')
                ->where([['uid', '=', $uid], ['status', '=', 3]])
                ->sum('money*savings');
            //人工调整金额
            $vipPrice = ArtificialBalanceModel::alias("a")->where([['b.uid', '=', $uid], ['a.a_type', '=', '4']])->join("dq_user_balance_mx b", "a.mx_id=b.id")->sum('a.price');
            //人工调整所需流水
            $vipPriceWater = ArtificialBalanceModel::alias("a")->where([['b.uid', '=', $uid], ['a.a_type', 'in', '4']])->join("dq_user_balance_mx b", "a.mx_id=b.id")->sum('a.water*a.price');
            //签到奖励
            $signPrice = SignRecordModel::where('uid', '=', $uid)->sum('reward_amount');
            $signWater = SignRecordModel::where('uid', '=', $uid)->sum('reward_amount*savings');
            //PDD奖励
            $pddPrice = PddWithdrawalRecordModel::where('uid', '=', $uid)->sum('amount');
            $pddWater = PddWithdrawalRecordModel::where('uid', '=', $uid)->sum('amount*savings');
            //领取码
            $receiveCodePrice = ReceiveCodeRecordModel::where('uid', '=', $uid)->sum('reward_amount');
            $receiveCodeWater = ReceiveCodeRecordModel::where('uid', '=', $uid)->sum('reward_amount*savings');
            //人工添加减少流水
            $manualPrice = ArtificialBalanceModel::where([['uid', '=', $uid], ['a_type', 'in', '8']])->sum('price');
            $manualWater = ArtificialBalanceModel::where([['uid', '=', $uid], ['a_type', 'in', '8']])->sum('price*water');
            $LotteryPrice = UserWaterModel::where([['uid', '=', $uid], ['type', '=', '15']])->sum('amount');
            $LotteryWater = UserWaterModel::where([['uid', '=', $uid], ['type', '=', '15']])->sum('water');
            // 定义各项费用
            $items = [
                [
                    'title' => lang('充值'),
                    'total_price' => $total_price1,
                    'required_price' => $total_price1 * $w_water,
                    'ste' => 1
                ],
                [
                    'title' => lang('人工调整'),
                    'total_price' => $total_price2,
                    'required_price' => $total_price2_multiple,
                    'ste' => 2
                ],
                [
                    'title' => lang('代理返佣'),
                    'total_price' => $total_price2_Agent2,
                    'required_price' => $total_price2_Agent1,
                    'ste' => 3
                ],
                [
                    'title' => lang('活动优惠'),
                    'total_price' => $action_apply_price2,
                    'required_price' => $action_apply_price,
                    'ste' => 4
                ],
                [
                    'title' => lang('推广流水'),
                    'total_price' => $agentSellLogPrice,
                    'required_price' => $agentSellLogWater,
                    'ste' => 5
                ],
                [
                    'title' => lang('任务奖励'),
                    'total_price' => $taskPrice,
                    'required_price' => $taskWater,
                    'ste' => 6
                ],
                [
                    'title' => lang('阶段任务奖励'),
                    'total_price' => $taskStagePrice,
                    'required_price' => $taskStageWater,
                    'ste' => 7
                ],
                [
                    'title' => lang('等级奖励(已被删除的不会计算，修改过的倍数按修改后的算)'),
                    'total_price' => $levelPrice,
                    'required_price' => $levelWater,
                    'ste' => 8
                ],
                [
                    'title' => lang('VIP彩金'),
                    'total_price' => $vipPrice,
                    'required_price' => $vipPriceWater,
                    'ste' => 9
                ],
                [
                    'title' => lang('签到奖励'),
                    'total_price' => $signPrice,
                    'required_price' => $signWater,
                    'ste' => 10
                ],
                [
                    'title' => lang('PDD'),
                    'total_price' => $pddPrice,
                    'required_price' => $pddWater,
                    'ste' => 11
                ],
                [
                    'title' => lang('领取码'),
                    'total_price' => $receiveCodePrice,
                    'required_price' => $receiveCodeWater,
                    'ste' => 12
                ],
                [
                    'title' => lang('人工调整'),
                    'total_price' => $manualPrice,
                    'required_price' => $manualWater,
                    'ste' => 13
                ],
                [
                    'title' => lang('幸运转盘流水'),
                    'total_price' => $LotteryPrice,
                    'required_price' => $LotteryWater,
                    'ste' => 13
                ],
            ];

            // 计算总费用
            $total_price = 0;
            $required_price = 0;
            foreach ($items as $item) {
                $total_price += $item['total_price'];
                $required_price += $item['required_price'];
                $item['total_price'] = sprintf("%.2f", $item['total_price']);
                $item['required_price'] = sprintf("%.2f", $item['required_price']);
            }
            $items[] = [
                'title' => lang('总计'),
                'total_price' => sprintf("%.2f", $total_price),
                'required_price' => sprintf("%.2f", $required_price)
            ];

            // 组合返回结果
            $rsps = ['code' => 1, 'data' => $items];
        } else {
            $unsettlement_price = 0;
            $settled_price = 0;
            $ungame_price = GameBetsModel::where(['gb_uid' => $uid, 'gb_winlose' => 1])->sum('gb_price');
            $game_price = GameBetsModel::where([['gb_uid', '=', $uid], ['gb_winlose', 'in', '2,3,4']])->sum('gb_price');
            $rsps = [
                'code' => 1,
                'data' => [
                    [
                        'title' => lang('注单'),
                        'unsettlement' => sprintf("%.2f", $unsettlement_price),
                        'settled' => sprintf("%.2f", $settled_price),
                        'total_price' => sprintf("%.2f", $unsettlement_price + $settled_price),
                    ],
                    [
                        'title' => lang('游戏注单'),
                        'unsettlement' => sprintf("%.2f", $ungame_price),
                        'settled' => sprintf("%.2f", $game_price),
                        'total_price' => sprintf("%.2f", $game_price + $ungame_price),
                    ],
                ]
            ];
        }
        //获取用户数据
        $userDetail = UserModel::where(['id' => $uid])->find();
        $total_price3 = $userDetail['savings'] ?? 0;
        $withdrawal_rules = Utils::sysInfo("withdrawal_rules");
        $rsps['savings'] = floatval($total_price3);
        $rsps['cjsavings'] = floatval($userDetail['cjsavings']);
        $appointWater = 0;
        if ($userDetail) {
            $appointWater = UserAwardCfgsModel::where('uac_uid', '=', $uid)->sum(Db::raw('uac_water-uac_waterdone'));
        }
        $rsps['appoint_savings'] = $appointWater;
        $rsps['withdrawal_rules'] = $withdrawal_rules == 1 ? 1 : 0;
        $rsps['userDetail'] = $userDetail;
        return Respons::run($rsps);
    }

    //指定流水
    public function appointWater()
    {
        $param = request()->param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $username = $param['uid'] ?? '';
        if (!$username) {
            return Respons::run(0, [], '请输入用户UID/手机号/邮箱');
        }
        if (is_numeric($username)) {
            $uid = UserModel::where('id|phone', $username)->value('id');
        } else {
            $uid = UserModel::where('mail|username', $username)->value('id');
        }
        $condition = [];
        $condition[] = ['uac.uac_uid', '=', $uid];
        $condition[] = ['uac.uac_is_del', '=', 0];
        $field = 'u.id,u.login_ip_count,u.phone,u.mail,uac.*';
        $data = Db::name('user_award_cfgs')->alias('uac')
            ->join('user u', 'u.id=uac.uac_uid')
            ->where($condition)
            ->field($field)
            ->order('uac.uac_intime desc')
            ->page($page, $limit)
            ->select()->toArray();
        foreach ($data as &$v) {
            $v['uac_done'] = $v['uac_done'] > 0 ? 1 : 0;#重新组合格式，原来格式大于零为完成时间
            //查询对应平台或游戏名称 uac_game_type 1为平台，2为游戏
            if ($v['uac_game_type'] == 1) {
                $name = implode('|||', ThirdConfigs::where('tc_id', 'in', $v['uac_game_type_ids'])->column('tc_name'));
            } else {
                $name = implode('|||', GameInfosModel::where('gi_gid', 'in', $v['uac_game_type_ids'])->column('gi_name'));
            }
            $v['game_or_platform'] = $name;
        }
        return Respons::run(1, $data, 'success');
    }

    //获取用户流水列表
    public function getUserWaterList()
    {
        $param = request()->param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $condition[] = ['uid', '=', $param['uid']];
        if (isset($param['wallet_type']) && $param['wallet_type'] !== '') {
            if ($param['wallet_type'] == 1) {
                //彩金钱包
                $condition[] = ['type', 'in', [99, 100, 101]];
            } else {
                //真金钱包
                $condition[] = ['type', 'not in', [99, 100, 101]];
            }
        }
        $data = UserWaterModel::where($condition)
            ->order('create_time desc, id desc')
            ->paginate($limit, false, ['query' => $param])->each(function ($v, $index) use ($param) {
                $v['remark'] = lang($v['remark']);
            });
        foreach ($data as &$v) {
            $walletType = 0;
            //99，100，101为彩金钱包，其余为真金钱包
            if (in_array($v['type'], [99, 100, 101])) {
                $walletType = 1;
            }
            $v['wallet_type'] = $walletType;
        }
        return Respons::run(['data' => $data, 'code' => 1]);
    }

    //获取用户已完成流水
    public static function getUserFinishWater($uid, $where = [])
    {
        $redis = new CommomRedis();
        $redisKey = 'get_user_finish_savings_' . $uid . '_' . json_encode($where);
        //先从缓存获取
        if ($redis->has($redisKey)) {
//            return $redis->get($redisKey);
        }
        //缓存没有从数据库获取
        $condition = [];
        $condition[] = ['su_uid', '=', $uid];
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $water = sprintf("%.2f", Db::name($table['_stats_global'])->where($condition)->where($where)->sum('su_valid_bet'));
        //设置缓存十分钟
        $redis->set($redisKey, $water, 600);
        return $water;
    }

    //获取用户总下注
    public static function getUserTotalBet($uid)
    {
//        $totalBet = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_bet');
//        $totalBet = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_validwater');//有效下注
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $totalBet = Db::name($table['_stats_global'])->where([['su_uid', '=', $uid]])->sum('su_valid_bet');//及时有效下注
        return round($totalBet, 2);
    }

    //获取用户输赢
    public static function getUserLossWin($uid, $where = [])
    {
        $field = "SUM(su_syswin*-1) as total_loss_win"; // 平台盈亏 * -1 = 用户输赢
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $bets = Db::name($table['_stats_global'])->field($field)->where('su_uid', '=', $uid)->where($where)->find();
        return $bets['total_loss_win'] ?? 0;
    }

    //获取活跃人数
    public static function getActiveUserNum($where = [], $where1 = [])
    {
        return UserLoginModel::where($where)->where($where1)->group('uid')->count() ?: 0;
    }

    //获取用户指定游戏会平台流水
    public static function getUserAppointSavings($uid, $startTime = 0, $endTime = 0)
    {
        $condition = [];
        $condition[] = ['uac_uid', '=', $uid];
        if ($startTime) {
            $condition[] = ['uac_intime', '>=', $startTime];
        }
        if ($endTime) {
            $condition[] = ['uac_intime', '<=', $endTime];
        }
        $field = 'sum(uac_water) as water,sum(uac_waterdone) as finish_water';
        $detail = UserAwardCfgsModel::field($field)->where($condition)->group('uac_uid')->find();
        if (!$detail) {
            $detail['water'] = 0;
            $detail['finish_water'] = 0;
        } else {
            $detail['water'] = round($detail['water'], 2);
            $detail['finish_water'] = round($detail['finish_water'], 2);
        }
        //获取指定平台的名称
        $_condition = $condition;
        $_condition[] = ['uac_game_type', '=', 1];
        $platformIdArr = [];
        $platformIdsArr = UserAwardCfgsModel::where($_condition)->column('uac_game_type_ids');
        foreach ($platformIdsArr as $ids) {
            $platformIdArr = array_merge($platformIdArr, explode(',', $ids));
        }
        $platformNameArr = ThirdConfigs::where('tc_id', 'in', $platformIdArr)->column('tc_name');
        //获取指定游戏的名称
        $_condition = $condition;
        $_condition[] = ['uac_game_type', '=', 2];
        $gameIdArr = [];
        $gameIdsArr = UserAwardCfgsModel::where($_condition)->column('uac_game_type_ids');
        foreach ($gameIdsArr as $ids) {
            $gameIdArr = array_merge($gameIdArr, explode(',', $ids));
        }
        $gameNameArr = GameInfosModel::where('gi_gid', 'in', $gameIdArr)->column('gi_name');

        $detail['game_name'] = array_merge($platformNameArr, $gameNameArr);
        return $detail;
    }

    //生成邀请链接
    public static function createShareUrl($recCode, $isAdmin = false)
    {
        //是否关闭邀请链接
        if (env('IS_CLOSE_INVITE_URL', 0) == 1) {
            return '';
        }
        //如果代理设置了站点权限，则直接返回站点配置中的域名
        $agentId = UserModel::where('rec_code', '=', $recCode)->value('id');
        if ($agentId) {
            $condition = [];
            $condition[] = ['website_uid', '=', $agentId];
            $condition[] = ['website_status', '=', 1];
            $domainArrStr = UserAgentConfigModel::where($condition)->value('website_web_url');
            if ($domainArrStr) {
                //域名可能是多个逗号分割，随机取出一个
                $domainArr = explode(',', $domainArrStr);
                return $domainArr[rand(0, count($domainArr) - 1)] . '/#/?modal=LoginRegistration&data=tab-1';
            }
        }
        //获取域名
        $domain = getShareDomain();
        //优先使用绑定域名的代理
        $webDomain = getOriginalDomain();#https://testweb.lottogf110.com
        $webDomainArr = explode('.', $webDomain);
        if (count($webDomainArr) == 2) {
            //顶级域名
            $_agentDomain = $webDomainArr[0] . '.' . $webDomainArr[1];
            $_agentDomain = str_replace('https://', '', $_agentDomain);
            $_agentDomain = str_replace('http://', '', $_agentDomain);
        } elseif (count($webDomainArr) == 3) {
            //子域名
            $_agentDomain = $webDomainArr[1] . '.' . $webDomainArr[2];
        } else {
            $_agentDomain = -1;
        }
        $autoDomain = env('AUTO_SHARE_URL', 0);
        //是否是自动
        if ($autoDomain == 1 && $_agentDomain != -1 && $isAdmin === false) {
            // 获取协议（http 或 https）
            $protocol = env('AUTO_SHARE_URL_PROTOCOL', 'https');
            $prefixApi = env('AUTO_URL_PREFIX', 'api');
            $domain = $protocol . '://' . $prefixApi . '.' . $_agentDomain;
        }
        return $domain . '/s/' . $recCode;
    }

    //获取代理域名
    public static function getAgentDomainArr($isNeedHttps = false)
    {
        $agentDomainEnv = env('AGENT_SHARE_URL', '');
        $agentDomainArr = explode(',', $agentDomainEnv);
        if ($isNeedHttps) {
            foreach ($agentDomainArr as $k => $v) {
                $agentDomainArr[$k] = 'https://' . $v;
            }
        }
        return $agentDomainArr;
    }

    //获取注册域名
    public static function getRegisterDomain($uid = 0)
    {
        $registerDomain = getOriginalDomain();
        if ($uid) {
            $domain = UserModel::where('id', '=', $uid)->value('reg_url');
            if ($domain) {
                $registerDomain = $domain;
            }
        }
        return $registerDomain;
    }

    //获取三方所有余额
    public static function getThirdAllBalance($uid)
    {
        $balance = 0;
        try {
            $thirdBalance = Game::getThirdBalanceAll($uid);
            if ($thirdBalance) {
                foreach ($thirdBalance as $k => $v) {
                    $balance += is_numeric($v) ? round($v, 2) : 0;
                }
            }
        } catch (\Exception $e) {
            $balance = 0;
        }
        return $balance;
    }

    /**
     * 获取用户三方余额
     * @param int $uid
     * @return float
     */
    public static function getUserThirdsBalance($uid = 0)
    {
        if (empty($uid)) {
            $balance = Db::name("user_thirds")->sum("ut_balance");
            return floatval($balance);
        }
        $balance = Db::name("user_thirds")->where('ut_uid', $uid)->sum("ut_balance");
        return floatval($balance);
    }

    /**
     * 刷新用户三方余额列表数据
     */
    public static function updateUserThirdsBalanceList()
    {
        $list = Db::name("user_thirds")->where('ut_balance', '>', 0)->order('ut_lastout asc')->limit(50)->select();
        if (empty($list)) {
            return true;
        }
        foreach ($list as $k => $v) {
            $balance = MemberService::getThirdAllBalance($v['ut_uid']);

            if ($balance < 1 && $v['ut_lastin'] < time() - (3600 * 5)) {
                //时间超过5小时，且余额小于1，取出该账号余额
                Game::withdrawThirdAll($v['ut_uid']);
            }
            if ($v['ut_lastin'] < time() - (3600 * 72)) {
                //72小时未更新，取出该账号余额
                Game::withdrawThirdAll($v['ut_uid']);
            }
            Db::name("user_thirds")->where('ut_uid', $v['ut_uid'])->update(['ut_lastout' => time()]);
        }
    }

    //获取指定时间当天的流水等统计
    public static function getDayWaterStatistics($uid, $time)
    {
        $apiRedis = new ApiRedis();
        $redisKey = 'get_day_water_statistics_uid_V2_' . $uid . '_time_' . $time;
        if ($apiRedis->has($redisKey)) {
            return json_decode($apiRedis->get($redisKey), true);
        }
        $userDetail = UserModel::where('id', '=', $uid)->find();
        if (!$userDetail) {
            return [];
        }
        $data = [];
        $date = date('Y-m-d', $time);
        $startTime = Utils::getQueryDateTime($date);
        $endTime = Utils::getQueryDateTime($date, true);
        $createTimeCondition = $updateCondition = [];

        $createTimeCondition[] = ['uid', '=', $uid];
        $createTimeCondition[] = ['create_time', 'between', [$startTime, $endTime]];

        $updateCondition[] = ['a.uid', '=', $uid];
        $updateCondition[] = ['a.update_time', 'between', [$startTime, $endTime]];

        $data['date'] = $date;
        //充值金额
        $data['recharge_amount'] = UserOfflineModel::getRechargeUserAmount($updateCondition);
        //活动奖励
        $data['activity_award'] = ReportService::getBonuses($startTime, $endTime, $uid)['action_amount'];
        //普通流水
        $water = UserWaterModel::where('water', '>', 0)
            ->where($createTimeCondition)->sum('water'); // 总游戏流水
        //指定流水
        $appointWaterResult = self::getUserAppointSavings($uid, $startTime, $endTime);
        $data['total_water'] = $water;
        //输赢
        $gameBets = GameService::statGameBetByGlobal($uid, $startTime, $endTime);
        $data['win_loss'] = $gameBets['total_win_lost'];
        //已完成流水
        $data['finish_water'] = $gameBets['bet_valid_amount'];
        //剩余流水
        $data['surplus_water'] = round($appointWaterResult['water'] - $appointWaterResult['finish_water'] + $userDetail->savings, 2);
        //缓存数据
        $expireTime = 86400 * 7;
        if ($time > time() - 86400 * 2) {
            $expireTime = 60 * 5;
        }
        $apiRedis->set($redisKey, json_encode($data), $expireTime);
        return $data;
    }

    //用户是否能领取优惠
    public static function judgeUserIsCanReceiveDiscount($uid)
    {
        $apiRedis = new ApiRedis();
        $sys = $apiRedis->systemInfo();
        if (!isset($sys['user_verify_disable_discount']) || !$sys['user_verify_disable_discount']) {
            //没有关闭用户认证领取优惠
            return true;
        }
        //获取用户实名认证状态
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['status', '=', 2];
        $userVerifySuccess = UserVerifyModel::where($condition)->field('id')->find();
        if ($userVerifySuccess) {
            return true;
        }
        return false;
    }

    //获取用户昵称（优先号码》邮箱》用户名）
    public static function getNickname($uid, $userDetail = null, $isHidden = false)
    {
        if (!$userDetail) {
            $userDetail = UserModel::where('id', '=', $uid)->find();
        }
        $nickname = '';
        if ($userDetail) {
            if ($userDetail['phone']) {
                $nickname = $userDetail['phone'];
            } elseif ($userDetail['mail']) {
                $nickname = $userDetail['mail'];
            } else {
                $nickname = $userDetail['username'];
            }
        }
        if ($isHidden) {
            $nickname = hideStr($nickname, 3);
        }
        return $nickname;
    }

    //活动优惠触发
    public static function activityDiscountTrigger($uid)
    {
        //触发自动发送
        $list = ActionApplyModel::where('uid', '=', $uid)->where('ps_status', '=', 0)->field('id')->select();
        foreach ($list as $v) {
            (new ActionFactory())->auto_review($v['id']);
        }
//        action_task($uid, 2); //再存优惠
//        action_task($uid, 3); //一般优惠

        action_task($uid, 6); //游戏救援金
        action_task($uid, 7); //推荐奖励
    }

    /**
     * 更新用户 彩金钱包转余额钱包
     * @param int $uid 指定uid执行
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function updateCjUserList($uid = 0)
    {
        $limit = env('UPDATE_CJ_USER_LIST_LIMIT', 1000);
        $map = [];
        $map[] = ['cjsavings', '=', 0];
        $map[] = ['cjbalance', '>', 0];
        if ($uid) {
            $map[] = ['id', '=', $uid];
        }
        Db::startTrans();
        $UserModel = new UserModel();
        $user_list = $UserModel->where($map)->limit($limit)->field('id,cjsavings,cjbalance')->select();
        $user_ids = [];
        $apiRedis = new ApiRedis();
        foreach ($user_list as $value) {
            $redisKey = 'auto_transfer_cj_to_balance_uid_' . $value['id'];
            if ($apiRedis->has($redisKey)) {
                Db::rollback();
                continue;
            }
            $apiRedis->set($redisKey, 1);
            //修改余额
            $ret = UserModel::cz_balance($value['id'], floatval($value['cjbalance']), "+", true, "彩金钱包转入余额钱包", 18);
            if ($ret) {
                UserModel::clearCjBalanceUnmet($value['id'], '彩金钱包转入余额钱包');
                $user_ids[] = $value['id'];
            }
            $apiRedis->delete($redisKey);
        }
        Db::commit();
        return $user_ids;
    }
}
