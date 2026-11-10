<?php

namespace app\manage\factory;

use app\api\factory\AgentFactory;
use app\api\factory\UserFactory;
use app\common\lib\Respons;
use app\common\model\AgentSellLogModel;
use app\common\model\AgentSettingModel;
use app\common\model\UserModel;
use app\common\service\AgentService;
use app\common\Utils;
use think\facade\Request;

class AgentSelllogFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $tg_condition = Utils::sysInfo("tg_condition");
        $AgentModel =new AgentSellLogModel();
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.create_time",">=", strtotime($time[0])];
            $where[]=["a.create_time","<=", strtotime($time[1])];
        }

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $uid = UserModel::where('id|phone', $param['username'])->value("id");
            } else {
                $uid = UserModel::where('phone|mail|username', $param['username'])->value("id");
            }
            $where[] = ["a.uid","=", $uid];
        }
        if (isset($param['rec_code']) && $param['rec_code']!='') {
            $uids = UserModel::where([['rec_code','=',$param['rec_code']]])->column("id");
            $where[] = ["a.uid","in", $uids];
        }

        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=",$param['status']];
        }

        $rsps['data'] = $AgentModel
            ->alias("a")
            ->field('a.*,'
                .'(select username from dq_user where id=a.uid limit 1) as username,'
                .'(select mail from dq_user where id=a.uid limit 1) as mail,'
                .'(select phone from dq_user where id=a.uid limit 1) as phone,'
                .'(select status from dq_user_agent where uid=a.uid limit 1) as ustatus'
            )
            ->where($where)
            ->order("a.id desc")
            ->paginate($limit,false,['query' => $param]);
        $agent_set = AgentSettingModel::column('stratum_number', 'id');
        foreach($rsps['data'] as $key => &$val){
            $val['create_time'] = date("Y-m-d H:i:s",$val['create_time']);
//            $val['rec_person'] = AgentService::getAgentSellUsers($val['uid']);
            $val['stratum_number'] = $agent_set[$val['tg_set_id']] ?? 0;
            //判断是否是重复领取的奖励
            $agentSettingDetail = AgentSettingModel::where('id', '=', $val['tg_set_id'])->find();
            $val['rec_person'] = UserFactory::getUserInvitePerson($val['uid'], $agentSettingDetail?$agentSettingDetail->start_time:0);
            $val['is_not_sell'] = 0;#是否不能派发
            if( $agentSettingDetail && $agentSettingDetail['reward_type'] == AgentSettingModel::REWARD_TYPE_ONE ){
                $result = (new AgentFactory($val['uid']))->judgeOneRewardType($agentSettingDetail, $val, $val['rec_person']);
                if(!$result){
                    $val['is_not_sell'] = 1;
                }
            }
        }
        $rsps['stat'] = $AgentModel
            ->alias("a")
            ->field('a.status, SUM(a.money) AS money')
            ->where($where)
            ->group("a.status")
            ->select();
        $rsps['code']=1;
        $rsps['sets']=$agent_set;
        return Respons::run($rsps);
    }
}