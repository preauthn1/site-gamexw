<?php
namespace app\promote\controller;
use app\common\model\AdminNodeModel;
use app\common\model\AdminRoleModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\GameBetsModel;
use app\common\model\UserBankModel;
use app\common\model\UserModel;
use app\common\model\UserLevelModel;
use app\common\model\UserCkModel;
use app\common\model\UserLoginModel;
use app\common\model\UserOfflineModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserAgentModel;
use app\common\model\UserBalanceMxModel;
use app\common\model\UserAdminLogModel;
use app\common\model\ActionApplyModel;
use app\common\model\ActionModel;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\redis\CommomRedis;
use app\common\Utils;
use app\common\lib\Uploads;
use app\common\model\UserWithdrawalModel;
use app\manage\factory\UserFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\facade\Config;
use think\facade\Log;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
use think\facade\Session;
use app\common\service\MemberService;

class User extends Base
{
    //列表
    public function list(){
        $param=Request::param();
        $UserModel =new UserModel();
        $page=$param['page'];
        $limit=$param['limit'];
        $where=[];
        $where1 = '';
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }
        if (isset($param['username']) && $param['username']!='') {
            if(is_numeric($param['username'])){
                $where[]=["a.id|a.rec_code|a.phone", "=", $param['username']];
            }else{
                $where[]=["a.rec_code|a.username|a.mail|a.phone", "=", $param['username']];
            }
        }
        if (isset($param['lebals']) && $param['lebals']!='') {
//            $where[]=["a.lebal", "=", $param['lebals']];
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.id", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }
        if (isset($param['end_login_ip']) && $param['end_login_ip']!='') {
            $where[]=["a.end_login_ip|a.reg_ip","like","%".$param['end_login_ip']."%"];
        }
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["a.username|a.mail|a.phone","like","%".$param['name']."%"];
        }

