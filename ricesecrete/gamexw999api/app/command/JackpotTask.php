<?php

namespace app\command;

use app\common\service\BetService;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\model\JackpotModel;
use app\common\model\JackpotConfigModel;
use app\common\model\JackpotRecordModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\service\MemberService;
use think\facade\Db;


class JackpotTask extends Command
{
    protected function configure()
    {
        $this->setName('jackpot')->setDescription('排行榜统计/结算');
    }

    protected function execute(Input $input, Output $output)
    {
        //排行榜处理
        $condition = [];
        $condition[] = ['status', '=', 1];
        $condition[] = ['and_time', '<=', time()];
        $condition[] = ['end_time', '>=', time()];
        $list = JackpotConfigModel::where($condition)->order('type asc')->select();
        foreach ($list as $v){
            $jTypeStr = $v['type'] == 1 ? '日榜' : ($v['type'] == 2 ? '周榜' : '月榜');
            $output->info("开始处理{$jTypeStr}数据");
            $this->handleJackpot($v['type'], $output);
        }
        $output->info("done！");
    }

    //处理排行榜数据
    public function handleJackpot($type, $output){
        //统计数据
        $output->info("开始统计排行榜数据：".$type);
        $this->statistics_data($type, $output);

        //假数据自增长
        $output->writeln("开始处理假数据：".$type);
        $this->falseDataInc($type, $output);

        //计算排名
        $output->writeln("计算排名：".$type);
        JackpotModel::rank_statistics($type);

        //结算
        $output->writeln("开始结算：".$type);
        $this->settle($type, $output);
        return true;
    }

    //排行榜数据统计
    public function statistics_data($type, $output)
    {
        $config = JackpotConfigModel::jackpot_config($type);
        if(!$config){
            return;
        }
        //根据类型获取查询时间和当期时间
        $timeResult = JackpotModel::getTimeByJType($type);
        $day = $timeResult['day'];
        $startDate = $timeResult['startDate'];
        $endDate = $timeResult['endDate'];

        //统计数据
        $where = [];
        $where[] = ['a.su_dt','between', [$startDate, $endDate]];
        $where[] = ['b.status','=',1];
        $where[] = ['b.lebal','=',1];
        //等级限制
        if($config['level_id'] > 0){
            $where[] = ['b.level_id','=',$config['level_id']];
        }
        //代理限制
        $whereStr = '1=1';
        if( $config['agent_id'] ){
            //可能存在多个代理1,3,4
            $agentIdArr = explode(',', $config['agent_id']);
            $_where = [];
            foreach ($agentIdArr as $k => $v){
                $_where[] = "b.tree like '%/$v/%'";
            }
            $whereStr = implode(' or ', $_where);
        }

        //排序
        $order = $config['bet_type'] == 1 ? 'total_bet desc' : 'total_validwater desc';

        $table = BetService::getThirdStatsTable();
        $list = Db::name($table['_stats_users'])->alias('a')->where($where)
            ->join('user b','a.su_uid=b.id')
            ->where($whereStr)
            ->field('sum(a.su_bet) as total_bet,sum(a.su_validwater) as total_validwater,a.su_uid,b.level_id,b.username,b.head_img')
            ->group('a.su_uid')
            ->order($order)
            ->select();
        foreach($list as $k=>$v){
            //标签限制
            if ($config['tag_id'] > 0) {
                $user_tags = MemberService::getUserTagId($v['su_uid']);
                if (!in_array($config['tag_id'], $user_tags)) {
                    continue;
                }
            }
            //首冲并且有投注记录状态
            if($config['fcharge_status'] == 1){
                $UserOff = UserOfflineModel::where([['first','=',1],['uid','=',$v['su_uid']],['status','in',[3,4]]])->whereTime('pay_time', 'between', [$config['and_time'],$config['end_time']])->value('id');
                //不需要判断是否有下注，到这里面就说明有下注
//                $bet_record = Db::name('game_bets')->where([['gb_uid','=',$v['su_uid']],['gb_bettime','between',[$config['and_time'],$config['end_time']]]])->value('gb_id');
                if(!$UserOff){
                    continue;
                }
            }
            $price = $config['bet_type'] == 1 ? $v['total_bet'] : $v['total_validwater'];
            $info = JackpotModel::where(['uid'=>$v['su_uid'],'type'=>1,'day'=>$day,'j_type'=>$type])->find();
            if($info){
                if( $price != $info->price || $v['username'] != $info->username || $v['head_img'] != $info->head_img ){
                    $info->price = $price;
                    $info->username = $v['username'];
                    $info->head_img = $v['head_img'];
                    $info->save();
                    $output->warning("更新用户{$v['su_uid']}数据,金额:{$price}");
                }else{
                    //用户数据不变不用更新
                    $output->error("用户{$v['su_uid']}数据不变,不用更新,金额:{$price}");
                }
            }else{
                $data = [
                    'j_type'=>$type,
                    'uid'=>$v['su_uid'],
                    'username'=>$v['username'],
                    'head_img'=>$v['head_img'],
                    'price'=>$price,
                    'type'=>1,
                    'day'=>$day,
                    'create_time'=>time(),
                    'update_time'=>time(),
                ];
                JackpotModel::insert($data);
                $output->info("写入用户{$v['su_uid']}数据,金额:{$price}");
            }
        }
    }


