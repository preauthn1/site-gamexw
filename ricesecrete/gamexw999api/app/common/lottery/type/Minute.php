<?php

namespace app\common\lottery\type;
use app\common\model\GamesModel;
use app\common\model\UserModel;
use app\common\Utils;
use think\facade\Db;

/**
 * 分分彩
 */
class Minute extends Base {

    public function run() {
        return 'run';
    }

    /**
     * 开奖
     * @return void
     */
    public function draw($qishu = null) {
        if (is_null($qishu)) {
            $qishu = $this->getQishu(true);
        }
        $where = [
            'lottery_id' => $this->lottery['id'],
            'qishu' => $qishu,
        ];
        $result = Db::name('s_lottery_result')->field('lottery_id,qishu,ball_1,ball_2,ball_3,ball_4,ball_5')->where($where)->find();
        if (empty($result)) {
            $result = $this->getDrawResult($qishu);
            $data = [
                'lottery_id' => $this->lottery['id'],
                'qishu' => $qishu,
                'ball_1' => $result[0],
                'ball_2' => $result[1],
                'ball_3' => $result[2],
                'ball_4' => $result[3],
                'ball_5' => $result[4],
                'open_time' => time(),
                'create_time' => time(),
                'update_time' => time(),
            ];
            Db::name('s_lottery_result')->insert($data);
            return $data;
        }

        return $result;
    }

    /**
     * 随机开奖
     * @param $qishu
     * @return array|false|string[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getDrawResult($qishu = null) {
        if (is_null($qishu)) {
            $qishu = $this->getQishu(true);
        }

        $where = [
            ['lottery_id', '=', $this->lottery['id']],
            ['qishu', '=', $qishu],
        ];
        $preset = Db::name('s_lottery_preset')->where($where)->find();
        $result = [];
        if (!empty($preset)) {
            $result = explode(",", $preset['numbers']);
        }

        if (empty($result)) {
            $rate = $this->lottery['draw_rate'] * 100;
            $rand = random_int(0, 100);
            if ($this->lottery['draw_type'] > 0 && ($rate >= 100 || $rand < $rand)) {
                $result = $this->getDrawResultCale($qishu);
            } else {
                $result = $this->randBall(5);
            }
        }
        return $result;
    }

    /**
     * 概率控制开奖
     * @param $qishu
     * @return void
     */
    public function getDrawResultCale($qishu) {
        $result = $this->randBall(5);
        $where = [
            ['lottery_id', '=', $this->lottery['id']],
            ['qishu', '=', $qishu],
            ['status', '=', 0]
        ];
        $lists = Db::name('s_lottery_order')->where($where)->field('odd_group,odd_name,sum(amount) as sum_amount,sum(win_amount) as sum_win_amount')->group('odd_group,odd_name')->select();
        if (!$lists->isEmpty()) {
            $cale_times = 0;
            $draw_win_array = [];
            $draw_lose_array = [];
            $draw_zero = $result;
            while($cale_times <= 200){
                $result = $this->randBall(5);
                // 获取所有用户订单盈亏
                list($profit, $loss) = $this->getOrderProfitAndLoss($result, $lists);
                $win = intval($loss) - intval($profit); // 用户输即平台赢
                if($win > 0) { // 平台赢
                    $draw_win_array[] = ['draw' => $result, 'win' => $win];
                } else if($win < 0){ // 平台输
                    $draw_lose_array[] = ['draw' => $result, 'win' => $win];
                } else { // 和
                    $draw_zero = $result;
                }
                if($this->lottery['draw_type'] == 3 && $win >= 0){break;}
                if($this->lottery['draw_type'] == 6 && $win <= 0){break;}
                $cale_times++;
            }
            if($this->lottery['draw_type'] != 3 && $this->lottery['draw_type'] != 6){
                $draw_win_array  = array_sort($draw_win_array , 'win');
                $draw_lose_array = array_sort($draw_lose_array, 'win', 'desc');
                if (count($draw_win_array)>0) {
                    if($this->lottery['draw_type'] == 1){
                        end($draw_win_array);
                        $key = key($draw_win_array);
                        $result = $draw_win_array[$key]['draw'];
                    }
                    if($this->lottery['draw_type'] == 2){
                        reset($draw_win_array);
                        $key = key($draw_win_array);
                        $result = $draw_win_array[$key]['draw'];
                    }
                } else {
                    $result=$draw_zero;
                }

                if (count($draw_lose_array) > 0) {
                    if($this->lottery['draw_type']==4){
                        end($draw_lose_array);
                        $key = key($draw_lose_array);
                        $result = $draw_lose_array[$key]['hm'];
                    }
                    if($this->lottery['draw_type'] == 5){
                        reset($draw_lose_array);
                        $key = key($draw_lose_array);
                        $result = $draw_lose_array[$key]['hm'];
                    }
                } else {
                    $result = $draw_zero;
                }
            }
        }
        return $result;
    }

