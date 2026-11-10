<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\redis\CommomRedis;
use app\common\service\UserOfflineService;
use app\common\Utils;
use think\facade\Request;

class AgentListFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function lists()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[["b.status","=",3]];//通过的代理
        $where1=[];
        $where2=[];
        $redis_key_arr = [$page];

        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $redis_key_arr[] = $date[0].$date[1];
        $where1[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
        $where1[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        $where2[]=["a.update_time",">=", Utils::getQueryDateTime($date[0])];
        $where2[]=["a.update_time","<=", Utils::getQueryDateTime($date[1], true)];

        if (isset($param['username']) && $param['username']!='') {
            $redis_key_arr[] = $param['username'];
            if (is_numeric($param['username'])) {
                $where[]=["a.id|a.phone","=", $param['username']];
            } else {
                $where[]=["a.username|a.mail|a.phone","=", $param['username']];
            }
        }
        $where1[] = ["a.lebal","=",1];//正式用户
        $where2[] = ["a.lebal","=",1];//正式用户
        $redis_key = md5(json_encode($redis_key_arr));
        $CommomRedis= new CommomRedis();

        $rsps['count']= UserModel::alias("a")->join("dq_user_agent b","a.id=b.uid")->where($where)->count();
        if (!$CommomRedis->get($redis_key)) {
            $data = UserModel::alias("a")->join("dq_user_agent b","a.id=b.uid")->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->field("a.*")->select();
            $arr=[];
            foreach ($data as $key => $value) {
                $agent_sql = unLevelSql($value['id']);
                $arr[$key]['count_pnumber'] = UserModel::alias("a")->where("a.id in(".$agent_sql.") and a.lebal=1")->count() - 1;#减去自身
                $arr[$key]['new_reg'] = UserModel::alias("a")->where("a.id in(".$agent_sql.")")->where($where1)->count();   //新注册人数  默认全部
                $arr[$key]['recharge_pnumber'] = UserOfflineModel::getRechargeUserCount($where2,"a.uid in(".$agent_sql.")");//充值人数
                $recharge = UserOfflineModel::getRechargeUserAmount($where2,"a.uid in(".$agent_sql.")");
                $arr[$key]['recharge_amount']=$recharge??0;

                $recharge = UserOfflineModel::getRechargeUserAmount($where2,"a.uid in(".$agent_sql.") and a.first=1");//充值人数
                $arr[$key]['first_recharge_amount']=$recharge??0;
                $arr[$key]['first_recharge_pnumber'] =  UserOfflineModel::getRechargeUserCount($where2, "a.uid in(".$agent_sql.") and a.first=1"); //首充总人数
                $arr[$key]['first_recharge_zb'] = round((($arr[$key]['recharge_pnumber']!=0?$arr[$key]['first_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2);
                $arr[$key]['two_recharge_pnumber'] =  $arr[$key]['recharge_pnumber'] - $arr[$key]['first_recharge_pnumber'];  //复充总人数
                $arr[$key]['two_recharge_zb'] = round((($arr[$key]['recharge_pnumber']!=0?$arr[$key]['two_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2);
                $arr[$key]['two_recharge_pnumber2'] =  UserOfflineService::getAgainRechargeAmount2($where2,"TotalRechargeUser2_3_agent","uid in(".$agent_sql.")");
                $arr[$key]['two_recharge_zb2']=round((($arr[$key]['two_recharge_pnumber2']!=0?$arr[$key]['two_recharge_pnumber2']/$arr[$key]['recharge_pnumber']:0)*100),2);

                $arr[$key]['withdrawal_pnumber'] = UserWithdrawalModel::getWithdrawUserCount($where2,"a.uid in(".$agent_sql.")");
                $arr[$key]['withdrawal_amount'] = UserWithdrawalModel::getWithdrawUserAmount($where2,"a.uid in(".$agent_sql.")")[2];

                $arr[$key]['max_level'] = $value['rec_level'];
                $arr[$key]['rec_code'] = $value['rec_code'];
                $arr[$key]['username'] = !is_null($value['mail']) ? $value['mail'] : (!is_null($value['phone']) ? $value['phone'] : $value['username']);
                $arr[$key] = Utils::hideUserInfo($arr[$key]);
            }
            $CommomRedis->set($redis_key, json_encode($arr), 600);
        } else {
            $arr = json_decode($CommomRedis->get($redis_key), true);
        }
        $rsps['data']=$arr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}