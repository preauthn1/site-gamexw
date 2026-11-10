<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\PayTypeModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserLevelModel;
use app\common\model\UserWithdrawalModel;
use app\common\Utils;
use think\facade\Request;
use think\facade\Config;

class PaySetFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function count()
    {
        $pay_type = Util::getPayType();
        $is_online_pay = 0;
        if(isset($pay_type[0]) && isset($pay_type[1])){
            $is_online_pay = 1;
        }
        $is_online_pay = 1;#什么情况下都查询
        $param = Request::instance()->param();
        $limit=$param['limit'] ?? 10;
        $date_time = [];
        if($is_online_pay == 1){
            if(!empty($param['date']) && isset($param['date'])){
                $date=explode(' - ',$param['date']);
                $date_time[]=["add_time",">=", Utils::getQueryDateTime($date[0])];
                $date_time[]=["add_time","<=", Utils::getQueryDateTime($date[1], true)];
            }
            $rsps['data'] = PaySetModel::where(['isdel'=>0])
                ->order("pay_status DESC,id DESC")
                ->paginate($limit);
            $wh['lebal'] = 1;


            foreach($rsps['data'] as &$val){
                $where=[];
                $where_with = [];
                if(count($date_time) > 0){
                    $where = $where_with = $date_time;
                }
                $where[] = ['pay_id','=',$val['id']];
                $val['count_num'] = UserOfflineModel::where($where)->where($wh)->count();
                $val['count_price'] = UserOfflineModel::where($where)->where($wh)->sum('apply_price');
                $val['count_price_defa'] = UserOfflineModel::where($where)->where($wh)->sum('arrival_price');
                $where[] = ['status','in',[3,4]];
                $val['count_success_num'] = UserOfflineModel::where($where)->where($wh)->count();
                $val['count_success_price'] = UserOfflineModel::where($where)->where($wh)->sum('apply_price');
                $val['count_success_price_defa'] = UserOfflineModel::where($where)->where($wh)->sum('arrival_price');
                if($val['count_num'] == 0){
                    $val['percentage'] = '0%';
                }else{
                    $val['percentage'] = round($val['count_success_num'] /  $val['count_num'] * 100,2) . "%";
                }
                $where_with[] = ['method_id','=',$val['id']];
                $val['with_count_num'] = UserWithdrawalModel::where($where_with)->where($wh)->count();
                $val['with_count_price'] = UserWithdrawalModel::where($where_with)->where($wh)->sum('w_price');
                $val['with_count_price_third'] = UserWithdrawalModel::where($where_with)->where($wh)->sum('submit_third_price');
                $where_with[] = ['status','in',[3,4]];
                $val['with_count_success_num'] = UserWithdrawalModel::where($where_with)->where($wh)->count();
                $val['with_count_success_price'] = UserWithdrawalModel::where($where_with)->where($wh)->sum('w_price');
                $val['with_count_success_price_third'] = UserWithdrawalModel::where($where_with)->where($wh)->sum('submit_third_price');
                if($val['with_count_num'] == 0){
                    $val['with_percentage'] = '0%';
                }else{
                    $val['with_percentage'] = round($val['with_count_success_num'] /  $val['with_count_num'] * 100,2) . "%";
                }
                $val['pay_type_name'] = lang(Util::getPayTypeName($val['pay_type']));
            }
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function lists()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $where[] = ['isdel', '=', 0];
        if (isset($param['pay_sys_sign']) && $param['pay_sys_sign'] != '') {
            $where[] = ['pay_sys_sign|pay_name|remarks', 'LIKE', "%{$param['pay_sys_sign']}%"];
        }
        foreach (['is_hot', 'pay_status', 'is_withdra'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                $where[] = [$key, '=', $param[$key]];
            }
        }
        $rsps['count'] = PaySetModel::where($where)->count();
        $rsps['data'] = PaySetModel::where($where)->with('currency')->limit((($page-1)*$limit),$limit)->order("sort desc,id DESC")->select();
        $pay_types = PayTypeModel::where("1=1")->column("name", 'id');
        foreach($rsps['data'] as $key => &$val){
            $val['pay_type_name'] = lang(Util::getPayTypeName($val['pay_type']));
            $val['pay_type_n'] = isset($pay_types[$val['pay_types']]) ? $pay_types[$val['pay_types']] : 0;
            if($val['agent_id'] > 0){
                $val['agent_name'] = UserModel::where(['id' => $val['agent_id']])->value('username');
            }else{
                $val['agent_name'] = "";
            }
        }
        $usdt_type = Config::get('custom.usdt_type');
        $vip = UserLevelModel::where("1=1")->column("id,level_name");
        $rsps['paytype'] = Utils::getOptionsByConfig(Util::getPayType());
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['code']=1;
        $rsps['currency'] = Util::getCurrency();
        $rsps['usdt_type'] = $usdt_type;
        $rsps['pay_types'] = PayTypeModel::where("1=1")->column("id,name");
        $rsps['vip'] = $vip;

        return Respons::run($rsps);
    }
}