    //排行榜结算
    public function settle($type, $output){
        $config = JackpotConfigModel::jackpot_config($type);
        if(!$config){
            return;
        }


        $jackpotTime = $config['jackpot_time'];
        switch ($type){
            case 1:
                //日榜
                $settleTime = strtotime(date('Y-m-d ' . $jackpotTime. ':00'));
                break;
            case 2:
                //周榜
                $endWeekTime = Carbon::now()->endOfWeek()->timestamp;
                $settleTime = strtotime(date('Y-m-d ' . $jackpotTime. ':00', $endWeekTime));
                break;
            case 3:
                //月榜
                $endMonthTime = Carbon::now()->endOfMonth()->timestamp;
                $settleTime = strtotime(date('Y-m-d ' . $jackpotTime. ':00', $endMonthTime));
                break;
            default:
                return false;

        }
        if(time() < $settleTime){
            $output->error("排行榜未到结算时间,当前时间:".date('Y-m-d H:i:s').',结算时间为: ' . date('Y-m-d H:i:s', $settleTime));
            return;
        }

        $portional = $config['portional_config'];
        //最大排名
        $maxlimit = JackpotModel::getMaxNum($portional);

        $timeResult = JackpotModel::getTimeByJType($type);
        $condition = [];
        $condition[] = ['day', '=', $timeResult['day']];
        $condition[] = ['j_type', '=', $type];
        $sta = false;
        $query = JackpotModel::where($condition);
        Db::startTrans();
        try {
            $list = $query->order('ranking asc')->field('uid,price,type')->limit($maxlimit)->select();
            $totalAmount = $query->sum('price');
            //奖池金额大于起始金额，则取奖池金,否则取起始金额
            $total_price = $totalAmount > $config['starting_amount'] ? $totalAmount : $config['starting_amount'];
            foreach ($list as $k => &$v) {
                $v['proportion'] = $v['bonus'] = 0;
                //计算用户可获得的金额及比例
                foreach ($portional as $_v){
                    if( $v['ranking'] >= $_v['start'] && $v['ranking'] <= $_v['end'] ){
                        $v['proportion'] = $_v['proportion'] / 100;  //分成比例
                        $v['bonus'] = round($total_price * $v['proportion'], 2);//发放奖金
                        break;
                    }
                }
                //真实用户发放奖金
                if ($v['type'] == 1) {
                    //本期是否发放过奖金
                    $_condition = [];
                    $_condition[] = ['uid', '=', $v['uid']];
                    $_condition[] = ['type', '=', $type];
                    $_condition[] = ['day', '=', $timeResult['day']];

                    $info = JackpotRecordModel::where($_condition)->field('id')->find();
                    if(!$info){
                        $da = [
                            'uid' => $v['uid'],
                            'type' => $config['type'],
                            'day' => $v['day'],
                            'price' => $v['bonus'],
                            'proportion' => $v['proportion'],
                            'ranking' => $v['ranking'],
                            'create_time' => time()
                        ];
                        $_id = (new JackpotRecordModel())->insertGetId($da);
                        if($v['bonus'] > 0){
                            UserModel::cz_balance($v['uid'], $v['bonus'], "+", true, "游戏排行榜奖金结算", 41, $config['water'], 1, $_id, true, 101);
                        }
                        $sta = true;
                        $msg = $config['type'] == 1 ? '日榜' : ($config['type'] == 2 ? '周榜' : '月榜');
                        $output->info($msg."奖金结算成功：用户id:". $v['uid']. " 奖金:". $v['bonus']);
                    }
                }
            }
            if($sta){
                $dates = JackpotModel::getNextStartTime($type);
                JackpotConfigModel::jackpot_config($type,2,['next_time'=>$dates,'update_time'=>time()]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $output->error('排行榜结算失败：'. $e->getMessage());
        }
    }

    //处理假数据
    private function falseDataInc($type, $output){
        $timeResult = JackpotModel::getTimeByJType($type);
        $condition = [];
        $condition[] = ['type', '=', 2];
        $condition[] = ['day', '=', $timeResult['day']];

        //假数据自增长
        $jacklist = JackpotModel::where($condition)->whereColumn('price', '<', 'upper_limit')->order('id asc')->select();
        foreach($jacklist as $k=>$vs){
            $min = $vs['min_rand'];
            $max = $vs['max_rand'];
            $rand = round($min + mt_rand() / mt_getrandmax() * ($max - $min),2);
            $price = $vs['price'] + $rand;
            JackpotModel::where(['id'=>$vs['id']])->update(['price'=>$price,'update_time'=>time()]);
            $output->warning("更新假数据:{$vs['username']},原金额:{$vs['price']},增加金额:{$rand},新金额:{$price}");
        }
        return true;
    }
}