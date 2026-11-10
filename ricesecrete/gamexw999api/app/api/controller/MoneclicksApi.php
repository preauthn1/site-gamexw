<?php

namespace app\api\controller;


use app\BaseController;
use app\common\service\MemberService;
use think\Request;
use think\facade\Db;
use app\common\model\UserOfflineModel;
use app\common\model\TaskModel;

class MoneclicksApi extends BaseController {

    // 生成随机key
    private $key = '0QYWrhJ8jrVy2kVKNcotRBN56XhStC2J2Wi9NZhWMZIJ';

    /**
     * 代理用户报表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index(Request $request) {
        $mc_id = $request->param('mc_id/s');
        $key = $request->param('key/s');
        if ($key !== $this->key) {
            return $this->error('key错误');
        }
        $page = $request->param('page/d', 1);
        $limit = $request->param('limit/d', 20);
        $id = Db::name('user')->where('rec_code', $mc_id)->value('id');
        if (!$id) {
            return $this->error('代理不存在');
        }
        if ($limit > 100) {
            $limit = 100;
        }
        $users = Db::name('user')->where('rec_id', $id)->field('id,username,add_time')->limit(($page - 1) * $limit, $limit)->select();
        $total = Db::name('user')->where('rec_id', $id)->field('id,username,add_time')->count();
        $result = [];
        $recharge = UserOfflineModel::whereIn('status', '3,4')->where('first', 1)->field("arrival_price AS amount,update_time,uid")->group('uid')->select();
        if ($recharge) {
            $recharge = array_column($recharge->toArray(), null, 'uid');
        } else {
            $recharge = [];
        }
        foreach ($users as $k => $v) {
            $result[] = [
                'player_id' => $v['id'],
                'player_name' => $v['username'],
                'register_time' => date('Y-m-d H:i:s', $v['add_time']),
                'first_deposit_amount' => $recharge[$v['id']]['amount'] ?? 0,
                'first_deposit_date' => isset($recharge[$v['id']]['update_time']) ? date('Y-m-d H:i:s', $recharge[$v['id']]['update_time']) : '',
                'total_deposit_amount' => MemberService::getRechargeByUid($v['id']),
                'net_revenue' => $this->getNetRevenue($v['id']),
            ];
        }
        
        return $this->success([
            'data' => $result,
            'total' => $total,
        ], 'ok');
    }

    private function getNetRevenue($uid) {
        $revenue = Db::name('third_stats_users')->where('su_uid', $uid)->field('su_dt as date,su_otherwin as amount')->select();
        $result = $this->getBonuses($uid);
        $result2 = [];
        foreach($revenue as $k => $v) {
            $result2[$v['date']] = $v['amount'];
        }
        foreach($result as $k => $v) {
            if (!isset($result2[$k])) {
                $result2[$k] = 0;
            }
        }
        foreach($result2 as $k => $v) {
            $result2[$k] -= $result[$k] ?? 0;
        }
        return $result2;
    }

    /**
     * 统计所有优惠活动+赠送彩金
     * @param $uid
     * @return array
     */
    private function getBonuses($uid = null){
        $fyWhere = $where = [];
        if (is_numeric($uid)) {
            $where['uid'] = $uid;
            $fyWhere['sff_uid'] = $uid;
        } elseif (is_array($uid) || $uid instanceof \Closure) {
            $where[] = ['uid', "IN", $uid];
            $fyWhere[] = ['sff_uid', "IN", $uid];
        }
        $result = $actionResult = [];
        // 人工赠送彩金 + 会员等级彩金
        $result['bouns'] = Db::name('artificial_balance')->where(
            ['a_type' => 3, 'uid' => $uid]
        )
        ->fieldRaw("FROM_UNIXTIME(add_time,'%Y%m%d') AS days, SUM(price) AS amount ")->group('days')
        ->select();

        $result['bouns_vip'] = Db::name('artificial_balance')->where(
            ['a_type' => 4, 'uid' => $uid]
        )
        ->fieldRaw("FROM_UNIXTIME(add_time,'%Y%m%d') AS days, SUM(price) AS amount ")->group('days')
        ->select();

        // 免费转盘奖励
        $result['lottery'] = $actionResult['lottery'] = Db::name('lottery_record')
            ->where($where)
            ->where('is_free', 1)
            ->where('prize_type', 1)
            ->fieldRaw("FROM_UNIXTIME(time,'%Y%m%d') AS days, SUM(price) AS amount ")->group('days')
            ->select();


        // 优惠活动奖金
        $result['action_apply'] = $actionResult['action_apply'] = Db::name('action_apply')
                    ->where($where)
                    ->where('ps_status', 1)
                    ->fieldRaw("FROM_UNIXTIME(add_time,'%Y%m%d') AS days, SUM(`gd_amount`+`ck_hl_amount`) AS amount ")->group('days')
                    ->select();

        // 推广奖励
        $result['promotional_rebates'] = Db::name('agent_sell_log')->where("status",3)
            ->where($where)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(money) AS amount ")->group('days')
            ->select();



        //任务奖励
        $result['taskReward'] = $actionResult['taskReward'] = Db::name('task_record')
            ->where($where)
            ->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
            ->where('receive_status', '=', 1)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(reward_amount_or_integral) AS amount ")->group('days')
            ->select();


        //VIP等级周月奖励
        $result['levelReward'] = Db::name('user_level_reward')
            ->where($where)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(reward_amount) AS amount ")->group('days')
            ->select();

        //内部代理返佣
        $result['interior_rebates'] = Db::name('third_stats_fyfy')
            ->where($fyWhere)
            ->where('sff_payed', '=', 2)
            ->fieldRaw("FROM_UNIXTIME(sff_paytime,'%Y%m%d') AS days, SUM(sff_back) AS amount ")->group('days')
            ->select();



        //领取码
        $result['receive_code'] = $actionResult['receive_code'] = Db::name('receive_code_record')->where($where)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(reward_amount) AS amount ")->group('days')
            ->select();



        //签到
        $result['sign_amount'] = $actionResult['sign_amount'] = Db::name('sign_record')->where($where)
            ->where('status', '<>', 0)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(reward_amount) AS amount ")->group('days')
            ->select();

        //PDD转盘
        $result['pdd_amount'] = $actionResult['pdd_amount'] = Db::name('pdd_withdrawal_record')->where($where)
            ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(amount) AS amount ")->group('days')
            ->select();


        //五福
        $result['wufu'] = $actionResult['wufu'] = Db::name('wufu_withdrawal')->where($where)
        ->fieldRaw("FROM_UNIXTIME(create_time,'%Y%m%d') AS days, SUM(amount) AS amount ")->group('days')
        ->select();

        $res = [];
        foreach($result as $item) {
            foreach($item as $row) {
                if (!isset($res[$row['days']])) {
                    $res[$row['days']] = 0;
                }
                $res[$row['days']] += $row['amount'];
            }
        }
        return $res;
    }
}