    /**
     * 计算所有订单的盈亏
     * @param $result 开奖结果
     * @param $orders 投注订单
     * @return void
     */
    private function getOrderProfitAndLoss($result, $orders) {
        $profit = 0; // 盈利
        $loss = 0; // 亏损
        foreach ($orders AS $row) {
            $loss += $row['sum_amount']; //
            $text = $this->getResult($result, $row['odd_group'], $row['odd_name']);
            $is_win = $text == $row['odd_name'] ? 1 : 2; // 1 赢 2输
            if ($is_win) {
                $profit += $row['sum_win_amount'];
            }
        }
        return [$profit, $loss];
    }
    /**
     * @param boolean $last 获取上一期单期号
     * @return array|int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getQishu($last = false) {
        $now_time = time();
        $lottery_time = date("H:i:s", $now_time);

        $where = [
            ['lottery_id', '=', $this->lottery['id']],
            ['open_time', '<=', $lottery_time],
            ['draw_time', '>', $lottery_time],
        ];
        $times = Db::name('s_lottery_opentime')->where($where)->find();
        if (empty($times)) {
            return -1;
        }
        if (!$last) {
            $qs = date("Ymd", $now_time) . $this->padNumber($times['qishu']);
        } else {
            if($times['qishu'] == 1){
                $qs = date("Ymd", strtotime("-1 day")).'1440';
            }else{
                $qs = date("Ymd", $now_time) . $this->padNumber($times['qishu'] - 1);
            }
        }
        $close_time	= strtotime(date("Y-m-d", $now_time) . ' '. $times['close_time']) - $now_time;
        $draw_time	= strtotime(date("Y-m-d", $now_time) . ' '. $times['draw_time']) - $now_time;

        return $last ? $qs : [
            'qishu' => $qs,
            'close_time' => $close_time,
            'draw_time' => $draw_time,
        ];
    }


    /**
     * 结算注单
     * @param $qishu
     * @return boolean
     */
    public function js($qishu = null, $limit = 50) {
        $where = [
            'lottery_id' => $this->lottery['id'],
            'status' => 0
        ];

        if (!is_null($qishu)) {
            $where['qishu'] = $qishu;
        }
        $game = null;
        if ($this->lottery['game_id']) {
            $game = GamesModel::where('g_id', $this->lottery['game_id'])->find();
        }
        $result = Db::name('s_lottery_result')->where($where)->select();
        $is_done = true;
        foreach ($result AS $row) {
            $balls = [
                $row['ball_1'], $row['ball_2'], $row['ball_3'], $row['ball_4'], $row['ball_5'],
            ];
            $orders = Db::name('s_lottery_order')->where($where)->where('qishu', $row['qishu'])->order('create_time ASC')->limit($limit)->select();
            if ($orders->isEmpty()) {
                Db::name('s_lottery_result')->where('id', $row['id'])->update([
                    'status' => 1
                ]);
                continue;
            }
            $is_done = false;
            foreach ($orders AS $order) {
                $text = $this->getResult($balls, $order['odd_group'], $order['odd_name']);
                $is_win = $text == $order['odd_name'] ? 1 : 2; // 1 赢 2输
                $update = [
                    'status' => 1,
                    'result' => implode(',', $balls),
                    'odd_result' => $text,
                    'is_win' => $is_win,
                    'pay_time' => time()
                ];
                if ($is_win == 1) { // 赢
                    $update['win_amount'] = round($order['amount'] * $order['odds'], 4);
                    UserModel::cz_balance($order['uid'], $update['win_amount'],"+",true,"彩票盈利", 2);
                } else {
                    $update['win_amount'] = $order['amount'] * -1;
                }
                Db::name('s_lottery_order')->where('id', $order['id'])->update($update);
                if ($game) {
                    try {
                        Db::name('game_bets')->insert([
                            'gb_tcid' => $game['g_tcid'],
                            'gb_gid' => $game['g_id'],
                            'gb_uid' => $order['uid'],
                            'gb_intime' => time(),
                            'gb_price' => $order['amount'],
                            'gb_sn' => $order['order_sn'],
                            'gb_winlose' => $is_win == 1 ? 3 : 2,
                            'gb_bettime' => $order['create_time'],
                            'gb_overtime' => time(),
                            'gb_winprice' => $is_win == 1 ? $update['win_amount'] : 0,
                        ]);
                    } catch (\Exception $e) {
                        Utils::L('lottery-js', $e->getMessage());
                    }
                }
            }
        }
        return $is_done;
    }

