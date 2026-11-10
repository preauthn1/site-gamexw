<?php
namespace app\promote\controller;
use app\common\lib\Uploads;
use app\common\model\UserAgentModel;
use app\common\model\UserLevelModel;
use app\common\model\UserAdminLogModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\UserModel;
use app\common\service\MemberService;
use app\common\Utils;
use app\promote\controller\Base;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
use think\facade\Session;
use app\common\redis\CommomRedis;
class UserAgent extends Base
{	
	//列表
    public function list(){
        $param=Request::param();
        $page=$param['page'];
        $limit=$param['limit'];
        $where=[];
        if (isset($param['username']) && !empty($param['username'])) {
            $where[]=["b.username|b.mail|b.phone","=", $param['username']];
        }
        if (isset($param['rec_code']) && $param['rec_code']!='') {
            $where[]=["b.rec_code", "=", $param['rec_code']];
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=",$param['status']];
        }
        if (isset($param['type']) && $param['type']!='') {
            $where[]=["a.type","=",$param['type']];
        }
        if (isset($param['rec_id']) && $param['rec_id']!='') {
            //查询rec_id
            $sqlwhere = [];
            if (is_numeric($param['rec_id'])) {
                $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
            } else {
                $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
            }
            $rec_info = UserModel::where($sqlwhere)->find();
            if (empty($rec_info)) {
                $result['code'] = 0;
                $result['msg'] = '上级UID或邀请码有误';
                return Respons::run($result);
            }
            if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                $where[]=["b.tree", "like", "{$rec_info['tree']}%"];
            } else {
                $where[]=["b.rec_id", "=", $rec_info['id']];
            }
        }
        if (isset($param['register_time']) && $param['register_time']!='') {
            $date = explode(' - ',$param['register_time']);
            $where[]=["b.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["b.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        // 只显示内部代理
//        $where[]=["a.type", "=", 1];
        $agent = $this->user_agent;
        $sql = UserModel::where([['tree','like', $this->user['tree'].'%']])->field('id')->buildSql();
        $whereStr = 'b.id in ('.$sql .')';
        $user_level_data = UserLevelModel::column('level_name', 'id');
        $UserAgentModel =new UserAgentModel();
        $rsps['data']=$UserAgentModel
            ->alias("a")
            ->Join("dq_user b","a.uid=b.id")
            ->where($where)->where($whereStr)
            ->order("a.id desc")
            ->field("a.*,b.username,b.lebal,b.rec_code,b.rec_id,b.lebal,b.level_id,b.phone,b.qh,b.balance,b.mail,"
                ."(select username from dq_user where id=b.rec_id ) as rec_name,b.add_time as register_time,b.reg_ip")
            ->paginate($limit,false,['query' => $param]);

        foreach($rsps['data'] as &$value){
            $value['recharge'] = MemberService::getRechargeByUid($value['uid']);
            $value['withdraw'] = MemberService::getWithdrawByUid($value['uid']);
            $value['level_name'] = $user_level_data[$value['level_id']];
            $value['apply_time'] = date("Y-m-d H:i:s",$value['apply_time']);
            $value['rate'] = sprintf4f($value['rate'] * 100);
        }

        if (isset($param['export']) && $param['export'] == 1) {
            $list = $rsps['data'];
            $statusArr = [
                1 =>  lang('待审核'),
                2 =>  lang('未通过'),
                3 =>  lang('已通过'),
            ];
            foreach ($list as $value) {
                $value['lebal']      = $value['lebal'] == 1 ? lang('普通用户') : lang('测试用户');
                $value['status']     = $statusArr[$value['status']] ?? lang('已通过');
                $value['type_name']     = $value['type'] == 1 ? lang('内部代理') : lang('外部代理');
                $value['rec_top_user_id'] = MemberService::getTopAgentByTree($value['tree'], true);
            }
            $header = [
                'id' => lang('UID'),
                'phone' => lang('手机号'),
                'qh' => lang('区号'),
                'mail' => lang('邮箱'),
                'lebal' => lang('用户标签'),
                'rec_code' => lang('推广码'),
                'level_name' => lang('会员等级'),
                'balance' => lang('余额'),
                'recharge' => lang('总充值'),
                'withdraw' => lang('总提现'),
                'rec_name' => lang('上级代理'),
                'rec_top_user_id' => lang('顶级代理UID'),
                'register_time' => lang('注册时间'),
                'reg_ip' => lang('注册ip'),
                'type_name' => lang('代理类型'),
                'rate' => lang('返佣比例'),
                'status' => lang('状态'),
            ];
            $file_name = genCsv($header, $list,lang('代理列表').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('代理列表导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
    
    //修改资料
    public function edit(){
        $param = $this->request->param();
        $info = $param['info'];
        $info['rate'] = $info['rate'] ?? 0;

        $my_agent_rate = $this->user_agent['rate'] * 100;
        if($info['rate'] > 0 && $info['rate'] >= $my_agent_rate){
            return $this->error(lang("返佣比例不能大于{:rate}%", ['rate' => $my_agent_rate]));
        }
        $agent = UserAgentModel::where('id', $info['id'])->find();
        if (empty($agent)) {
            return $this->error('数据不存在');
        }
        $agent->status = in_array($info['status'], [1, 2, 3]) ? $info['status'] : 1;
        //仅内部代理可以改比例
        if($agent->type == 1){
            $agent->rate = intval($info['rate']) / 100;
        }
        $rs = $agent->save();
        $status = [1 => "待审核", 2 => "拒绝", 3 => "通过"];
        if($rs !== false){
            $msg_text = "分佣比例：".$info['rate'].",状态：".$status[$info['status']];
            UserAdminLogModel::add_log($this->user_id, $msg_text,"修改下级代理:".$agent['uid']);
        } else {
            return $this->error();
        }
        return $this->success();
    }
    
    //编辑
    public function status_edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('agent_status_edit'.$info['id'])) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            $data = UserAgentModel::where('id',$info['id'])->field('id,status')->find();
            $UserAgentModel=new UserAgentModel();
            $res=$UserAgentModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$UserAgentModel,lang('会员代理审核状态编辑'));
        }
    }

    public function add(){
        $param=Request::param();
        $info=$param['info'];
        $info['rate'] = $info['rate'] ?? 0;
        try {
            validate(\app\common\validate\UserValidate::class)->scene('create')->check($info);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            return Respons::run(0,[],$e->getError());
        }
        if(!checkString($info['password'])){
            return Respons::run(0,[], "密码只能数字和字母组合");
        }
        if($info['username'] == $info['password']){
            return Respons::run(0,[], "密码不能与用户名相同");
        }
        if (empty($info['mail']) && empty($info['phone'])) {
            return Respons::run(0,[], "邮箱和手机号必填一个");
        }
        $user = UserModel::where("username|mail|phone",$info['username'])->find();
        if (!empty($user)) {
            return Respons::run(0,[], "账号已存在！");
        }

        if (!empty($info['mail'])) {
            $user = UserModel::where("username|mail|phone",$info['mail'])->find();
            if (!empty($user)) {
                return Respons::run(0,[], "邮箱已存在！");
            }
        } else {
            unset($info['mail']);
        }

        if (!empty($info['phone'])) {
            $user = UserModel::where("username|mail|phone", $info['phone'])->find();
            if (!empty($user)) {
                return Respons::run(0,[], "手机号已存在！");
            }

        } else {
            unset($info['phone']);
        }

        $UserModel =new UserModel();
        $UserAgentModel=new UserAgentModel();
        $useragent = $this->user_agent;

        $tree = $this->user['tree'];
        $proxy_tree = $useragent['proxy_tree'];
        if ((!empty($info['rate'])) &&  ($info['rate'] / 100) >= $useragent['rate']) {
            return Respons::run(0,[], "下级返佣比例不能超过上级！");
        }

        Db::startTrans();
        try {

            $rate = $info['rate'];
            $type = $info['type'] ?? 1;
            unset($info['rate'],$info['type']);
            $info['add_time'] = time();
            $info['reg_ip'] = Util::get_ip();
            $info['password'] = Util::userMd5Password($info['password']);
            $info['rec_id'] = $this->user['id'];
            $info['rec_id2'] = $this->user['rec_id'];
            $info['rec_id3'] = $this->user['rec_id2'];
            $info['rec_code'] = Util::rec_code();
            $status = $info['status'];
            $info['status'] = $info['status'] == 3 ? 1 : 0;
            $id = $UserModel->insertGetId($info);
            if (!$id) {
                throw new \think\Exception(lang("添加会员信息失败！"), 0);
            }
            $user_tree = rtrim($tree, '/') . "/{$id}/";
            $new_user_tree = explode("/", trim($user_tree, '/'));
            $upd['id'] = $id;
            $upd['tree'] = $user_tree;
            $upd['rec_level'] = count($new_user_tree);
            $UserModel->update($upd);
            $proxy_tree = rtrim($proxy_tree, '/') . "/{$id}/";
            $new_proxy_tree = explode("/", trim($proxy_tree, '/'));
            //开通微商
            $add_agent['uid'] = $id;
            $add_agent['apply_ip'] = Util::get_ip();
            $add_agent['apply_time'] = time();
            $add_agent['status'] = $status;
            $add_agent['tree'] = $user_tree;
            $add_agent['rate'] = floatval($rate) / 100;
            $add_agent['rec_id'] = $this->user['id'];
            $add_agent['proxy_upuid'] = $this->user['id'];
            $add_agent['proxy_topuid'] = $useragent['proxy_topuid'];
            $add_agent['proxy_level'] = count($new_proxy_tree);
            $add_agent['proxy_tree'] = $proxy_tree;
            $add_agent['type'] = $type;
            $add_agent['admin_name'] = $this->user['username'];
            $UserAgentModel->insertGetId($add_agent);
            UserAdminLogModel::add_log($this->user_id,"代理添加[{$info['username']},{$id}]", $this->user['username']);
            // 提交事务
            Db::commit();
            $info['id'] = $id;
            $info['is_agent'] = 1;
            $info['top_code'] = $this->user['rec_code'];
            return Respons::run($id?1:0,'','','', $info, $UserModel,lang('代理添加'));
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return Respons::run(0,[$e->getTrace()],$e->getMessage());
        }
    }
}