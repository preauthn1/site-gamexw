<?php
namespace app\manage\controller;
use app\api\factory\AgentFactory;
use app\api\factory\UserFactory;
use app\common\lib\Uploads;
use app\common\model\AgentSellLogModel;
use app\common\model\AgentSettingModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\manage\factory\AgentSelllogFactory;
use think\facade\View;
use app\common\Utils;
use think\facade\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\redis\CommomRedis;
use think\facade\Db;
class AgentSelllog
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //派发列表
    public function list(\think\Request $request){
        $rsps = (new AgentSelllogFactory($request->token->uid))->list();
        return $rsps;
    }

    //代理返佣汇出
    public function excel(){
        $param= Request::param();
        $where=[];
        $AgentModel =new AgentSellLogModel();
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.create_time",">=", strtotime($time[0])];
            $where[]=["a.create_time","<=", strtotime($time[1])];
        }

        if (isset($param['username']) && $param['username']!='') {
            $uids = UserModel::where([['id|username','=',$param['username']]])->column("id");
            $where[] = ["a.uid","in", $uids];
        }
        if (isset($param['rec_code']) && $param['rec_code']!='') {
            $uids = UserModel::where([['rec_code','=',$param['rec_code']]])->column("id");
            $where[] = ["a.uid","in", $uids];
        }

        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=",$param['status']];
        }

            
        $tg_condition = Utils::sysInfo("tg_condition");
        $lable = [lang('一般用户'),lang('测试用户')];
        $status = [lang('待处理'),lang('拒绝'),lang('派发')];
        $ustatus = [lang('待审核'),lang('未通过'),lang('已通过')];
        
        $list = $AgentModel
            ->alias("a")
            ->field('a.*,'
                .'(select status from dq_user_agent where uid=a.uid) as ustatus'
            )
            ->where($where)
            ->order("a.id desc")
            ->select();
        $data = [];
        $agent_set = AgentSettingModel::column('stratum_number', 'id');
        foreach($list as $key => &$val){
            $val['create_time'] = date("Y-m-d H:i:s",$val['create_time']);
            $agentSettingDetail = AgentSettingModel::where('id', '=', $val['tg_set_id'])->find();
            $val['rec_person'] = UserFactory::getUserInvitePerson($val['uid'], $agentSettingDetail?$agentSettingDetail->start_time:0);
            $val['stratum_number'] = $agent_set[$val['tg_set_id']] ?? 0;
            $val['status'] = isset($status[$val['status']-1]) ? $status[$val['status']-1] : $val['status'];
            $val['ustatus'] = isset($ustatus[$val['ustatus']-1]) ? $ustatus[$val['ustatus']-1]:'';
            $data[] = [ $val['create_time'],$val['uid'],$val['rec_person'],$val['stratum_number'],$val['money'],$val['status'],$val['ustatus']];
        }
        $header = [
            lang('结算时间'),lang('UID'),lang('推广进度'),lang('推广要求人数'),lang('推广奖金'),lang('派发状态'),
            lang('账户状态')
        ];
        $file_name = genCsv($header, $data,lang('推广奖金数据导出').date('YmdHis',time()), false);
        $s3_url = Uploads::uploadS3(public_path() . $file_name);
        if (is_null($where)) {
            Utils::addOptLogByHandle('推广奖金数据导出');
        }
        $result['data'] = $s3_url ?: toMedia($file_name, '/');
        $result['code']=1;
        return Respons::run($result);
    }

    //一键派送
    public function distribute()
    {
        $id = Request::param('id');
        foreach ($id as $v){
            $this->sell($v);
        }
        return Respons::run(1);
    }

    //拒绝
    public function reject($id = ''){
        $param=Request::param();
        if (isset($param['edit']) || $id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('selllog_'. $id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            $AgentModel=new AgentSellLogModel();
            //先修改状态

            $agent_where=[];
            $agent_where[]=["id", "=", $id];
            $data=$AgentModel->where($agent_where)->find();
            //判断状态是否未处理
            if($data['status'] == 1) {
                //先修改状态
                $res = $AgentModel->update(['status' => 2, 'id' => $id]);
                $ldata = ['status' => 2, 'id' => $id];
                return Respons::run($res ? 1 : 0,'','',$data,$ldata,$AgentModel,lang('推广奖金状态修改'));
            }
        }
    }

    //派送
    public function sell($id = ''){
        $param=Request::param();
        if (isset($param['edit']) || $id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('selllog_'.$id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            $AgentModel=new AgentSellLogModel();
            $agent_where=[];
            $agent_where[]=["id","=",$id];
            $data=$AgentModel->where($agent_where)->find();
//            $rec_person = AgentService::getAgentSellUsers($data['uid']);
            $rec_person = UserFactory::getUserInvitePerson($data['uid']);
            $set=AgentSettingModel::where('id', $data['tg_set_id'])->find();
            if( !$set ){
                return Respons::run(0,[],lang('对应奖励已被删除，无法操作'));
            }
            if ($set['stratum_number'] > $rec_person) {
                return Respons::run(0,[],lang('该不符合领取资格,任务进度:') . "{$rec_person}/{$set['stratum_number']}");
            }

            $amount = $data['money'];
//            $receiveRewardPeopleNum = $data->received_people_num;
//            $nextAgentSettingDetail = null;
            //判断是否是重复领取的奖励
//            if( $set->reward_type == AgentSettingModel::REWARD_TYPE_ONE ){
//                $result = (new AgentFactory($data['uid']))->judgeOneRewardType($set, $data, $rec_person);
//                if(!$result){
//                    ajax_return("对应的人数奖励已领取，请完成条件后再来");
//                }
//                $amount = $result['amount'];
//                $receiveRewardPeopleNum = $result['receive_reward_people_num'];
//                $nextAgentSettingDetail = $result['next_agent_setting_detail'];
//            }
            //判断状态是否未处理
            if($data['status'] == 1){
                Db::startTrans();
                try {
                    $save = [];
//                    if(
//                        ($set->reward_type == AgentSettingModel::REWARD_TYPE_ONE &&
//                            isset($nextAgentSettingDetail) &&
//                            $rec_person == $nextAgentSettingDetail->stratum_number - 1) ||
//                        $set->reward_type == AgentSettingModel::REWARD_TYPE_TOTAL
//                    ){
                        //判断单次奖励时人数等于下阶段要求人数减1或总数奖励时才能更改为完成状态
                        $save['status'] = 3;
//                        AgentSellLogModel::where('id', '=', $data['id'])->update($save);
//                    }
                    $res = AgentSellLogModel::where('id', '=', $data['id'])->update($save);
//                    $res = AgentSellLogModel::where('id', '=', $data['id'])->inc('received_people_num', $receiveRewardPeopleNum)->update();

                    //修改余额
                    $UserModel = new UserModel();
                    $UserModel->cz_balance($data['uid'],$amount,'+',true,'推广奖励', 7, $set['savings'], 1, $data['id'], true, 101);

                    // 提交事务
                    Db::commit();
                    $ldata = ['status' => 3, 'id' => $id];
                    return Respons::run($res?1:0,'','',$data,$ldata,$AgentModel,lang('推广奖金状态修改'));

                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return Respons::run(0,[],$e->getMessage());
                }
            }
        }
    }

    public function batch() {
        $type = request()->param('type/d');
        $id_str = request()->param('id/s');
        if (!in_array($type, [1, 2]) || empty($id_str)) { // 1拒绝 2通过
            return Respons::run(0,[],lang('参数有误'));
        }
        $CommomRedis= new CommomRedis();
        if (!$CommomRedis->lock('sell_batch')) {
            return Respons::run(0,[],lang('请勿频繁访问'));
        }
        $ids = explode(",", $id_str);
        foreach ($ids as $id) {
            if ($type == 1) {
                $this->reject($id);
            } else {
                $this->sell($id);
            }
        }
        return Respons::run(1);
    }
}