    /**
     * 获取结果文字
     * @param $result
     * @param $group
     * @param $type
     * @return float|int|string
     */
    public function getResultText($result, $group, $type = null) {
        $options_text = config('lottery.options_text.minute');
        $text = $this->getResult($result, $group, $type);
        return lang($options_text[$text] ?? $text);
    }

    /**
     * 获取结果
     * @param $result
     * @param $group
     * @param $type
     * @return float|int|string
     */
    public function getResult($result, $group, $type = null) {
        // 总和
        $total = array_sum($result);
        $text = "";
        if($group == 'sum'){
            switch ($type) {
                case 'big':
                case 'small':
                case 'big_small':
                    $text = $total >= 23 ? 'big' : 'small';
                    break;
                case 'single':
                case 'double':
                case 'single_double':
                    $text = $total%2==0 ? 'double' : 'single';
                    break;
                case 'tiger':
                case 'dragon':
                case 'dragon_tiger':
                    if ($result[0] == $result[4]) {
                        $text = 'draw';
                    } else {
                        $text = $result[0] > $result[4] ? 'dragon' : 'tiger';
                    }
                    break;
                default:
                    $text = $total;
            }
        }

        elseif (in_array($group, ['before_three', 'middle_three', 'after_three'])) {
            // 'trips', 'sequence', 'pair', 'half_sequence', 'other'
            if ($group == 'before_three') {
                $three_arr = [$result[0], $result[1], $result[2]];
            } elseif ($group == 'middle_three') {
                $three_arr = [$result[1], $result[2], $result[3]];
            } else {
                $three_arr = [$result[2], $result[3], $result[4]];
            }
            sort($three_arr);
            if($this->isLeopard($three_arr)){
                $text = 'trips';
            }else if($this->hasPair($three_arr)){
                $text = 'pair';
            }else if($this->isStraight($three_arr)){
                $text = 'sequence';
            }else if($this->isSemiStraight($three_arr)){
                $text = 'half_sequence';
            }else{
                $text = 'other';
            }
        }

        elseif (in_array($group, ['ball_1', 'ball_2', 'ball_3', 'ball_4', 'ball_5'])) {
            $index = str_replace("ball_", '', $group);
            $text = $result[$index - 1];
            switch ($type) {
                case 'big':
                case 'small':
                case 'big_small':
                    $text = $text > 4 ? 'big' : 'small';
                    break;
                case 'single':
                case 'double':
                case 'single_double':
                    $text = $text%2==0 ? 'double' : 'single';
                    break;
            }
        }
        return $text;
    }

    /**
     * 判断是否为顺子
     * @param $nums
     * @return bool
     */
    private function isStraight($nums) {
        // 将数组从小到大排序
        sort($nums);

        // 判断是否为特殊顺子，例如：089、019、910
        if (($nums[0] == 0 && $nums[1] == 1 && $nums[2] == 9) ||
            ($nums[0] == 0 && $nums[1] == 8 && $nums[2] == 9) ||
            ($nums[0] == 9 && $nums[1] == 1 && $nums[2] == 0)) {
            return true;
        }

        // 判断是否为普通顺子
        return $nums[2] - $nums[0] == 2 && $nums[1] - $nums[0] == 1 && $nums[2] - $nums[1] == 1;
    }

    /**
     * 判断是否为半顺
     * @param $nums
     * @return bool
     */
    private function isSemiStraight($nums) {
        // 将数组从小到大排序
        sort($nums);
        return (
            // 存在 "09" 或 "90"，可以算做半顺
            preg_match('/.09|0.9/', implode('', $nums)) ||
            // 存在两个连续的数，其中一个数不连续
            ($nums[1] - $nums[0] == 1 && $nums[2] - $nums[1] != 1) ||
            ($nums[1] - $nums[0] != 1 && $nums[2] - $nums[1] == 1)
        );
    }

    /**
     * 判断是否为豹子
     * @param $nums
     * @return bool
     */
    private function isLeopard($nums) {
        // 将数组去重，如果去重后只剩下一个数，即为豹子
        $unique = array_unique($nums);
        return count($unique) == 1;
    }

    /**
     * 判断是否为对子
     * @param $nums
     * @return bool
     */
    private function hasPair($nums) {
        // 将数组去重，如果去重后的数组长度为 2，则说明有一对数字相等
        $unique = array_unique($nums);
        return count($unique) == 2;
    }
}