<?php
namespace app\admin\controller;
use app\common\model\UserModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\UserOfflineModel;
use app\common\model\OlPayModel;
use app\common\model\ActionApplyModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\lib\Respons;
use app\common\Utils;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class ChartYunying
{	
	protected $middleware = ['adminCheckLogin',"Language"];

    //
    public function index(){
        $param=Request::param();
        if (isset($param['user_data'])) {
            $user_data=$this->user_data();
            
            $rsps['code'] = 1;
            $rsps['data']['user_data'] = $user_data;
            
            $user_price = $this->user_price();
            $rsps['data']['user_price'] = $user_price;
            
            $order_data = $this->order_data();
            $rsps['data']['order_data'] = $order_data;
            
            $action_data = $this->action_data();
            $rsps['data']['action_data'] = $action_data;
            
            $agent_data = $this->agent_data();
            $rsps['data']['agent_data'] = $agent_data;
            return Respons::run($rsps);
        }
        return View::fetch('chart_yunying/index');
    }


    //用户数据
    private function user_data(){
        $where = $this->getMap();        
        $new_reg = UserModel::alias("a")->where($where)->count();//新注册
        $new_order = 0;//
        $new_recharge = UserOfflineModel::alias("a")->where('first', 1)->where($where)->count();//首充人数
        $recharge = UserOfflineModel::alias("a")->where($where)->count();//充人数

        $rsps['new_reg'] = $new_reg;
        $rsps['new_order'] = $new_order;
        $rsps['new_recharge'] = $new_recharge;
        $rsps['recharge'] = $recharge;
        return $rsps;
    }

    //充提数据
    private function user_price(){
        $where = $this->getMap();
        
        $apply_price = UserOfflineModel::alias("a")->where($where)->where("a.status",3)->sum("a.apply_price");
        $pay_price = OlPayModel::alias("a")->where($where)->where("a.pay_status",1)->sum("a.pay_price");        
        $recharge = $apply_price+$pay_price;//充值总金额
        
        $withdraw = UserWithdrawalModel::alias("a")->where($where)->where("a.status",3)->sum("a.w_price");//提现总金额
        $new_recharge = UserOfflineModel::alias("a")->where('first', 1)->where($where)->sum("a.apply_price");

        $rsps['withdraw'] = $withdraw;
        $rsps['new_recharge'] = $new_recharge;
        $rsps['recharge'] = $recharge;
        return $rsps;
    }

    //营运数据
    private function order_data(){
        $where = $this->getMap();

        $rsps['order_count'] = 0;
        $rsps['win_count'] = 0;
        $rsps['win_amount'] = 0;
        $rsps['order_amount'] = 0;
        return $rsps;
    }

    //优惠数据
    private function action_data(){
        $where = $this->getMap();
        if(isset($where[2])){
            unset($where[2]);
        }
        $ActionApplyModel=new ActionApplyModel();
        $sc_amount = ActionApplyModel::alias("a")
            ->join("dq_action b","a.action_id=b.id")
            ->join("dq_user_ck c","a.user_ck_id=c.id")
            ->where($where)
            ->where("a.ps_status",1)
            ->where("b.base_type",1)
            ->sum("c.ck_hl_amount");//注册首存

        $zc_amount = ActionApplyModel::alias("a")
            ->join("dq_action b","a.action_id=b.id")
            ->join("dq_user_ck c","a.user_ck_id=c.id")
            ->where($where)
            ->where("a.ps_status",1)
            ->where("b.base_type",2)
            ->sum("c.ck_hl_amount");//再存优惠

        $yh_amount = ActionApplyModel::alias("a")
            ->join("dq_action b","a.action_id=b.id")
            ->where($where)
            ->where("a.ps_status",1)
            ->where("b.base_type",3)
            ->sum("a.gd_amount");//一般优惠

        $tyj_amount = ActionApplyModel::alias("a")
            ->join("dq_action b","a.action_id=b.id")
            ->where($where)
            ->where("a.ps_status",1)
            ->where("b.base_type",4)
            ->sum("a.tyj_amount");//体验金

        $rsps['sc_amount'] = $sc_amount;
        $rsps['zc_amount'] = $zc_amount;
        $rsps['yh_amount'] = $yh_amount;
        $rsps['tyj_amount'] = $tyj_amount;
        return $rsps;
    }

    //返佣推广
    private function agent_data(){
        $info=Request::param('info');
        $now_date=Utils::getQueryDateTime(date("Y-m-d"));
        $now_end_date=Utils::getQueryDateTime(date("Y-m-d"), true);
        $where[0]=["a.create_time",">=",$now_date];
        $where[1]=["a.create_time","<=",$now_end_date];
        if (isset($info['date']) && $info['date']!='') {
            $date=explode(' - ',$info['date']);
            $where[]=["a.create_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.create_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
       
        $agent_commission = AgentRakeBackModel::alias("a")->where($where)->sum("a.money");//预估返佣
        $spread_commission = AgentSellLogModel::alias("a")->where($where)->sum("a.money");//预估推广

        $rsps['agent_commission'] = $agent_commission;
        $rsps['spread_commission'] = $spread_commission;
        return $rsps;
    }
    
    public function getMap(){
        $info=Request::param('info');
        $where = [];
        $now_date=Utils::getQueryDateTime(date("Y-m-d"));
        $now_end_date=Utils::getQueryDateTime(date("Y-m-d"), true);
        $where[0]=["a.add_time",">=",$now_date];
        $where[1]=["a.add_time","<=",$now_end_date];
        if (isset($info['date']) && $info['date']!='') {
            $date=explode(' - ',$info['date']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        
        if(!empty($info['lebal'])){
            $where[2] = ["a.lebal", "=", $info['lebal']];
        }
                
        return $where;
    }
}
