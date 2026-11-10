<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\Utils;
use think\facade\Request;

class ReportFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function user()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $UserModel =new UserModel();
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["a.id|a.phone", "=", $param['username']];
            } else {
                $where[]=["a.username|a.mail|a.phone", "=", $param['username']];
            }
        }
        $rsps['count']=$UserModel
            ->alias("a")
            ->leftJoin("dq_user_level b","a.level_id=b.id")
            ->where($where)
            ->count();
        $rsps['data']=$UserModel
            ->alias("a")
            ->leftJoin("dq_user_level b","a.level_id=b.id")
            ->where($where)
            ->limit((($page-1)*$limit),$limit)
            ->order("a.id desc")
            ->field("a.*,b.level_name")
            ->select();

        foreach ($rsps['data'] as $key => $value) {
            $value = Utils::hideUserInfo($value);
            $rech = UserOfflineModel::getRechargeUserAmount([["a.uid","=",$value['id']]]);
            $with = UserWithdrawalModel::getWithdrawUserAmount([["a.uid","=",$value['id']]]);
            $give = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where("a_type",3)
                ->where([["c.id","=",$value['id']]])->sum("b.price");//赠送彩金
            $rsps['data'][$key]['total_rech']=$rech;
            $rsps['data'][$key]['total_with']=$with[2];
            $rsps['data'][$key]['give']=$give;
            $rsps['data'][$key]['add_time']=date("Y-m-d H:i:s",$value['add_time']);
            $rsps['data'][$key]['login_time']=$value['login_time']?date("Y-m-d H:i:s",$value['login_time']):'';
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}