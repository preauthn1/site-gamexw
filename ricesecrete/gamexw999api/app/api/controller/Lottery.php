<?php

namespace app\api\controller;


use app\api\redis\ApiRedis;
use app\BaseController;
use app\common\lib\Util;
use app\common\lottery\App;
use app\common\model\RuleModel;
use app\common\model\SLotteryModel;
use app\common\model\SLotteryResultModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\Utils;
use think\Exception;
use think\facade\Db;
use think\facade\Lang;
use think\Request;

class Lottery extends BaseController {

    /**
     * 彩种列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index(Request $request) {
        $page = $request->param('page/d', 1);
        $size = $request->param('limit/d', 10);
        $result = SLotteryModel::where(['status' => 1])->field('id,name,icon,lottery_type')->order('sort DESC')->select();

        return $this->success([
            'data' => $result
        ]);
    }

    /**
     * 获取彩票详情
     * @param $id 彩种ID
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function detail($id) {
        $lottery = SLotteryModel::where(['status' => 1, 'id' => $id])->field('id,name,icon,lottery_type,quick_amount')->find();
        if (empty($lottery)) {
            return $this->error('彩种信息不存在');
        }
        $lottery['rule_text'] = RuleModel::where('type', 99)->where('lang', strtoupper(Lang::getLangSet()))->where('game_id', $lottery['id'])->value('content');
        if (isset($lottery['quick_amount'])) {
            $lottery['quick_amount'] = array_filter(array_unique(explode(",", $lottery['quick_amount'])));
        }
        if (empty($lottery['quick_amount'])) {
            $lottery['quick_amount'] = [100, 500, 1000, 5000, 10000];
        }
        $lottery_config = config('lottery');
        $options = $lottery_config['options'][$lottery['lottery_type']];
        $odds = [];

        foreach ($options as $key => $v) {
            $odds[$key] = [];
        }
        $odd_info = Db::name('s_lottery_odds')->where('lottery_id', $id)->select();
        if ($odd_info) {
            foreach ($odd_info AS $item) {
                $odds[$item['bet_group']][$item['bet_name']] = floatval($item['bet_odd']);
            }
        }
        $obj = App::init($lottery['lottery_type'], $lottery);

        $last_qi = SLotteryResultModel::where('lottery_id', $id)->field('qishu,open_time,ball_1,ball_2,ball_3,ball_4,ball_5')->order('id DESC')->find();
        $last = [];
        if ($last_qi) {
            $last_qi_balls = [
                $last_qi['ball_1'], $last_qi['ball_2'], $last_qi['ball_3'], $last_qi['ball_4'], $last_qi['ball_5'],
            ];
            $last['qishu'] = $last_qi['qishu'];
            $last['balls'] = $last_qi_balls;
            $last['result'] = [
                'sum' => $obj->getResultText($last_qi_balls,'sum', 'big_small')
                    . "/" . $obj->getResultText($last_qi_balls,'sum', 'single_double')
                    . "/" . $obj->getResultText($last_qi_balls,'sum', 'dragon_tiger'),
                'before_three' => $obj->getResultText($last_qi_balls,'before_three'),
                'middle_three' => $obj->getResultText($last_qi_balls,'middle_three'),
                'after_three' => $obj->getResultText($last_qi_balls,'after_three'),
            ];
        }
        $ball_numbers = $lottery_config['ball_numbers'][$lottery['lottery_type']];
        return $this->success([
            'data' => [
                'numbers' => $ball_numbers,
                'last' => $last,
                'info' => $lottery,
                'current' => $obj->getQishu(),
                'odds' => $odds,
                'options_text' => $lottery_config['options_text'][$lottery['lottery_type']],
//                'config' => $lottery_config,
            ],
        ]);
    }

    /**
     * 彩票投注
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function order(Request $request) {
        $uid = $request->token->uid;
        $lottery_id = $request->param('lottery_id/d');
        $order_data = $request->param('order_data/a');

        $lottery = SLotteryModel::where(['status' => 1, 'id' => $lottery_id])->field('id,name,icon,lottery_type')->find();
        if (empty($lottery)) {
            return $this->error('彩种信息不存在');
        }
        try {
            $obj = App::init($lottery['lottery_type'], $lottery);
            $qishu = $obj->getQishu();
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }

        if ($qishu['close_time'] <= 0) {
            return $this->error('封盘中,请稍后再下注');
        }
        $all_order = [];
        $total_amount = 0;
        foreach ($order_data AS $row) {
            if ( !(isset($row['bet_group']) && isset($row['bet_option']) && isset($row['amount'])) ) {
                continue;
            }
            $bet_group = $row['bet_group'];
            $bet_option = $row['bet_option'];
            $amount = floatval($row['amount']);
            if (empty($amount) || $amount <= 0) {
                continue;
            }
            $odd_info = Db::name('s_lottery_odds')->where([
                'lottery_id' => $lottery_id,
                'bet_group' => $bet_group,
                'bet_name' => $bet_option,
            ])->find();
            if (empty($odd_info)) {
                return $this->error('投注选项错误');
            }

            $all_order[] = [
                'order_sn' => Utils::getOrderSN(Util::random(3, true),'LO'),
                'uid' => $uid,
                'lottery_id' => $lottery_id,
                'qishu' => $qishu['qishu'],
                'odd_id' => $odd_info['id'],
                'odd_group' => $odd_info['bet_group'],
                'odd_name' => $odd_info['bet_name'],
                'bet_tag' => $odd_info['bet_name'],
                'odds' => $odd_info['bet_odd'],
                'amount' => $amount,
                'win_amount' => $amount * $odd_info['bet_odd'],
                'currency_id' => 0, // TODO 支持多货币
                'status' => 0,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $total_amount+=$amount;
        }

        if ($total_amount <= 0 || empty($all_order)) {
            return $this->error('投注信息有误');
        }

        Db::startTrans();
        try {
            $userlock = UserModel::where("id", $uid)->lock(true)->field("balance,live_fund_money")->find();
            if (!($userlock['balance']>=$total_amount)) {
                throw new \Exception("余额不足", 0);
            }

            Db::name('s_lottery_order')->insertAll($all_order);
            UserModel::cz_balance($uid, $total_amount,"-",true,"彩票投注" . count($all_order) . "注", 1);

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }

        return $this->success();
    }

    /**
     * 获取最近50期开奖结果
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function draw(Request $request) {
        $page = $request->param('page/d', 1);
        $size = $request->param('limit/d', 50);
        $offset = ($page - 1) * $size;
        $lottery_id = $request->param('lottery_id/d');
        $where = [];
        $where['lottery_id'] = $lottery_id;
        $lottery = SLotteryModel::where(['status' => 1, 'id' => $lottery_id])->field('id,name,icon,lottery_type')->find();
        if (empty($lottery)) {
            return $this->error('彩种信息不存在');
        }
        $result = Db::name('s_lottery_result')->field("*")->where($where)->limit($offset, $size)->order('id DESC')->select();
        $total = Db::name('s_lottery_result')->where($where)->count();
        $list = [];
        $lottery_config = config('lottery');
        $options_text = $lottery_config['options_text'][$lottery['lottery_type']];

        $obj = App::init($lottery['lottery_type'], $lottery);
        foreach ($result AS $value) {
            $balls = [
                $value['ball_1'], $value['ball_2'], $value['ball_3'], $value['ball_4'], $value['ball_5'],
            ];
            $row = [];
            $row['qishu'] = $value['qishu'];
            $row['balls'] = $balls;
            $row['result'] = [
                'sum' => $obj->getResultText($balls,'sum', 'big_small')
                    . "/" . $obj->getResultText($balls,'sum', 'single_double')
                    . "/" . $obj->getResultText($balls,'sum', 'dragon_tiger'),
                'before_three' => $obj->getResultText($balls,'before_three'),
                'middle_three' => $obj->getResultText($balls,'middle_three'),
                'after_three' => $obj->getResultText($balls,'after_three'),
            ];
            $list[] = $row;
        }
        return $this->success([
            'data' => [
                'list' => $list,
                'total' => $total,
                'options_text' => $lottery_config['options_text'][$lottery['lottery_type']],
            ],
        ]);
    }

    /**
     * 我的投注记录
     * @return void
     */
    public function myrecord(Request $request) {
        $uid = $request->token->uid;
        $page = $request->param('page/d', 1);
        $size = $request->param('limit/d', 10);
        $lottery_id = $request->param('lottery_id/d');
        $qishu = $request->param('qishu/s');
        $offset = ($page - 1) * $size;
        $where = [
            'uid' => $uid,
        ];
        if (!empty($lottery_id)) {
            $where['lottery_id'] = $lottery_id;
        }
        $lottery = SLotteryModel::where(['status' => 1, 'id' => $lottery_id])->field('id,name,icon,lottery_type')->find();
        if (empty($lottery)) {
            return $this->error('彩种信息不存在');
        }
        if (!empty($qishu)) {
            $where['qishu'] = $qishu;
        }
        $field = "uid,qishu,odd_group,odd_name,odds,amount,win_amount,status";
        $result = Db::name('s_lottery_order')->field($field)->where($where)->limit($offset, $size)->order('id DESC')->select();
        $total = Db::name('s_lottery_order')->where($where)->count();
        $list = [];
        $lottery_config = config('lottery');
        $options_text = $lottery_config['options_text'][$lottery['lottery_type']];
        foreach ($result AS $row) {
            $row['option'] = lang($options_text[$row['odd_group']] ?? $row['odd_group']) . "[".lang(($options_text[$row['odd_name']] ?? $row['odd_name']))."]";
            $row['uid'] = hideStr(md5($row['uid']));
            $list[] = $row;
        }
        return $this->success([
            'data' => [
                'list' => $list,
                'total' => $total,
            ],
        ]);
    }


