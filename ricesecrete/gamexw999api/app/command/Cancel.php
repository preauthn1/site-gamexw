<?php
declare (strict_types = 1);

namespace app\command;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\model\GameBetsSon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\redis\ApiRedis;
use think\facade\Cache;
use think\facade\Queue;
use think\facade\Db;

class Cancel extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('cancel')
            ->addArgument("type", Argument::REQUIRED, '')
            ->addArgument("uid", Argument::OPTIONAL, '')
            ->addArgument("hour", Argument::OPTIONAL, '')
            ->setDescription('cancel order status');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getArgument('type'); // 1 提现 2 充值
        $uid = $input->getArgument('uid');
        $hour = $input->getArgument('hour');#指定多少小时之前
        if( $hour ){
            $today_strat_time = strtotime('-'.$hour.' hour');
        }else{
            $today_strat_time = strtotime(date('Y-m-d').'00:00:00');
        }

        if($type == 2){
            $list = UserModel::alias("a")->where([['tree','like', '%/'.$uid.'/%']])->column("id");
            foreach($list as $val){
                $this->resetBalance($val, $output);
            }
        }elseif($type == 3){
            $ApiRedis = new ApiRedis();
            $sys = $ApiRedis->systemInfo();
            if(isset($sys['cancel_recharge_order']) && $sys['cancel_recharge_order'] == 1){
                //使用redis缓存
                $ApiRedis = new ApiRedis();
                //获取上次更新的id缓存
                $min_id = $ApiRedis->get('cache_user_offline_min_id');
                if(empty($min_id)){
                    $min_id = 0;
                }
                //获取最后一条id
                $user_off = UserOfflineModel::where("(status=1 or status=-1) and add_time <= ".$today_strat_time)->where('id','>=',$min_id)->limit(1)->order('id desc')->find();
                $where = [];
                if($user_off){
                    //将上次跟新id缓存
                    if(is_numeric($user_off['id'])){
                        $ApiRedis->set('cache_user_offline_min_id', $user_off['id']);
                    }
                    $where[] = ['id','>',$min_id];
                    $where[] = ['id','<',$user_off['id']];
                }else{
                    $where[] = ['add_time','<=',$today_strat_time];
                }
                UserOfflineModel::where("(status=1 or status=-1)")->where($where)->update(['status' => -2]);
            }
        }elseif($type == 4){
            $game = GameBetsSon::where([["gb_action", "like","%double%"]])->group("gb_sn")->select();
            foreach($game as $v){
                $this->resetBets($v['gb_gid'], $v['gb_sn'], $output);
            }
        }elseif($type == 1){
            $where = 'a.status = 1';
            if($uid > 0){
                $agent_wh = [];
                $agent_wh[] = ['tree','like', '%/'.$uid.'/%'];
                $sql = UserModel::where($agent_wh)->field('id')->buildSql();
                $where .= ' and a.uid in ('.$sql .')';
            }
            $list = UserWithdrawalModel::alias("a")->where($where)->column("id");
            foreach($list as $val){
                $this->operation($val, $output);
            }
        }
        
    }
    
    protected function resetBets($gid,$round_id,$output){
        $money = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where('gb_winlose', 3)->sum('gb_winprice');
        $gb_price = GameBetsSon::where('gb_gid', $gid)->where('gb_sn', $round_id)->where([["gb_action", "like","%double%"]])->sum('gb_price');
        $money1 = $money - $gb_price;
        Db::table('dq_game_bets')->where('gb_gid', $gid)->where('gb_sn', $round_id)->update(['gb_winprice' => $money1]);
        $output->writeln($gid."---".$round_id."---".$money."---".$gb_price."---".$money1);
    }
    protected function resetBalance($id, $output){
        $data = UserModel::alias("a")->where("a.id",$id)->find();
        if($data['balance'] > 0){
            UserModel::alias("a")->where("a.id",$id)->update(['balance' => 0]);
            $output->writeln("用户ID：{$id}===余额：{$data['balance']}===".date("Y-m-d H:i:s"));
        }else{
            $output->writeln("用户ID：{$id}===无操作===".date("Y-m-d H:i:s"));
        }
    }
    
    protected function operation($id, $output){
        $data = UserWithdrawalModel::alias("a")->where("a.id",$id)->find();
        if ($data['status'] > 1) {
            $output->writeln("数据ID：{$id}===重复处理===".date("Y-m-d H:i:s"));
        }
        $upd = [];
        $upd['w_remarks'] = $upd['third_err_remarks'] = "Resubmit";
        $upd['status'] = -2;
        $upd['operator'] = "admin";
        $upd['update_time']=time();
        $upd['id'] = $data['id'];
        $res = UserWithdrawalModel::update($upd);
        if($res){    
            UserModel::cz_balance($data['uid'],$data['w_price'],"+",true,'提现拒绝');
            $output->writeln("数据ID：{$id}===成功===".date("Y-m-d H:i:s"));
        }else{
            $output->writeln("数据ID：{$id}===失败===".date("Y-m-d H:i:s"));
        }
    }
}
