<?php
declare (strict_types = 1);

namespace app\command;
use app\common\model\AdsChannelModel;
use app\common\model\CurrencyModel;
use app\common\model\CurrencySetModel;
use app\common\model\UserModel;
use app\common\model\UserReportsModel;
use app\common\service\ReportService;
use Carbon\Carbon;
use think\facade\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\common\lib\Util;
use think\facade\Queue;
use think\facade\Db;

class Publics extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('publics')
            ->addArgument("type", Argument::REQUIRED, '类型：1 提现 2 充值 3 会员报表')
            ->addOption("arg", 'a', Option::VALUE_OPTIONAL, '复合参数')
            ->setDescription('public task');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getArgument('type'); // 1 提现 2 充值
        
        if($type == 1){
            $ApiRedis=new ApiRedis();
            $is_auto = $ApiRedis->get("currency_rate_auto_time");
            $set = CurrencySetModel::where(['id' => 1])->find();
            if(empty($set)){
                $set['auto_rate'] = 0;
            } else {
                $set = $set->toArray();
            }
            if($set['auto_rate'] == 1 && empty($is_auto)){
                $currency = Config::get('custom.currency_code');
                $default_country = env("default_country", "");
                $currency_type = env("currency_type", 0);
                if(!empty($default_country)){
                    if($currency_type == 1){
                        $curr_list = CurrencyModel::where(['type' => 2])->select();
                        foreach($curr_list as $val){
                            $this->updateRate($val['id'], $currency[$val['country_code']], 1, $output);
                        }
                    } else {
                        $curr_list = CurrencyModel::where(['type' => 1])->select();
                        foreach($curr_list as $val){
                            $this->updateRate($val['id'], $currency[$default_country], 2, $output);
                        }
                    }
                    $ApiRedis->set("currency_rate_auto_time", 1, $set['auto_time'] * 60);
                    app()->make(CommomRedis::class)->set( "currency", Util::getCurrency());
                } else {
                    $output->writeln("请设置默认货币");
                }
            } else {
                $output->writeln("关闭了实时汇率或间隔时间没到");
            }
        } elseif($type == 3){
            $this->userReports($output);
        } elseif($type == 4){
            $time = time();
            $list = Db::name('user')->where([['update_time','>',($time-360)]])->whereOr([['login_time','>',($time-360)]])->field('id,username,phone,mail,lebal,add_time,login_time,phone_status,status,rec_id,reg_ip,balance,level_id')->select();
            foreach ($list as $v){
                $this->userReports2($v,$time,$output);
            }
        } else {
            if (method_exists($this, $type)) {
                $this->{$type}();
            }
        }
    }

    public function ads() {
        $ads = AdsChannelModel::where('ac_state', 1)->select();
        $date = Carbon::yesterday()->format("Y-m-d");
        foreach ($ads as $c) {
            ReportService::updateAdReport($date, $c['ac_id']);
        }
    }

    protected function userReports2($v,$time,$output){
        $uid = $v['id'];
        $upd = [
            "username"=>$v['username'],
            "phone"=>$v['phone'],
            "mail"=>$v['mail'],
            "lebal"=>$v['lebal'],
            "reg_time"=>$v['add_time'],
            "phone_status"=>$v['phone_status'],
            "status"=>$v['status'],
            "rec_id"=>$v['rec_id'],
            "reg_ip"=>$v['reg_ip'],
            "balance"=>$v['balance'],
            "login_time"=>$v['login_time'],
            "first_deposit_time"=>Db::raw("IFNULL((SELECT add_time FROM dq_user_offline WHERE uid={$uid} AND first=1 LIMIT 1),0)"),
            "recent_withdraw_money_time"=>Db::raw("IFNULL((SELECT add_time FROM dq_user_withdrawal WHERE uid={$uid} AND status=3 ORDER BY id DESC LIMIT 1),0)"),
            "recent_deposit_money_time"=>Db::raw("IFNULL((SELECT add_time FROM dq_user_offline WHERE uid={$uid} AND (status=3 OR status=4) ORDER BY id DESC LIMIT 1),0)"),
            "deposit_nums"=>Db::raw("(SELECT COUNT(id) FROM dq_user_offline WHERE uid={$uid} AND (status=3 OR status=4))"),
            "deposit_total"=>Db::raw("IFNULL((SELECT SUM(arrival_price) FROM dq_user_offline WHERE uid={$uid} AND (status=3 OR status=4)),0)"),
            "withdrawal_nums"=>Db::raw("(SELECT COUNT(id) FROM dq_user_withdrawal WHERE uid={$uid} AND status=3)"),
            "withdrawal_total"=>Db::raw("IFNULL((SELECT SUM(w_price) FROM dq_user_withdrawal WHERE uid={$uid} AND status=3),0)"),
            "total_gift"=>Db::raw("IFNULL((SELECT SUM(price) FROM dq_artificial_balance WHERE uid={$uid} AND (a_type=3 or a_type=4)),0)"),
            "action_discount"=>Db::raw("IFNULL((SELECT SUM(ck_hl_amount+gd_amount) FROM dq_action_apply WHERE uid={$uid} AND ps_status=1),0)"),
            "lottery_total"=>Db::raw("IFNULL((SELECT SUM(price) FROM dq_lottery_record WHERE uid={$uid} AND prize_type=1),0)"),
            "rebate_total"=>Db::raw("IFNULL((SELECT SUM(sff_fy) FROM dq_third_stats_fyfy WHERE sff_uid={$uid}),0)"),
            "rebate1_total"=>Db::raw("IFNULL((SELECT SUM(sb_backmoney) FROM dq_third_stats_back WHERE sb_uid={$uid}),0)"),
            "promotion_bonus"=>Db::raw("IFNULL((SELECT SUM(money) FROM dq_agent_sell_log WHERE uid={$uid}),0)"),
            "mission_rewards"=>Db::raw("IFNULL((SELECT SUM(reward_amount_or_integral) FROM dq_task_record WHERE uid={$uid} AND reward_type=0),0)"),
            "vip_bonus"=>Db::raw("IFNULL((SELECT SUM(reward_amount) FROM dq_user_level_reward  WHERE uid={$uid} AND status=1),0)"),
            "level"=>Db::raw("IFNULL((SELECT level_name FROM dq_user_level WHERE id={$v['level_id']} LIMIT 1),0)")
        ];

        UserReportsModel::update($upd,['uid'=>$uid]);

        $output->writeln("【".date("Y-m-d H:i:s", $time)."】执行用户ID【{$uid}】更新数据");
    }

    protected function userReports($output){
        //将事务隔离级别修改为“未提交读”，这样不会影响其它事务的执行
        //科普：未提交读表示其它事务可以读到此事务未提交的数据 ，这是个危险的隔离级别（最弱隔离级别），会产生脏读或幻读的问题，但在你这个业务中刚好适用，因为这个业务中由于有更新业务，所以所有参与业务的表都会被锁（有索引的锁行，无索引的锁表【特别注意：不是说列有索引就一定会被MYSQL使用的】），但这个业务并不会更新这些参与数据组织的表，所以不会产生真正的脏读或幻读
        Db::execute("set @@session.transaction_isolation='READ-UNCOMMITTED'");
        $_sql = "select u.id from dq_user u left join dq_user_reports b on u.id=b.uid where b.uid is null order by u.id limit 0,1";
        $_id = Db::query($_sql);
        $id = 0;
        if( $_id ){
            $id = $_id[0]['id'];
        }

        $sql = "INSERT INTO dq_user_reports (uid,username,phone,mail,lebal,reg_time,first_deposit_time,recent_withdraw_money_time,recent_deposit_money_time,login_time,deposit_nums,deposit_total,withdrawal_nums,withdrawal_total,total_gift,action_discount,lottery_total,rebate_total,rebate1_total,promotion_bonus,mission_rewards,vip_bonus,phone_status,status,level,rec_id,reg_ip,balance)
            SELECT u.id,u.username,u.phone,u.mail,u.lebal,u.add_time,
            IFNULL((SELECT add_time FROM dq_user_offline d WHERE d.uid=u.id AND d.first=1 LIMIT 1),0) AS first_deposit_time,
            IFNULL((SELECT add_time FROM dq_user_withdrawal w WHERE w.uid=u.id AND w.status=3 ORDER BY id DESC LIMIT 1),0) AS recent_withdraw_money_time,
            IFNULL((SELECT add_time FROM dq_user_offline d WHERE d.uid=u.id AND (d.status=3 OR d.status=4) ORDER BY id DESC LIMIT 1),0) AS recent_deposit_money_time,
            u.login_time,
            (SELECT COUNT(id) FROM dq_user_offline d WHERE d.uid=u.id AND (d.status=3 OR d.status=4)) AS deposit_nums,
            IFNULL((SELECT SUM(arrival_price) FROM dq_user_offline d WHERE d.uid=u.id AND (d.status=3 OR d.status=4)),0) AS deposit_total,
            (SELECT COUNT(id) FROM dq_user_withdrawal w WHERE w.uid=u.id AND w.status=3) AS withdrawal_nums,
            IFNULL((SELECT SUM(w_price) FROM dq_user_withdrawal w WHERE w.uid=u.id AND w.status=3),0) AS withdrawal_total,
            IFNULL((SELECT SUM(price) FROM dq_artificial_balance a WHERE a.uid=u.id AND (a.a_type=3 or a.a_type=4)),0) AS total_gift,
            IFNULL((SELECT SUM(ck_hl_amount+gd_amount) FROM dq_action_apply a WHERE a.uid=u.id AND a.ps_status=1),0) AS action_discount,
            IFNULL((SELECT SUM(price) FROM dq_lottery_record a WHERE a.uid=u.id AND a.prize_type=1),0) AS lottery_total,
            IFNULL((SELECT SUM(sff_fy) FROM dq_third_stats_fyfy a WHERE a.sff_uid=u.id),0) AS rebate_total,
            IFNULL((SELECT SUM(sb_backmoney) FROM dq_third_stats_back a WHERE a.sb_uid=u.id),0) AS rebate1_total,
            IFNULL((SELECT SUM(money) FROM dq_agent_sell_log a WHERE a.uid=u.id),0) AS promotion_bonus,
            IFNULL((SELECT SUM(reward_amount_or_integral) FROM dq_task_record a WHERE a.uid=u.id and a.reward_type=0),0) AS mission_rewards,
            IFNULL((SELECT SUM(reward_amount) FROM dq_user_level_reward a WHERE a.uid=u.id and a.status=1),0) AS vip_bonus,
            u.phone_status,u.status,
            IFNULL((SELECT level_name FROM dq_user_level c WHERE c.id=u.level_id LIMIT 1),0) AS level,
            u.rec_id,u.reg_ip,u.balance
            FROM dq_user u left join dq_user_reports b on u.id=b.uid
      where u.id>={$id} and b.uid is null";

        $rlt=Db::execute($sql);
        $output->writeln("【".date("Y-m-d H:i:s", time())."】执行用户数量【".$rlt."】添加数据");
    }

    protected function updateRate($id, $currency_code, $type, $output){
        $rate = $this->curlS($currency_code);
        $rate_data = json_decode($rate, true);
        if(!empty($rate_data['data'])){
            $rate_val = 0;
            foreach($rate_data['data'] as $val){
                if($val['assetCode'] == "USDT"){
                    $rate_val = $val['quotation'];
                }
            }
            $data = [];
            if($type == 1){ // 法币 转 USDT
                $data['recharge_rate'] = 1/$rate_val;
                $data['withdrawal_rate'] = $rate_val;
            } else if($type == 2) { // USDT 转 法币
                $data['recharge_rate'] = $rate_val;
                $data['withdrawal_rate'] = 1/$rate_val;
            }
            if($rate_val > 0){
                CurrencyModel::where(["id" => $id])->save($data);
                $output->writeln("货币".$currency_code."更新汇率:". json_encode($data).", 数据ID:".$id);
            } else {
                $output->writeln("货币".$currency_code."获取汇率异常");
            }
        } else {
            $output->writeln("货币".$currency_code."获取汇率失败");
        }
    }
    
    protected function curlS($curr_code,$timeout = 10){
        $params = [
            "channels" => [
                "p2p","wallet","card","mobilum","ONLINE_BANKING","INSWITCH","APPLE_PAY","GOOGLE_PAY"
            ],
            "fiat" => $curr_code,
            "transactionType" => "buy"
        ];
        $params = json_encode($params);
        $url = "https://www.binance.com/bapi/fiat/v2/friendly/ocbs/buy/list-crypto";
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params)
        );
        
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        $contents = curl_exec($ch);
        curl_close($ch);
        return $contents;
    }
}