        //首充或复充
        if( isset($param['first']) && $param['first'] !== '' ){
            //查询复充用户sql
            $userOfflineSql = UserOfflineModel::where('status', 'in', '3,4')->where('first', '<>', 1)->buildSql();
            if( $param['first'] == 1 ){
                //首充，没有复充的用户既为首充
                $where[]=["a.id", "not in", $userOfflineSql];
            }else{
                //复充
                $where[]=["a.id", "in", $userOfflineSql];
            }
        }

//        if (isset($param['rec_id']) && $param['rec_id']!='') {
//            //查询rec_id
//            $sqlwhere[] = ["id|rec_code|username", "=", $param['rec_id']];
//            $rec_tree = UserModel::where($sqlwhere)->field("id")->value('tree');
//            if(!empty($rec_tree)){
//                $where[]=["a.tree", "like", $rec_tree."%"];
//            }
//        }

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
                $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
            } else {
                $where[]=["a.rec_id", "=", $rec_info['id']];
            }
        }

        if (isset($param['ip']) && $param['ip'] > 0) {
            $sql = UserLoginModel::where(['login_ip'=>$param['ip']])->field('uid')->buildSql();
            $where1 = "a.id in(".$sql.")";
        }

        if (isset($param['logins']) && $param['logins'] != '') {
            $where[]=["a.login_time","<", time() - 86400 * $param['logins']];
            $where[]=["a.login_time",">", 0];
        }

        if (isset($param['login_time']) && $param['login_time']!='') {
            $date=explode(' - ',$param['login_time']);
            $where[]=["a.add_time",">=", strtotime($date[0])];
            $where[]=["a.add_time","<=", strtotime($date[1])];
        }
        if( isset($param['is_recharge']) && $param['is_recharge'] !== '' ){
            if( $param['is_recharge'] == 1 ){
                $where[] = ['a.sc_balance', '>', 0];
            }else{
                $where[] = ['a.sc_balance', '=', 0];
            }
        }

        $whereStr = '';
        $agent = $this->user_agent;
        $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
        $whereStr = 'a.id in ('.$sql .')';

        $UserLevelModel = new UserLevelModel();
        $user_level_data = $UserLevelModel->column('level_name', 'id');
        $rsps['data'] = $UserModel
            ->alias("a")
            ->where($where)
            ->where($where1)
            ->where($whereStr)
            ->order($order)
            ->field("a.id,a.username,a.mail,a.login_ip_count,a.lebal,a.rec_code,a.add_time,a.login_time,a.balance,a.phone,a.qh,"
                ."a.rec_id,a.live_fund_money,a.integral,a.level_id,a.status,a.end_login_ip,a.reg_ip"
            )
            ->paginate($limit,false,['query' => $param]);

        try {
            $withdraw = UserWithdrawalModel::whereIn('status', '3,4')->field("SUM(arrival_prices) AS amount,uid")->group('uid')->select()->column('amount', 'uid');
            $recharge = UserOfflineModel::whereIn('status', '3,4')->field("SUM(arrival_price) AS amount,uid")->group('uid')->select()->column('amount', 'uid');
        } catch (Exception $e) {
            $withdraw = [];
            $recharge = [];
        }
        foreach($rsps['data'] as &$value){
            $wh = [];
            $value['recharge'] = $recharge[$value['id']] ?? 0;
            $value['withdraw'] = $withdraw[$value['id']] ?? 0;
            $value['level_name'] = $user_level_data[$value['level_id']];
            $value['mail'] = $value['mail'] == null ||  $value['mail'] == 'undefined' ? '' : $value['mail'];
            $value['phone'] = $value['phone'] == null ||  $value['phone'] == 'undefined' ? '' : $value['phone'];
            $value['add_time'] = empty($value['add_time']) ? '':date("Y-m-d H:i:s",$value['add_time']);
            $value['login_time'] = empty($value['login_time']) ? '':date("Y-m-d H:i:s",$value['login_time']);
            $value['login_rep'] = MemberService::isRepeatByIP($value['end_login_ip']);
//            if($value['reg_ip'] == $value['end_login_ip']){
//                $value['reg_rep'] = $value['login_rep'];
//            }else{
//                $value['reg_rep'] = MemberService::isRepeatByIP($value['reg_ip']);
//            }
            $value['tags'] = MemberService::getTagByUid($value['id']);
            $value['rec_name'] = MemberService::getUsernameByUid($value['rec_id']);
            $value['rec_top_user'] = MemberService::getTopAgentByTree($value['tree']);
            $value['fund_money'] = $value['live_fund_money'];
            $value['reg_ip'] = $value['reg_ip'] . "<br>" . ip2location($value['reg_ip']);
            $value['end_login_ip'] = !empty($value['end_login_ip'])?$value['end_login_ip'] . "<br>" . ip2location($value['end_login_ip']):'';
        }
        if (isset($param['export']) && $param['export'] == 1) {
            $list = $rsps['data'];
            foreach ($list as $value) {
                $value['level_name'] = $user_level_data[$value['level_id']];
                $value['lebal']      = $value['lebal'] == 1 ? lang('普通用户') : lang('测试用户');
                $value['status']     = $value['status'] == 1 ? lang('启用') : lang('禁用');
            }
            $header = [
                'id' => lang('UID'),
                'phone' => lang('手机号'),
                'qh' => lang('区号'),
                'mail' => lang('邮箱'),
                'lebal' => lang('用户标签'),
                'rec_code' => lang('推广码'),
                'rec_name' => lang('上级代理UID'),
                'rec_top_user_id' => lang('顶级代理UID'),
                'level_name' => lang('会员等级'),
                'balance' => lang('余额'),
                'recharge' => lang('总充值'),
                'withdraw' => lang('总提现'),
                'add_time' => lang('注册时间'),
                'reg_ip' => lang('注册ip'),
                'login_time' => lang('登录时间'),
                'end_login_ip' => lang('登录ip'),
                'status' => lang('状态'),
            ];
            $file_name = genCsv($header, $list,lang('会员记录').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('会员记录导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $tags = [
            ['label' => '一般用户', 'value' => -1],
            ['label' => '测试用户', 'value' => -2],
            ['label' => '三方登录用户', 'value' => -3],
        ];
        $rsps['code']=1;
        $rsps['tags'] = array_merge($tags, Utils::getOptions('tag'));
        $rsps['user_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        return Respons::run($rsps);
    }

    public function detail()
    {
        $rsps = (new UserFactory($this->user_agent))->detail();
        return $rsps;
    }
    //基础资料
    public function base(){
        $param=Request::param();
        if (isset($param['find'])) {
            $UserModel = new UserModel();
            $res=$UserModel->where("id",$param['id'])->find();
            $res['mail'] = $res['mail'] == null || $res['mail'] == 'undefined' ? '' : $res['mail'];
            $res['phone'] = $res['phone'] == null || $res['phone'] == 'undefined' ? '' : $res['phone'];
            $res['add_time']=date("Y-m-d H:i:s",$res['add_time']);
            $res['login_time']=date("Y-m-d H:i:s",$res['login_time']);
            $res['new_password']="";//新密码
            return Respons::run(1,$res);
        }

        if (isset($param['get_data'])) {
            $uid = $param['id'] ??0;
            $total_activityaward = 0;
            $apply = ActionApplyModel::where(["uid"=>$uid,'ps_status'=>1])->field('id,action_id,user_ck_id,gd_amount,tyj_amount')->select();
            foreach ($apply as $v){
                $base_type = ActionModel::where(['id'=>$v['action_id']])->value('base_type');
                if ($base_type == 1 || $base_type == 2) {
                    $total_activityaward += UserCkModel::where(["id"=>$v['user_ck_id'],'ck_status'=>1])->value('ck_hl_amount') ??0;
                } elseif ($base_type == 3) {
                    $total_activityaward += $v['gd_amount'];
                } elseif ($base_type == 4) {
                    $total_activityaward += $v['tyj_amount'];
                }
            }
            $user = UserModel::where(['id'=>$uid])->find();
            $res['data'] = [
                //充值总额
                'total_recharge'=>sprintf("%.2f",UserOfflineModel::where([['uid','=',$uid],['status','in','3,4']])->sum('arrival_price')),
                //提现总额
                'total_withdraw'=>sprintf("%.2f",UserWithdrawalModel::where([['uid','=',$uid],['status','in','3,4']])->sum('arrival_price')),
                //盈利总额
                'total_profit'=>sprintf("%.2f",0),
                //注单总额
                'total_betslip'=>sprintf("%.2f",0),
                //代理返佣总额
                'total_agentrebate'=>sprintf("%.2f",AgentRakeBackModel::where([['uid','=',$uid],['status','in','3,4']])->sum('money')),
                //人工增加总额
                'total_increase'=>sprintf("%.2f",ArtificialBalanceModel::alias("a")->where([['a.uid','=',$uid],['a.a_type','=','1']])->sum('a.price')),
                //人工赠送彩金总额
                'total_jackpot'=>sprintf("%.2f",ArtificialBalanceModel::alias("a")->where([['a.uid','=',$uid],['a.a_type','=','3']])->sum('a.price')),
                //人工赠减少总额
                'total_reduce'=>sprintf("%.2f",ArtificialBalanceModel::alias("a")->where([['a.uid','=',$uid],['a.a_type','=','2']])->sum('a.price')),
                //活动奖励总额
                'total_activityaward'=>sprintf("%.2f",$total_activityaward),
            ];
            return Respons::run(1,$res);
        }

        View::assign("id",$param['id']);
        return View::fetch('user/base');
    }

    //添加
    public function add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info=$param['info'];
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

            $agent = $this->user_agent;
            $this_user_id = $this->user_id;
            if($agent['proxy_level'] >= 3){
                return Respons::run(0,[], "无法开通下级账号");
            }
            $tree = $agent['tree'];
            $proxy_tree = $agent['proxy_tree'];
//            $lebal = $agent['lebal'];
            $UserModel = new UserModel();
            $UserAgentModel = new UserAgentModel();
            if (!empty($info['top_code'])) {
                $top       = $UserModel->where("rec_code", $info['top_code'])->find();
                if (empty($top)) {
                    return Respons::run(0,[], "上级推广码错误");
                }
                $top_tree_ids = array_filter(explode( '/', $top['tree']));
                if(!in_array($this_user_id, $top_tree_ids)){
                    return Respons::run(0,[], "上级推广码错误");
                }
                $tree = $top['tree'];
                $useragent = $UserAgentModel->where(['uid' => $top['id']])->find();
                if (!empty($useragent)) {
                    $proxy_tree = $useragent['proxy_tree'];
                    if ($useragent['type'] == 0 && $info['agent_type'] == 1) {
                        return Respons::run(0,[], "外部代理号不能开通内部代理下属账号");
                    }
                    if (isset($info['rate']) && ($useragent['rate'] * 100 < $info['rate'])) {
                        return Respons::run(0,[], "下级返佣比例不能超过上级");
                    } else {
                        $info['rate'] = 0;
                    }
                } else {
//                    if ($is_agent == 1) {
//                        throw new \think\Exception(lang("不是代理无法开通下级代理号！"), 0);
//                    }
                }
            }else{
                if (isset($info['rate']) && ($agent['rate'] * 100 < $info['rate'])) {
                    return Respons::run(0,[], "下级返佣比例不能超过上级");
                }
            }
            $user = $UserModel->where("username", $info['username'])->find();
            if (!empty($user)) {
                return Respons::run(0,[], "账号已存在");
            }
            if (!empty($info['mail'])) {
                $mail=$UserModel->where("mail",$info['mail'])->find();
                if (!empty($mail)) {
                    return Respons::run(0,[], "邮箱已存在");
                }
            } else {
                unset($info['mail']);
            }
            if (!empty($info['phone'])) {
                $phone=$UserModel->where("phone",$info['phone'])->find();
                if (!empty($phone)) {
                    return Respons::run(0,[], "手机号已存在");
                }
            } else {
                unset($info['phone']);
            }
            $is_agent = $info['is_agent'];
            $agent_type = $info['agent_type'];
            $agent_rate = isset($info['rate']) ? floatval($info['rate']) / 100 : 0;
            Db::startTrans();
            try {
                $UserAgentModel=new UserAgentModel();

//                $info['lebal']=$lebal;
                $info['add_time']=time();
                $info['reg_ip']=Util::get_ip();
                $info['password']=Util::userMd5Password($info['password']);
                unset($info['top_code']);
                unset($info['rate']);
                unset($info['is_agent']);
                unset($info['agent_type']);
                $id=$UserModel->insertGetId($info);
                if(empty($tree)){
                    $tree = "/".$id."/";
                }else{
                    $tree = $tree.$id."/";
                }
                $tree1 = explode("/", $tree);
                $new_tree = array_filter($tree1);
                $rec_id = $new_tree[count($new_tree) - 1] ?? 0;
                $rec_id2 = $new_tree[count($new_tree) - 2] ?? 0;
                $rec_id3 = $new_tree[count($new_tree) - 3] ?? 0;
                $upd['id']=$id;
                $upd['tree']=$tree;
                $upd['rec_id']=$rec_id;
                $upd['rec_id2']=$rec_id2;
                $upd['rec_id3']=$rec_id3;
                $upd['rec_level']=count($new_tree);
                $upd['rec_code']=Util::rec_code($id);
                $UserModel->update($upd);


                $ApiRedis=new ApiRedis();
                $sys = $ApiRedis->systemInfo();
                if($is_agent == 1 || ($sys['agent_apply_verify'] && $sys['agent_apply_verify'] == 2)){
                    //注册即为代理
                    if ($agent_type == 1) {
                        if (empty($proxy_tree)) {
                            $proxy_tree = "/" . $id . "/";
                        } else {
                            $proxy_tree = $proxy_tree . $id . "/";
                        }

                        $proxy_tree1    = explode("/", $proxy_tree);
                        $new_proxy_tree = array_filter($proxy_tree1);
                        $proxy_upuid    = $new_proxy_tree[count($new_proxy_tree) - 1] ?? 0;
                        $proxy_rec_id2  = $new_proxy_tree[count($new_proxy_tree) - 2] ?? 0;
                        $proxy_rec_id3  = $new_proxy_tree[count($new_proxy_tree) - 3] ?? 0;
                        if ($proxy_rec_id3 > 0) {
                            $proxy_topuid = $proxy_rec_id3;
                        } else if ($proxy_rec_id2 > 0) {
                            $proxy_topuid = $proxy_rec_id2;
                        } else {
                            $proxy_topuid = ($proxy_upuid == 0) ? $id : $proxy_upuid;
                        }
                    } else {
                        $proxy_tree     = '';
                        $proxy_topuid   = $proxy_upuid = 0;
                        $new_proxy_tree = [];
                    }
                    $add_agent['uid']=$id;
                    $add_agent['apply_ip']=Util::get_ip();
                    $add_agent['apply_time']=time();
                    $add_agent['status']=3;
                    $add_agent['tree']=$tree;
                    $add_agent['rec_id']=$rec_id;
                    $add_agent['proxy_upuid']=$proxy_upuid;
                    $add_agent['proxy_topuid']=$proxy_topuid;
                    $add_agent['proxy_level']=count($new_proxy_tree);
                    $add_agent['proxy_tree']=$proxy_tree;
                    $add_agent['type']         = $agent_type;
                    $add_agent['admin_name']= $agent['username'];
                    $add_agent['rate']   = $agent_rate;
                    $UserAgentModel->insertGetId($add_agent);

                    UserAdminLogModel::add_log($id,"代理会员添加", $agent['username']);
                }

                // 提交事务
                Db::commit();
                return Respons::run($id?1:0);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return Respons::run(0,[],$e->getMessage());
            }
        }
        return View::fetch('user/add');
    }

    public function edit(){
        $param=Request::param();
        $agent = $this->user_agent;
        if (isset($param['edit'])) {
            $info=$param['info'];
            if (!empty($info['new_password'])) {
                if(strlen($info['new_password']) < 6){
                    ajax_return("密码长度不能低于6位字符", 0);
                }
                if(!checkString($info['new_password'])){
                    ajax_return("密码只能数字和字母组合", 0);
                }
                $upd['password']=Util::userMd5Password($info['new_password']);
            }
            $upd['id']=$agent['id'];
            $ApiRedis=new ApiRedis();
            $ApiRedis->set("userPwdErrorCount".$agent['id'], 0, 86400);
            $UserModel =new UserModel();
            $data = $UserModel->where('id',$agent['id'])->withoutField('rec_id')->find();
            $rs = $UserModel->update($upd);
            //设置会员登出
            if($rs){
                Session::delete('agentInfo');
            }
            UserAdminLogModel::add_log($upd['id'],lang("会员修改"),Session::get("adminInfo.name"));
            return Respons::run(1,'','',$data,$info,$UserModel,lang('会员修改'));
        }
    }

    public function withdrawal(){
        $param=Request::param();
        $info=$param['info'];
        $agent = $this->user_agent;
        $ApiRedis=new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if ($sys['is_withdraw'] == 0) {
            ajax_return("提现系统维护中", 0);
        }
        if($sys['test_withdraw'] == 0){
            $user=UserModel::uinfo($agent['id'],",a.lebal");//
            if ($user['lebal']==2) {
                ajax_return("测试用户不能提现", 0);
            }
        }
        if (MemberService::tagBanWithdraw($agent['id'])) {
            ajax_return("您无法申请提现,请联系客服", 0);
        }

        $ApiRedis=new ApiRedis();


        $sys = $ApiRedis->systemInfo();
        $bk = UserBankModel::where("uid",$agent['id'])->with('currencyApi')->where("id",$info['bank_id'])->find();
        if (!isset($bk['id'])) {
            ajax_return("用户银行卡不存在！", 0);
        }
        if(!isset($sys['withdraw_amcount'])) $sys['withdraw_amcount']=0;
        if($info['w_price'] % 10 != 0 && $sys['withdraw_amcount'] == 0){
            ajax_return("提现金额必须为10的倍数，请重新输入", 0);
        }

        if($info['w_price'] % 100 != 0 && $sys['withdraw_amcount'] == 1){
            ajax_return("提现金额必须为100的倍数，请重新输入", 0);
        }

        $add_time = UserWithdrawalModel::where("uid",$agent['id'])->order("id desc")->value('add_time');//获取上一次提现时间
        if($add_time + 10 > time()){
            ajax_return("请勿频繁访问", 0);
        }

        $bk['pay_type'] = $bk['bank_type'];
        $currency = Util::getPayCurrency($bk, 1);

        $user = UserModel::uinfo($agent['id'],",a.water_balance,b.min_amount,b.max_amount,b.day_limit,a.savings,a.mail,a.phone,a.is_check_phone,a.is_check_phone");//查看当前用户流水和最低提现金额

        if (!($user['balance']>=$info['w_price'])) {
            ajax_return("余额不足", 0);
        }

        $UserOffline = 0;
        if($user['level_id'] == 1){
            $UserOffline = UserOfflineModel::where([['uid', "=", $agent['id']],['status', "in", "3,4"]])->value("id") ? 1:0;
        }

        $wit_num = 1;
        if($bk['bank_type'] == 1 && $sys['free_poundage'] > 0){
            $withdrawal_num = UserWithdrawalModel::where(['uid'=>$agent['id'],'channel'=>1])->count();//查看今日提现次数
            if($withdrawal_num < $sys['free_poundage']){ //当提现次数 小于后台设置的 提现免手续次数时 手续费设置为0
                $wit_num = 0;
            }
        }

        Db::startTrans();
        try {
            $userlock = UserModel::where("id",$agent['id'])->lock(true)->field("balance")->find();
            if ($userlock['balance'] < $info['w_price']) {
                throw new \Exception("余额不足", 0);
            }

            $fee = $sys['agent_service_charge'] ?? 0;
            $is_usdt_fee = env('is_usdt_fee', 0);
            if($bk['bank_type'] == 1 && $is_usdt_fee == 1){
                $fee = $sys['agent_usdt_charge'] ?? 0;
            }
            $add['add_time']=time();
            $add['uid']=$agent['id'];
            $add['channel'] = $bk['bank_type'];
            $add['bank_id']=$info['bank_id'];
            $add['w_price']=$info['w_price'];
            $add['arrival_price'] = round(($info['w_price'] - ($wit_num ? round($info['w_price']*($fee/100), 2) : 0/100)) * $currency['exchange_rate'], 2);
            $add['arrival_prices'] = round($info['w_price'] - ($wit_num ? round($info['w_price']*($fee/100), 2) : 0/100), 2);
            $add['current_currency'] = $currency['current_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['w_service'] = $wit_num ? round($info['w_price']*($fee/100), 2) : 0;
            $add['lebal']=$user['lebal'];
            $add['type']=1;
            $id = UserWithdrawalModel::insertGetId($add);
            $upd['id']=$id;
            $upd['order_sn']=date("YmdHi").$id;
            UserWithdrawalModel::update($upd);
            UserModel::cz_balance($agent['id'],$add['w_price'],"-",true, "余额提现");
            \app\common\model\UserAdminLogModel::add_log($agent['id'], '会员提现|IP:'.Util::get_ip()."|订单号：".$upd['order_sn']."|提现金额：".$add['w_price']."|收款地址：".$bk['bank_number'], "会员提现");
            // 提交事务
            Db::commit();
            $rsps['code']=1;
            $rsps['msg']='申请成功';
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code']=0;
            $rsps['msg']=$e->getMessage();
        }
        return $rsps;
    }

    public function my() {
        $param=Request::param();
        $user_info = $agent = $this->user_agent;
        $user_info['id'] = $this->user['id'];
        $user_info['rec_code'] = $this->user['rec_code'];
        $user_info['qh'] = $this->user['qh'];
        $user_info['phone'] = $this->user['phone'];
        $user_info['mail'] = $this->user['mail'];
        $user_info['username'] = $this->user['username'];
        $user_info['balance'] = $this->user['balance'];
        $user_info['rec_id'] = $this->user['rec_id'];
        $user_info['rec_id2'] = $this->user['rec_id2'];
        $user_info['rec_id3'] = $this->user['rec_id3'];
        $user_info['rec_person'] = $this->user['rec_person'];

        $user_info['rec_url'] = MemberService::createShareUrl($user_info['rec_code'], true);
        $bank_type = $param['bank_type'] ?? "all";
        $UserBankModel= new UserBankModel();
        $type = [];
        if($bank_type === "all"){
            $is_withdrawal_bank = env('is_withdrawal_bank', 0); //银行渠道
            $is_withdrawal_usdt = env('is_withdrawal_usdt', 0); // usdt渠道
            $is_withdrawal_wallet = env('is_withdrawal_wallet', 0); // 电子钱包
            $is_withdrawal_pix = env('is_withdrawal_pix', 0); // PIX

            if($is_withdrawal_bank == 1){
                $type[] = 0;
            }
            if($is_withdrawal_usdt == 1){
                $type[] = 1;
            }
            if($is_withdrawal_wallet == 1){
                $type[] = 2;
            }
            if($is_withdrawal_pix == 1){
                $type[] = 3;
            }
        }else{
            $type[] = $bank_type;
        }
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency_code = Config::get('custom.currency_code');

        $french_currency = [];
        foreach($currency['ordinary_data'] as $val){
            $val['currency_code'] = $currency_code[$val['country_code']];
            $french_currency[$val['id']] = $val->toArray();
        }

        $bank = $UserBankModel->alias("a")
            ->where("a.uid",$agent['id'])
            ->where("a.bank_type", "in", $type)
            ->field('a.id,a.bank_type,a.bank_id,a.bank_number,a.bank_open,a.bank_hm,a.province,a.city,a.remarks,a.bank_name,a.currency_id')
            ->order('a.id DESC')
            ->select();

        foreach ($bank as $key => $value) {
            if ($value['bank_type'] == 1) {
                $bank[$key]['withdrawal_rate'] = $currency['usdt_data'][0]['withdrawal_rate'] ?? '1.000000';
                $bank[$key]['recharge_rate'] = $currency['usdt_data'][0]['recharge_rate'] ?? '1.000000';
                $bank[$key]['currency_code'] = "USDT";
            } else {
                if(isset($french_currency[$value['currency_id']])){
                    $bank[$key]['withdrawal_rate'] = $french_currency[$value['currency_id']]['withdrawal_rate'] ?? '1.000000';
                    $bank[$key]['recharge_rate'] = $french_currency[$value['currency_id']]['recharge_rate'] ?? '1.000000';
                    $bank[$key]['currency_code'] = $french_currency[$value['currency_id']]['currency_code'] ?? '';
                } else {
                    $bank[$key]['withdrawal_rate'] = $currency['ordinary_data'][0]['withdrawal_rate'] ?? '1.000000';
                    $bank[$key]['recharge_rate'] = $currency['ordinary_data'][0]['recharge_rate'] ?? '1.000000';
                    $bank[$key]['currency_code'] = $currency_code[$currency['ordinary_data'][0]['country_code']];
                }
            }
            $bank[$key]['bankcrad']=Util::bank_number_sub($value['bank_number']);
        }
        $user_info['bank_list'] = $bank;
        // 团队用户
        $total_users = UserModel::where('id', '<>', $this->user_id)->where('tree','like', $this->user['tree'].'%')->count();
        // 直属用户
        $rec_users = UserModel::where('id', '<>', $this->user_id)->where('rec_id', $this->user_id)->count();
        // 代理用户
        $total_agent_users = UserAgentModel::where('uid', '<>', $this->user_id)->where('tree','like', $this->user['tree'].'%')->where('type', 1)->count();
        // 直属代理用户
        $rec_agent_users = UserAgentModel::where('uid', '<>', $this->user_id)->where('rec_id', $this->user_id)->where('type', 1)->count();
        $user_info['stat'] = [
            'total_users' => $total_users,
            'rec_users' => $rec_users,
            'rec2_users' => $total_users - $rec_users,
            'total_agent_users' => $total_agent_users,
            'rec_agent_users' => $rec_agent_users,
            'rec2_agent_users' => $total_agent_users - $rec_agent_users,
        ];
        //是否必须要填写信息才能使用后台
        $user_info['is_need_finish_info'] = env('AGENT_IS_NEED_FINISH_INFO', 0);
        $user_info['country'] = Config::get('custom.country');
        return $this->success([
            'data' => $user_info
        ]);
    }

    //完善信息
    public function complete_info(){
        $params = $this->request->param('info/a');
        UserAgentModel::where('uid', $this->user_id)->update($params);
        return $this->success();
    }

    //流水统计
    public function running_water(\think\Request $request)
    {
        return (new MemberService())->getUserWaterDetail();
    }

    //用户流水
    public function water(){
        return (new MemberService())->getUserWaterList();
    }
    
    public function getNav() {
        $nodes = [
            [
                "path" => "/welcome",
                "meta" => [
                    "keepAlive" => true,
                    "title" => "仪表盘",
                    "icon" => "home-filled",
                    "rank" => 1
                ],
                "children" => [
                    [
                        "path" => "/welcome",
                        "name" => "Welcome",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "仪表盘",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                            'auths' => ['dataView']
                        ]
                    ]
                ]
            ],
            [
                "path" => "/user",
                "meta" => [
                    "keepAlive" => true,
                    "title" => "团队",
                    "icon" => "IF-icon-7",
                    "rank" => 1
                ],
                "children" => [
                    [
                        "path" => "/user/pages/list",
                        "name" => "userList",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "会员列表",
                            "icon" => "",
                            "roles" => ["admin"]
                        ]
                    ],
                    [
                        "path" => "/user/pages/agent",
                        "name" => "agentList",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "代理列表",
                            "icon" => "",
                            "roles" => ["admin"]
                        ]
                    ]
                ]
            ],
            [
                "path" => "/offline",
                "meta" => [
                    "keepAlive" => true,
                    "title" => "充值提现",
                    "icon" => "IF-icon-jine",
                    "rank" => 3
                ],
                "children" => [
                    [
                        "path" => "/offline/useroffline",
                        "name" => "useroffline",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "用户充值",
                            "icon" => "",
                            "roles" => ["admin"]
                        ]
                    ],
                    [
                        "path" => "/offline/userwithdrawal",
                        "name" => "userwithdrawal",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "用户提现",
                            "icon" => "",
                            "roles" => ["admin"]
                        ]
                    ]
                ]
            ],
            [
                "path" => "/report",
                "meta" => [
                    "keepAlive" => true,
                    "title" => "数据",
                    "icon" => "IF-icon-ziyuan121",
                    "rank" => 5
                ],
                "children" => [
                    [
                        "path" => "/report/remainStatistics",
                        "name" => "remainStatistics",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "留存率报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                        ]
                    ],
                    [
                        "path" => "/report/reportAgent",
                        "name" => "reportAgent",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "运营报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                        ]
                    ],
                    [
                        "path" => "/report/gameRecord",
                        "name" => "gameRecord",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "投注记录",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                        ]
                    ],
//                    [
//                        "path" => "/report/gameReport",
//                        "name" => "gameReport",
//                        "meta" => [
//                            "showLink" => true,
//                            "keepAlive" => true,
//                            "title" => "游戏报表",
//                            "icon" => "",
//                            "roles" => [
//                                "admin"
//                            ]
//                        ]
//                    ],
                    [
                        "path" => "/report/gameUserDayReport",
                        "name" => "gameUserDayReport",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "用户日报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ]
                        ]
                    ],
                    [
                        "path" => "/report/agent",
                        "name" => "report",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "代理报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                            "auths" => [

                            ]
                        ]
                    ],
                    [
                        "path" => "/report/viewreport",
                        "name" => "viewreport",
                        "meta" => [
                            "showLink" => false,
                            "keepAlive" => true,
                            "title" => "查看报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                            "auths" => [

                            ]
                        ]
                    ],
                    [
                        "path" => "/weishang/reportdetail",
                        "name" => "reportdetail",
                        "meta" => [
                            "showLink" => false,
                            "keepAlive" => true,
                            "title" => "下层充提报表",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                            "auths" => [

                            ]
                        ]
                    ],
                    [
                        "path" => "/report/gameReportList",
                        "name" => "gameReportList",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "数据报表",
                            "icon" => "",
                            "roles" => ["admin"]
                        ]
                    ],