    /**
     * 投注记录
     * @return void
     */
    public function record(Request $request) {
        $page = $request->param('page/d', 1);
        $size = $request->param('limit/d', 10);
        $is_win = $request->param('is_win/d', 1); // 输赢
        $orderby = $request->param('orderby/d', 1); // 1 赢钱 2 投注
        $lottery_id = $request->param('lottery_id/d');
        $qishu = $request->param('qishu/s');
        $offset = ($page - 1) * $size;
        if (empty($lottery_id)) {
            return $this->error('彩种ID有误');
        }
        $lottery = SLotteryModel::where(['status' => 1, 'id' => $lottery_id])->field('id,name,icon,lottery_type,game_id')->find();
        if (empty($lottery)) {
            return $this->error('彩种信息不存在');
        }
        $where = ['status' => 1];
        $where['lottery_id'] = $lottery_id;
        if (!empty($qishu)) {
            $where['qishu'] = $qishu;
        }
        if (in_array($is_win, [1, 2])) {
            $where['is_win'] = $is_win;
        }
        $orderby_str = "id DESC";
        if (in_array($orderby, [1, 2])) {
            $orderby_str = $orderby == 1 ? "win_amount DESC" : "amount DESC";
        }
        $field = "uid,qishu,odd_group,odd_name,odds,amount,win_amount";
        $result = Db::name('s_lottery_order')->field($field)->where($where)->limit($offset, $size)->order($orderby_str)->select();
        $total = Db::name('s_lottery_order')->where($where)->count();
        $list = [];
        $lottery_config = config('lottery');
        $options_text = $lottery_config['options_text'][$lottery['lottery_type']];
        if ($result->isEmpty()) {
            if (empty($qishu)) {
                $obj = App::init($lottery['lottery_type'], $lottery);
                $qishu = $obj->getQishu(true);
            }
            $result = $this->generateVirtualData($size, $lottery_id, $lottery['game_id'], $qishu);
        }
        foreach ($result AS $row) {
            $row['option'] = lang($options_text[$row['odd_group']] ?? $row['odd_group']) . "[".lang(($options_text[$row['odd_name']] ?? $row['odd_name']))."]";
            $row['uid'] = hideStr(md5($row['uid']));
            $list[] = $row;
        }
        return $this->success([
            'data' => [
                'list' => $list,
                'total' => $total,
            ],
        ]);
    }


