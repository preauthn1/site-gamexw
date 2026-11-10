<?php
namespace app\manage\controller;
use app\common\model\AgentRakeBackModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\model\AgentModel;
use app\manage\factory\AgentRakebackFactory;
use think\facade\View;
use app\common\Utils;
use think\facade\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\redis\CommomRedis;
use think\facade\Db;
class AgentRakeback
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //代理返佣列表
    public function list(\think\Request $request){
        $rsps = (new AgentRakebackFactory($request->token->uid))->list();
        return $rsps;
    }

    public function list_count() {
        $param=Request::param();
        $where=[];
        $redis_key = '';

        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.create_time",">=", Utils::getQueryDateTime($time[0])];
            $where[]=["a.create_time","<=", Utils::getQueryDateTime($time[1], true)];
            $redis_key = json_encode($where);
        }

        if (isset($param['username']) && $param['username']!='') {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }       
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        $redis_key .= ($param['username'] ?? '') . ($param['status'] ?? '') . ($param['uid'] ?? '');
        $CommomRedis= new CommomRedis();
        if (!$CommomRedis->get(md5($redis_key))) {
            $AgentModel =new AgentRakeBackModel();
            $rsps['pending_price'] = sprintf("%.2f", $AgentModel->alias("a")->where($where)->where('a.status=1')->sum('a.money'));
            $rsps['distribute_price'] = sprintf("%.2f",$AgentModel->alias("a")->where($where)->where('a.status=3')->sum('a.money'));
            $rsps['refuse_price'] = sprintf("%.2f",$AgentModel->alias("a")->where($where)->where('a.status=2')->sum('a.money'));
            $rsps['manual_price'] = sprintf("%.2f",$AgentModel->alias("a")->where($where)->where('a.status=4')->sum('a.money'));
            $CommomRedis->set(md5($redis_key), json_encode($rsps), 86400);
        } else {
            $rsps = json_decode($CommomRedis->get(md5($redis_key)), true);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //代理返佣汇出
    public function excel(){
        $param= Request::param();
        $where=[];
        $AgentModel =new AgentRakeBackModel();
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.create_time",">=", Utils::getQueryDateTime($time[0])];
            $where[]=["a.create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }

        if (isset($param['username']) && $param['username']!='') {
            $uids = UserModel::where([['id|username','=',$param['username']]])->column("id");
            $where[] = ["a.uid","in", $uids];
        }

        if (isset($param['s_status']) && $param['s_status']!='') {
            if (strpos(trim($param['s_status']),',')) {
                $where[]=["a.status","in",$param['s_status']];
            }else {
                $where[]=["a.status","=",$param['s_status']];
            }
        }

        $lable = [lang('一般用户'),lang('测试用户')];
        $status = [lang('待处理'),lang('拒绝'),lang('派发'),lang('手动派发')];
        $list = $AgentModel->alias("a")->where($where)
            ->field('a.*,'
                .'(select rec_code from dq_user where id=a.uid) as rec_code,'
                .'(select username from dq_user where id=a.uid) as username,'
                .'(select lebal from dq_user where id=a.uid) as lebal')
            ->order("a.id desc")
            ->select();
        $data = [];
        foreach ($list as &$v){
            $v['lebal'] = isset($lable[$v['lebal']-1]) ? $lable[$v['lebal']-1]:'';
            $v['status'] = isset($status[$v['status']-1]) ? $status[$v['status']-1]:'';
            $v['create_time'] =  empty($v['create_time']) ?'':date("Y-m-d H:i:s",$v['create_time']);
            $v['update_time'] = empty($v['update_time']) ?'':date("Y-m-d H:i:s",$v['update_time']);
            $data[] = [ $v['lebal'],$v['username'],$v['rec_code'],$v['money'],$v['zd_number'].' ',$v['status'],$v['remark'],$v['create_time'],$v['update_time']];
        }
        $header = [
            lang('用户标签'),lang('用户名'),lang('推广码'),lang('代理返佣'),lang('注单编号'),lang('状态'),lang('备注'),
            lang('返佣结算时间'),lang('结算时间')
        ];
        Respons::run(1,'','','','',$AgentModel,lang('代理返佣数据导出'));
        Excel($header,$data,lang('代理返佣数据').date('Ymd',time()));
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

    //一键拒绝
    public function refused(){
        $id = Request::param('id');
        foreach ($id as $v){
            $this->reject($v);
        }
        return Respons::run(1);
    }

    //拒绝
    public function reject($id = ''){

        $param=Request::param();

        if (isset($param['edit']) || $id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('rakeback_'.$id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            $AgentModel=new AgentRakeBackModel();
            //先修改状态
            $agent_where=[];
            $agent_where[]=["id","=",$id];
            $data=$AgentModel->where($agent_where)->find();
            //判断状态是否未处理
            if($data['status'] == 1) {
                //先修改状态
                $res = $AgentModel->update(['status' => 2, 'id' => $id]);
                $ldata = ['status' => 2, 'id' => $id];
                return Respons::run($res ? 1 : 0,'','',$data,$ldata,$AgentModel,lang('代理返佣状态修改'));
            }
        }
    }

    //派送
    public function sell($id = ''){
        $param=Request::param();
        if (isset($param['edit']) || $id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('rakeback_'.$id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            $AgentModel=new AgentRakeBackModel();
            $agent_where=[];
            $agent_where[]=["id","=",$id];
            $data=$AgentModel->where($agent_where)->find();
            //判断状态是否未处理
            if($data['status'] == 1){
                Db::startTrans();
                try {
                    $commission_water = Utils::sysInfo("commission_water");
                    //先修改状态
                    $upd['status'] = 4;
                    $upd['id'] = $id;
                    if($commission_water > 0){
                        $upd['savings'] = $commission_water * $data['money'];
                    }
                    $upd['update_time'] = time();
                    $res = $AgentModel->update($upd);

                    //修改余额
                    $UserModel = new UserModel();
                    $UserModel->cz_balance($data['uid'], $data['money'],'+', true, '代理返佣', 6, 0, 1, $id, true, 101);
                    // 提交事务
                    Db::commit();
                    $upd['update_time'] = date('Y-m-d H:i:s',$upd['update_time']);
                    return Respons::run($res ? 1 : 0,'','',$data,$upd,$AgentModel,lang('代理返佣状态修改'));
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return Respons::run(0,[],$e->getMessage());
                }
            }
        }
    }
}