//                    [
//                        "path" => "/report/gameUserStat",
//                        "name" => "gameUserStat",
//                        "meta" => [
//                            "showLink" => true,
//                            "keepAlive" => true,
//                            "title" => "用户数据总览",
//                            "icon" => null,
//                            "roles" => [
//                                "admin"
//                            ]
//                        ]
//                    ],
//                    [
//                        "path" => "/report/gameStat",
//                        "name" => "gameStat",
//                        "meta" => [
//                            "showLink" => true,
//                            "keepAlive" => true,
//                            "title" => "游戏统计",
//                            "icon" => null,
//                            "roles" => [
//                                "admin"
//                            ]
//                        ]
//                    ]
                ]
            ],
        ];
        //站点设置权限
        if( isset($this->user_agent['website_auth']) && $this->user_agent['website_auth'] == 1 ){
            $nodes[] = [
                "path" => "/website",
                "meta" => [
                    "keepAlive" => true,
                    "title" => "站点配置",
                    "icon" => "IF-icon-yuyan1",
                    "rank" => 6
                ],
                "children" => [
                    [
                        "path" => "/website/config",
                        "name" => "websiteConfig",
                        "meta" => [
                            "showLink" => true,
                            "keepAlive" => true,
                            "title" => "站点配置",
                            "icon" => "",
                            "roles" => [
                                "admin"
                            ],
                            'auths' => ['dataView']
                        ]
                    ],
                    //暂时去掉，多服务器语言会有问题
//                    [
//                        "path" => "/website/lang",
//                        "name" => "websiteLang",
//                        "meta" => [
//                            "showLink" => true,
//                            "keepAlive" => true,
//                            "title" => "站点类目",
//                            "icon" => "",
//                            "roles" => [
//                                "admin"
//                            ],
//                            'auths' => ['dataView']
//                        ]
//                    ]
                ]
            ];
        }
        return $this->success([
            'data' => $nodes,
            'agent_user' => $this->user_agent,
        ]);
    }
}