    /**
     * @param $num 生成数据条数
     * @param $type 数据类型 1: 投注， 2 最高赢钱， 3 最高下注
     * @param int $min_amount
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function generateVirtualData($num = 10, $lottery_id = 0, $gid = 0, $qishu = ''){
        $ApiRedis=new ApiRedis();
        $redis_key = md5("generateVirtualData-lottery{$gid}:".$num);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data = [];
        }
        if(empty($data)){
            $game = Db::name('fake_game_set')->where('gid', '=', $gid)->where('status', 1)->select();
            if ($game->isEmpty()) {
                return [];
            }
            $odd_info = Db::name('s_lottery_odds')->where('lottery_id', $lottery_id)->select();
            $time = time();
            for($i=0;$i<$num;$i++){
                $rand_game = $game->shuffle()->first();
                $odd = $odd_info->shuffle()->first();
                $row = [];
                $row['qishu'] = $qishu;
                $row['uid'] = get_rand_char(2)."****".get_rand_char(2);
                $row['odd_group'] = $odd['bet_group'];
                $row['odd_name'] = $odd['bet_name'];
                $row['odds'] = $odd['bet_odd'];
                $row['amount'] = random_int($rand_game['min_amount'], $rand_game['max_amount']);
                $row['win_amount'] = $row['amount'] * $row['odds'];
                $data[] = $row;
            }
            $ApiRedis->set($redis_key, $data, 30);
        }
        return $data;
    }
}