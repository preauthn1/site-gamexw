<?php
namespace app\manage\controller;
use app\common\lib\Uploads;
use app\common\model\BankSkModel;
use app\common\model\LangModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserLevelModel;
use app\common\lib\Respons;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\PixelService;
use app\common\service\UserOfflineService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\redis\CommomRedis;
use app\common\lib\Util;
use app\common\Utils;
use think\facade\Config;
use think\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use app\common\model\PaySetModel;
use app\common\payment\Entry;
use app\common\model\LotteryConfigModel;
use app\common\model\UserMsgModel;
use think\facade\Queue;
use app\manage\factory\UserofflineFactory;

class UserOffline extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request)
    {
        $pay_channel = $this->pay_channel(2) ?? [];
        $rsps = (new UserofflineFactory($request->token->uid))->list(null, $pay_channel);
        return $rsps;
    }

    public function get_user_data(Request $request){
        $channel = Util::getOpenChannel();
        $param = $request->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $where = [];
        $whereStr = '1=1';
        $time_type = 'a.add_time';
        $UserOfflineModel = new UserOfflineModel();
        if (isset($param['set_content']) && $param['set_content']!='') {
            $where[] = ["a.set_content","like","%".$param['set_content']."%"];
        }
        if (isset($param['username']) && $param['username']!='') {
            $uid = UserModel::where([['username','=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uid];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['three_order_sn']) && $param['three_order_sn']!='') {
            $where[] = ["a.othr_number", "=", $param['three_order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[] = ['apply_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[] = ['apply_price',"<=",$param['max_price']];
        }

        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username",$param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in ('.$sql .')';
        }

        if(!empty($param['level_id'])){
            $sql = UserModel::where("level_id",$param['level_id'])->field('id')->buildSql();
            $whereStr .= ' and a.uid in ('.$sql .')';
        }

        if (isset($param['status']) && $param['status']!='') {
            $where[] = ["a.status","=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad']!='') {
            $where[]=["a.bankcrad","=", $param['bankcrad']];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[] = ["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.pay_id','=',$param['pay_type']];
        }
        if(isset($param['recharge_type']) && $param['recharge_type'] > 0){
            if($param['recharge_type'] == 1){
                $where[] = ['a.first','=',1];
            }else{
                $where[] = ['a.first','<>',1];
            }
        }
        if(isset($param['time_type']) && $param['time_type'] != ''){
            if($param['time_type'] == 1){
                $time_type = 'a.add_time';
            }else{
                $time_type = 'a.update_time';
            }
        }else{
            $time_type = 'a.add_time';
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebal'])) {
            if ($param['lebal'] != '') $where[]=["a.lebal","=", $param['lebal']];
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }

        $rsps['data'] = UserOfflineModel::alias("a")
            ->where($where)->where($whereStr)
            ->order($order)
            ->field("a.*,".'(select username from dq_user where id=a.uid limit 1) as username')
            ->paginate($limit,false,['query' => $param]);

        foreach($rsps['data'] as $key => &$val){
            $val['channel_name'] = isset($channel[$val['channel']])?lang($channel[$val['channel']]):"";
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
            if($val['update_time']){
                $val['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
            }else{
                $val['update_time'] = '';
            }
            if($val['pay_id'] > 0){
                if ($val['channel'] === 20) {
                    $val['pay_name'] = Db::name('agent')->where(['id'=>$val['pay_id']])->value('username');
                } else {
                    $val['pay_name'] = PaySetModel::where(['id'=>$val['pay_id']])->value('pay_name');
                }
            }
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function user_list(Request $request){
        $param = $request->param();
        $channel = Util::getOpenChannel();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $isSousuo = 0;
        foreach($channel as $key => $val){
            if($key == 0){
                $isSousuo = 1;
            }
        }
        View::assign("channel",$channel);
        View::assign("isSousuo",$isSousuo);
        $p_wh['isdel'] = 0;
        $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        $vip = UserLevelModel::where("1=1")->column("id,level_name");
        View::assign('vip',$vip);
        View::assign('pay_type',$pay_type);
        View::assign('uid',$param['uid']);
        return View::fetch('user_offline/list');

    }

    //列表
    public function verify(Request $request){
        $channel = Util::getOpenChannel();
        $param = $request->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $where = [];
        $where = [['status', '=', 1]];
        $whereStr = '';
        $time_type = 'a.add_time';
        $UserOfflineModel = new UserOfflineModel();
        if (isset($param['set_content']) && $param['set_content']!='') {
            $where[] = ["a.set_content","like","%".$param['set_content']."%"];
        }
        if (isset($param['username']) && $param['username']!='') {
            $uid = UserModel::where([['username','=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uid];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[] = ['apply_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[] = ['apply_price',"<=",$param['max_price']];
        }
        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username",$param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= 'a.uid in ('.$sql .')';
        }

        if (isset($param['bankcrad']) && $param['bankcrad']!='') {
            $where[] = ["a.bankcrad","like", "%".$param['bankcrad']."%"];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[] = ["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.pay_id','=',$param['pay_type']];
        }

        if(isset($param['time_type']) && $param['time_type'] != ''){
            if($param['time_type'] == 1){
                $time_type = 'a.add_time';
            }else{
                $time_type = 'a.update_time';
            }
        }else{
            $time_type = 'a.add_time';
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebal'])) {
            if ($param['lebal'] != '') $where[]=["a.lebal","=", $param['lebal']];
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }

        $rsps['data'] = UserOfflineModel::alias("a")
            ->where($where)->where($whereStr)
            ->order($order)
            ->field("a.*,".'(select username from dq_user where id=a.uid limit 1) as username')
            ->paginate($limit,false,['query' => $param]);

        foreach($rsps['data'] as $key => &$val){
            $val['channel_name'] = isset($channel[$val['channel']])?lang($channel[$val['channel']]):"";
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
            if($val['update_time']){
                $val['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
            }else{
                $val['update_time'] = '';
            }
            if($val['pay_id'] > 0){
                $val['pay_name'] = PaySetModel::where(['id'=>$val['pay_id']])->value('pay_name');
            }
        }
        $rsps['code']=1;
        return Respons::run($rsps);
        // $isSousuo = 0;
        // foreach($channel as $key => $val){
        //     if($key == 0){
        //         $isSousuo = 1;
        //     }
        // }
        // $p_wh['pay_status'] = 1;
        // $p_wh['isdel'] = 0;
        // $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        // View::assign('pay_type',$pay_type);
        // View::assign("isSousuo",$isSousuo);
        // View::assign("channel",$channel);
        // return View::fetch('user_offline/verify');
    }

    //编辑
    public function add(Request $request){
        $param = $request->param();
        $info=$param['info'];
        if (empty($info['username'])) {
            return Respons::run(0,[],lang('请选择用户'));
        }

        if ($info['price']<=0 || $info['price']=='') {
            return Respons::run(0,[],lang('请输入入单金额'));
        }

        if (!is_numeric($info['price'])) {
            return Respons::run(0,[],lang('入单金额只能为数字'));
        }

        if (!empty($info['update_time']) && strtotime($info['update_time']) > time()) {
            return Respons::run(0,[],lang('审核时间不能大于当前时间'));
        }
        $pay_type = '';
        $channel_type = 0;
        if(!empty($info['pay_id'])){
            if($info['pay_type'] == 'online'){
                $pay_type = PaySetModel::get_rand_pay('',['id'=>$info['pay_id']]);//随机渠道
                switch ($pay_type['pay_type']) {
                    case 0:
                        $channel_type = 1;
                        break;
                    case 1:
                        $channel_type = 2;
                        break;
                }
            }
            if($info['pay_type'] == 'offline'){
                $pay_type = BankSkModel::get_rand_pay('', ['id'=>$info['pay_id']]);//随机渠道
                switch ($pay_type['type']) {
                    case 0:
                        $channel_type = 0;
                        break;
                    case 1:
                        $channel_type = 3;
                        break;
                    case 2:
                        $channel_type = 4;
                        break;
                    case 3:
                        $channel_type = 6;
                        break;
                }
            }
            if(empty($pay_type)){
                return Respons::run(0,[],lang('支付渠道不存在'));
            }
        }

        $CommomRedis=new CommomRedis();
        if (!$CommomRedis->lock()) {
//                throw new \Exception(lang("请勿频繁访问"), 0);
            return Respons::run(0, [], '请勿频繁访问');
        }
//        UserOfflineModel::payset_rebate(6361, 86693936, 46, 500);
//        die;
        $sys = Utils::sysInfo();
        Db::startTrans();
        try {
            $uids = explode(",", $info['username']);
            foreach($uids as $val){
                if (empty($val)) {
                    continue;
                }
                $user = UserModel::where("id", $val)->find();
                if (!isset($user['id'])) {
                    throw new \Exception(lang("用户不存在"), 1);
                }
                $first = 2;
                if(UserOfflineModel::where(["uid"=>$user['id'], 'first' => 1])->where([["status", 'in',"3,4"]])->count() == 0){
                    $first = 1;
                }
                $currency = Util::getPayCurrency($pay_type);
                $add['uid'] = $user['id'];
                $add['bank_id'] = 0;
                $add['type'] =  0; //线下
                $add['channel'] =  $channel_type;
                $add['status'] =  4;
                $add['apply_price'] = $info['price']/$currency['exchange_rate'];
                $add['arrival_price'] = $info['price'];
                $add['pay_currency'] = $currency['pay_currency'];
                $add['arrival_currency'] = $currency['arrival_currency'];
                $add['exchange_rate'] = $currency['exchange_rate'];
                $add['remarks'] = $info['mx_msg']??"";
                $add['pay_ip'] = Util::get_ip();
                $add['lebal'] = $user['lebal'];
                $add['first'] = $first;
                $add['add_time']=empty($info['update_time']) ? time() : strtotime($info['update_time']);
                $add['update_time']= empty($info['update_time']) ? time() : strtotime($info['update_time']);
                $add['pay_type'] =  isset($pay_type['pay_sys_sign']) ? $pay_type['pay_sys_sign']:'';
                $add['pay_id'] = 0;
                $add['bank_sk_id'] = 0;
                if($info['pay_type'] == 'online'){
                    $add['pay_id'] = $info['pay_id'];
                    $add['type'] =  1; //线上
                }else if($info['pay_type'] == 'offline'){

                    $add['bank_sk_id'] = $info['pay_id'];
                }
                $id = UserOfflineModel::insertGetId($add);
                $upd['id'] = $id;
                $upd['order_sn'] = date("YmdHi").$id;
                $res = UserOfflineModel::update($upd);
                if($res){
//                        if(empty($info['pay_id'])){
                    UserModel::cz_balance($add['uid'], $add['arrival_price'], "+", true, "线下入款", 10);
                    if($add['type'] == 1){
                        UserOfflineModel::payset_rebate($id, $add['uid'], $add['pay_id'], $add['arrival_price']);
                    }else if($add['type'] == 0){
                        UserOfflineModel::bank_sk_rebate($id, $add['uid'], $add['bank_sk_id'], $add['arrival_price']);
                    }
                    UserOfflineModel::recharge_rebate($add['uid'], $add['arrival_price']);
                    UserAdminLogModel::add_log($add['uid'],"管理员手动入款, 订单号: {$upd['order_sn']}, 金额: {$add['arrival_price']}, 备注: " . $add['remarks'],Session::get("adminInfo.name"));
                    if($first == 1){
                        UserModel::where('id',$add['uid'])->update(['xc_date' => date("Ym")]);
                        // 发送首充投流像素事件
                        PixelService::firstRecharge($add['uid'], $upd['order_sn'], $add['arrival_currency'], $add['arrival_price']);
                    } else {
                        // 发送充值投流像素事件
                        PixelService::recharge($add['uid'], $upd['order_sn'], $add['arrival_currency'], $add['arrival_price']);
                    }
                    MessageService::sendRechargeMsg($add['uid'], $add['arrival_price']);
                    action_task($add['uid'], [1,2,7], $id);
                    Util::email_notify('off', $add['uid']);
//                    Queue::push("User@upgrade", ['id' => $add['uid'], 'money' => $add['arrival_price']], 'upgrade');
//                        }else{
//                            $pay = UserOfflineModel::where("id",$id)->find();
//                            $users = [];
//                            if (isset($user['id'])) {
//                                $users = UserModel::where("id", $user['id'])->field('*,id as user_id')->find()->toArray();
//                            }
//                            $other = [];
//                            foreach ($users as $key => $value) {
//                                $other[$key]=$value;
//                            }
//                            $other['order_sn'] = $pay['order_sn'];
//                            $other['apply_price'] = $pay['apply_price'];
//                            $other['phone'] = $user['phone']?$user['phone']:$user['user_id'];
//                            $other['pay_ip'] = $pay['pay_ip'];
//                            $other['add_time'] = date("Y-m-d H:i:s",$pay['add_time']);
//                            $other['call_domain'] = getAppUrl();
//                            $Entry = new Entry($pay_type['config'],$sys['domain']);//支付包
//                            $Entry->pay($pay_type['pay_sys_sign'],$other);
//                        }

                }
            }
            // 提交事务
            Db::commit();
            $rsps['code']=1;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $rsps['code']=0;
            $rsps['msg']=$e->getMessage();
        }
        //十秒自动解锁
        //$CommomRedis->unlock();
        $info['pay_type'] = isset($pay_type['pay_sys_sign']) ? $pay_type['pay_sys_sign']:'';
        return Respons::run($rsps,'','','',$info,new UserOfflineModel(),lang('手动添加入单'));
    }

    public function pay_channel($t = 1) {
        $channel_arr = Config::get('custom.channel');
        $online = PaySetModel::where(["isdel" => 0])
            ->filter(function($data) use($channel_arr) {
                $data['p_type'] = 'online';
                $data['t_name'] = $data['pay_type'] == 0 ? lang($channel_arr[1]) : lang($channel_arr[2]);
            })
            ->order("id desc")
            ->select()->toArray();
        //将线上渠道修改成线下渠道
        $offline = BankSkModel::where(['bank_status' => 1])
            ->filter(function($data) use($channel_arr) {
                $data['p_type'] = 'offline';
                switch($data['type']){
                    case 0 :
                        $data['t_name'] = lang($channel_arr[0]);
                        break;
                    case 1 :
                        $data['t_name'] = lang($channel_arr[3]);
                        break;
                    case 2 :
                        $data['t_name'] = lang($channel_arr[4]);
                        break;
                    case 3 :
                        $data['t_name'] = lang($channel_arr[6]);
                        break;
                }
            })
            ->order("id desc")
            ->select()->toArray();
        $channel = array_merge($offline ?? [], $online ?? []);
        if($t == 1){
            return Respons::run(1,$channel);
        }else{
            return $channel;
        }
    }

    //编辑
    public function success2(Request $request){
        $param = $request->param();
        $info=$param['info'];
        Db::startTrans();
        try {
            $data = UserOfflineModel::where("id",$info['id'])->find();
            if (!isset($data['id'])) {
                throw new \Exception(lang("数据不存在"), 1);
            }

            $first = 2;
            if(UserOfflineModel::where(["uid"=>$data['uid'], 'status'=>3, 'first' => 1])->count() == 0){
                $first = 1;
            }

            $upd['id']=$data['id'];
            $upd['status']=3;
            $upd['admin_name']=Session::get("adminInfo.name");
            $upd['update_time']=time();
            $upd['first']=$first;
            UserOfflineModel::update($upd);

            $mx_msg="线下入款";
            $mx = UserModel::cz_balance($data['uid'],$data['apply_price'],"+",true,$mx_msg,10);
            UserOfflineModel::recharge_rebate($data['uid'], $data['apply_price']);
            if($first == 1){
                UserModel::where('id',$data['uid'])->update(['xc_date' => date("Ym")]);
            }
            UserAdminLogModel::add_log($data['uid'],"线下入款审核通过, 订单号: {$data['order_sn']}, 金额: {$data['apply_price']}",Session::get("adminInfo.name"));

            /* 判断推广有效人数发放推广佣金 */
            $rec_id = UserModel::where(["id" => $data['uid']])->value('rec_id');
            $user = UserModel::alias("a")->join("dq_user_agent b","a.id=b.uid")->where("a.id", $rec_id)->where("b.status",3)->field("a.id")->find();//要是代理且是通过
            if(!empty($user)){
                $sys = Utils::sysInfo();
                UserModel::rec_person_add($rec_id, $sys['tg_condition']);
            }

            // 提交事务
            Db::commit();
            return Respons::run(1);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return Respons::run(0,[],$e->getMessage());
        }
    }

    public function edit_detail(Request $request){
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $sys = Utils::sysInfo();
        $res = UserOfflineModel::alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->leftjoin("dq_bank_sk e","a.bank_sk_id=e.id")
            ->where("a.id",$param['id'])
            ->order("a.id desc")
            ->field("a.*,b.username,b.mail,b.phone,b.lebal,e.bank_number as s_bank_number,e.bank_open as s_bank_open,e.bank_khm as s_bank_khm,e.bank_name as s_bank_name,e.bank_qrcode,e.financial as s_financial, a.arrival_price")
            ->find();
        $res['add_time'] = date("Y-m-d H:i:s",$res['add_time']);
        $res['update_time'] = date("Y-m-d H:i:s",$res['update_time']);
//        $res['arrival_price'] = $res['arrival_price'];
//        $res['arrival_price'] = $res['apply_price'] * $res['exchange_rate'];
        $res['cutrr_status'] = $res['status'];
        if (!is_null($res['mail'])) {
            $res['username'] = $res['mail'];
        } elseif (!is_null($res['phone'])) {
            $res['username'] = $res['phone'];
        }
        return Respons::run(1, Utils::hideUserInfo($res));

    }

    //编辑
    public function edit(Request $request){
        $param=$request->param();
        $info=$param['info'];
        if (!isset($info['id'])) {
            return Respons::run(0,'',lang("数据不存在"));
        }
        if (!isset($info['status']) || !in_array($info['status'], [2, 4])) {
            return Respons::run(0,[],lang('请选择审核状态'));
        }

        $CommomRedis=new CommomRedis();
        if (!$CommomRedis->lock('offline_'.$info['id'])) {
            return Respons::run(0,[],lang('请勿频繁访问'));
        }

        $data = UserOfflineModel::where("id",$info['id'])->find();
        if (empty($data)) {
            return Respons::run(0,'',lang("数据不存在"));
        }

        if ($data['status'] == 4 || $data['status'] == 3) {
            return Respons::run(0,[],lang('请勿重复操作'));
        }

        $first = 2;
        if(UserOfflineModel::where(["uid"=>$data['uid'], 'first' => 1])->where([["status", 'in',"3,4"]])->count() == 0){
            $first = 1;
        }

        if($info['status'] == 2){
            if (empty($info['remarks'])) {
                return Respons::run(0,[],lang('请填写审核备注'));
            }

            $upd['id'] = $data['id'];
            $upd['status'] = 2;
            $upd['remarks'] = $info['remarks'];
            $upd['admin_name'] = Session::get("adminInfo.name");
            $upd['update_time'] = time();
            $upd['user_remarks']=$info['user_remarks']??'';
            UserOfflineModel::update($upd);
        }

        if($info['status'] == 4){
            $upd['id']=$data['id'];
            $upd['status'] = 4;
            $upd['remarks'] = $info['remarks'];
            $arrival_price = $data['apply_price'] * $data['exchange_rate'];
            $upd['arrival_price'] = $arrival_price;
            $upd['admin_name'] = Session::get("adminInfo.name");
            $upd['update_time'] = time();
            $upd['first']=$first;
            $upd['user_remarks']=$info['user_remarks']??'';
            $res = UserOfflineModel::update($upd);
            if($res){
                if($arrival_price > 0){
                    $arrival_price = UserOfflineModel::where(['id' => $data['id']])->value('arrival_price');
                    $currency = Util::i18n_currency_code($data['arrival_currency']);
                    $msg = '充值成功, 订单号: {:order_sn}, 金额: {:price}, 币种: {:currency}, 备注: {:user_remarks}';#为了和成功回调那边统一，这里把基础的先翻译
                    UserMsgModel::add_msg($data['uid'], "充值提示", $msg, 15, $data['id'],['order_sn'=>$data['order_sn'],'price'=>$arrival_price,'currency'=>$currency,'user_remarks'=>$upd['user_remarks']], 'CN', $first);
                    UserModel::cz_balance($data['uid'], $arrival_price, "+", true, "线下入款", 10);

                    if($data['type'] == 1){
                        UserOfflineModel::payset_rebate($data['id'], $data['uid'], $data['pay_id'], $arrival_price);//线上渠道充值返利
                    }else if($data['type'] == 0){
                        UserOfflineModel::bank_sk_rebate($data['id'], $data['uid'], $data['bank_sk_id'], $arrival_price);//线下渠道充值返利
                    }

                    UserOfflineModel::recharge_rebate($data['uid'], $arrival_price);
                }
                MessageService::sendRechargeMsg($data['uid'], $arrival_price);
                UserAdminLogModel::add_log($data['uid'],"线下入款审核通过, 订单号: {$data['order_sn']}, 金额: {$arrival_price}, 备注: " . $upd['remarks'],Session::get("adminInfo.name"));
//                Queue::push("User@upgrade", ['id' => $data['uid'], 'money' => $arrival_price], 'upgrade');
                if($first == 1){
                    UserModel::where('id',$data['uid'])->update(['xc_date' => date("Ym")]);
                    // 发送首充投流像素事件
                    PixelService::firstRecharge($data['uid'], $data['order_sn'], $data['arrival_currency'], $upd['arrival_price']);
                } else {
                    // 发送充值投流像素事件
                    PixelService::recharge($data['uid'], $data['order_sn'], $data['arrival_currency'], $upd['arrival_price']);
                }
                //抽奖充值U金额送积分
                if($data['pay_currency'] == 'USDT'){
                    $this->recharge($data['id']);
                }
                //成功回调方法
                UserOfflineService::successCallback(UserOfflineModel::where('id', $data['id'])->find());
            }
        }
        $data2 = UserOfflineModel::where("id",$info['id'])->find();
        return Respons::run(1,'','',$data,$data2,new UserOfflineModel(),lang('入单审核'));
    }

    //抽奖充值U送积分
    public function recharge($order_id){
        if(empty($order_id)){return;}
        $Config = LotteryConfigModel::where('id',1)->field('uprice,integral')->find();
        if($Config['uprice'] > 0 && $Config['integral'] > 0){
            $pay = UserOfflineModel::where("id",$order_id)->find();
            if (!empty($pay) && $pay['apply_price'] >= $Config['uprice']) {
                UserModel::where('id',$pay['uid'])->inc('integral',$Config['integral'])->update();
                UserMsgModel::add_msg($pay['uid'], "充值送积分", lang('充值奖励已到账: 积分').'{:integral}', 2, $pay['id'],['integral'=>$Config['integral']]);
            }
        }
    }

    //导出数据
    public function excel(Request $request){
        $param = \think\facade\Request::param();
        $where = [];
        $whereStr = '1=1';
        if (isset($param['set_content']) && $param['set_content'] != '') {
            $where[] = ["a.remarks", "like", "%" . $param['set_content'] . "%"];
        }
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.uid", "=", $uids];
        }
        if (isset($param['order_id']) && $param['order_id'] != '') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn'] != '') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['three_order_sn']) && $param['three_order_sn'] != '') {
            $where[] = ["a.othr_number", "=", $param['three_order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price'] != '') {
            $where[] = ['apply_price', ">=", $param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price'] != '') {
            $where[] = ['apply_price', "<=", $param['max_price']];
        }

        //增加代理线搜索条件
        if (!empty($param['agent_name'])) {
            $agent_id = UserModel::where("username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree', 'like', '%/' . $agent_id . '/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }
        //增加上级代理线搜索条件
        if (!empty($param['top_agent_name'])) {
            $top_agent_id = UserModel::where("username|mail|phone", $param['top_agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['rec_id', '=', $top_agent_id];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (!empty($param['level_id'])) {
            $sql = UserModel::where("level_id", $param['level_id'])->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (isset($param['status']) && $param['status'] != '') {
            $where[] = ["a.status", "=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad'] != '') {
            $where[] = ["a.bankcrad", "like", "%" . $param['bankcrad'] . "%"];
        }

        if (isset($param['channel']) && $param['channel'] != '') {
            $where[] = ["a.channel", "=", $param['channel']];
        }
        if (isset($param['uid']) && $param['uid'] > 0) {
            $where[] = ['a.uid', '=', $param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] !== ''){
            if(isset($pay_channel) && count($pay_channel) > 0){
                $pay_set_or_sk = $pay_channel[$param['pay_type']];
                $set_or_sk_id = $pay_set_or_sk['id'];
                if($pay_set_or_sk['p_type'] == 'online'){
                    //线上渠道的时候
                    $wh_field = 'a.pay_id';
                }else{
                    //线下渠道的时候
                    $wh_field = 'a.bank_sk_id';
                }
                $where[] = [$wh_field, '=', $set_or_sk_id];

            }else{
                $where[] = ['a.pay_id','=',$param['pay_type']];
                if( $param['pay_type'] == 0 ){
                    $where[] = ['a.type','=',$param['pay_type']];
                }
            }

        }
        if (isset($param['recharge_type']) && $param['recharge_type'] > 0) {
            if ($param['recharge_type'] == 1) {
                $where[] = ['a.first', '=', 1];
            } else {
                $where[] = ['a.first', '<>', 1];
            }
        }
        if (isset($param['time_type']) && $param['time_type'] != '') {
            if ($param['time_type'] == 1) {
                $time_type = 'a.add_time';
            } else {
                $time_type = 'a.update_time';
            }
        } else {
            $time_type = 'a.add_time';
        }
        if (isset($param['register_time']) && $param['register_time'] !== '') {
            $date = explode(' - ', $param['register_time']);
            $where[] = ['b.add_time', ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ['b.add_time', "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
            $where[] = [$time_type, ">=", Utils::getQueryDateTime($date[0])];
            $where[] = [$time_type, "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[] = ["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[] = ["a.uid", "IN", function ($query) use ($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }

        return (new UserofflineFactory($request->token->uid))->exportList($where,$whereStr);
//        $UserOfflineModel=new UserOfflineModel();
//        $res = UserOfflineModel::alias("a")
//            ->join("dq_user b","a.uid=b.id")
//            ->where($where)->where($whereStr)
//            ->order("a.id desc")
//            ->field("a.*,b.username,b.lebal,b.rec_id,b.tree,b.add_time as register_time")
//            ->select();
//
//        $channel = Util::getOpenChannel();
//        $bank_list = BankSkModel::field('id, bank_number, bank_open, bank_khm, bank_name')->select();
//        $pay_list = PaySetModel::field('id, pay_name')->select();
//        $bank_arr = [];
//        $pay_arr = [];
//        foreach ($bank_list as $val) {
//            $bank_arr[$val['id']] = $val;
//        }
//        foreach ($pay_list as $va) {
//            $pay_arr[$va['id']] = $va;
//        }
//        $state = [-2=>lang('已取消'),-1=>lang('延迟未付款'),1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过'),4=>lang('手动入款')];
//        if (!empty($res)) {
//            // 从 A2 开始填充数据
//            foreach ($res as $k => $v) {
//                $v['lebal'] = ($v['lebal']==1?lang('一般账号'):lang('测试账号'));
//                $channel_name = isset($channel[$v['channel']])?$channel[$v['channel']]:"";
//                $v['update_time'] = (empty($v['update_time'])?"":date("Y-m-d H:i:s",$v['update_time']));
//                $status = (isset($state[$v['status']]) ? $state[$v['status']]:'');
//                $v['add_time'] = date("Y-m-d H:i:s",$v['add_time']);
//                $v['register_time'] = date("Y-m-d H:i:s",$v['register_time']);
//                if($v['type'] == 1){
//                    $bank_info = "";
//                }else{
//                    $bank_info = lang('银行名称').':'.$v['bankname']." ".lang("银行卡号").":".$v['bankcrad']." "." ".lang("户名").':'.$v['realname'];
//                }
//                if($v['type'] == 1){
//                    $sys_info = "";
//                }else{
//                    $bank_name = (isset($bank_arr[$v['bank_sk_id']]['bank_name']) ? $bank_arr[$v['bank_sk_id']]['bank_name']:'');
//                    $bank_number = (isset($bank_arr[$v['bank_sk_id']]['bank_number']) ? $bank_arr[$v['bank_sk_id']]['bank_number']:'');
//                    $bank_open = (isset($bank_arr[$v['bank_sk_id']]['bank_open']) ? $bank_arr[$v['bank_sk_id']]['bank_open']:'');
//                    $bank_khm = (isset($bank_arr[$v['bank_sk_id']]['bank_khm']) ? $bank_arr[$v['bank_sk_id']]['bank_khm']:'');
//                    $sys_info=lang('银行名称').':'.$bank_name." ".lang("银行卡号").":".$bank_number." ".lang('开户行').':'.$bank_open." ".lang("户名").':'.$bank_khm;
//                }
//                $pay_name = (isset($pay_arr[$v['pay_id']]['pay_name']) ? $pay_arr[$v['pay_id']]['pay_name']:'');
//                $parentUser = MemberService::getUsernameByUid($v['rec_id']);
//                $topUser = MemberService::getTopAgentByTree($v['tree']);
//                $v = Utils::hideUserInfo($v);
//
//                $data[] = [
//                    $v['order_sn'],$channel_name,$v['add_time'],$v['uid'],$v['register_time'],$v['lebal'], $topUser, $parentUser, $v['apply_price'],$v['arrival_price'],$pay_name,$v['update_time'],$status,$v['remarks'],$bank_info, $sys_info,$v['admin_name']
//                ];
//            }
//        }
//        $temp =[
//            'uid:用户UID',
//            'order_sn:订单号',
//            'channel_name:数据来源',
//            'add_time:申请时间',
//            'register_time:注册时间',
//            'lebal:用户标签',
//            'top_user:总代',
//            'parent_user:上级',
//            'apply_price:申请金额',
//            'arrival_price:到账金额',
//            'pay_name:支付渠道',
//            'update_time:支付时间',
//            'status:状态',
//            'remarks:备注',
//            'bank_info:用户银行卡信息',
//            'sys_info:收款银行信息',
//            'admin_name:操作人',
//        ];
//        $header = [
//            lang('订单号'),
//            lang('数据来源'),
//            lang('申请时间'),
//            lang('用户UID'),
//            lang('注册时间'),
//            lang('用户标签'),
//            lang('总代'),
//            lang('上级'),
//            lang('申请金额'),
//            lang('到账金额'),
//            lang('支付渠道'),
//            lang('支付时间'),
//            lang('状态'),
//            lang('备注'),
//            lang('用户银行卡信息'),
//            lang('收款银行信息'),
//            lang('操作人')
//        ];
//        $file_name = genCsv($header, $data,lang('会员充值数据').date('YmdHis',time()), false);
//        $s3_url = Uploads::uploadS3(public_path() . $file_name);
//        Utils::addOptLogByHandle('会员充值数据导出');
//        return $this->success([
//            'data' => $s3_url ?: toMedia($file_name, '/')
//        ]);
    }

    public function get_count(Request $request){
        $pay_channel = $this->pay_channel(2) ?? [];
        $param = \think\facade\Request::param();
        $where = [];
        $whereStr = '1=1';
        $time_type = 'a.add_time';
        if (isset($param['set_content']) && $param['set_content'] != '') {
            $where[] = ["a.remarks", "like", "%" . $param['set_content'] . "%"];
        }
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.uid", "=", $uids];
        }
        if (isset($param['order_id']) && $param['order_id'] != '') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn'] != '') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['three_order_sn']) && $param['three_order_sn'] != '') {
            $where[] = ["a.othr_number", "=", $param['three_order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price'] != '') {
            $where[] = ['apply_price', ">=", $param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price'] != '') {
            $where[] = ['apply_price', "<=", $param['max_price']];
        }

        //增加代理线搜索条件
        if (!empty($param['agent_name'])) {
            $agent_id = UserModel::where("username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree', 'like', '%/' . $agent_id . '/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }
        //增加上级代理线搜索条件
        if (!empty($param['top_agent_name'])) {
            $top_agent_id = UserModel::where("username|mail|phone", $param['top_agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['rec_id', '=', $top_agent_id];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (!empty($param['level_id'])) {
            $sql = UserModel::where("level_id", $param['level_id'])->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (isset($param['status']) && $param['status'] != '') {
            $where[] = ["a.status", "=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad'] != '') {
            $where[] = ["a.bankcrad", "like", "%" . $param['bankcrad'] . "%"];
        }

        if (isset($param['channel']) && $param['channel'] != '') {
            $where[] = ["a.channel", "=", $param['channel']];
        }
        if (isset($param['uid']) && $param['uid'] > 0) {
            $where[] = ['a.uid', '=', $param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] !== ''){
            if(isset($pay_channel) && count($pay_channel) > 0){
                $pay_set_or_sk = $pay_channel[$param['pay_type']];
                $set_or_sk_id = $pay_set_or_sk['id'];
                if($pay_set_or_sk['p_type'] == 'online'){
                    //线上渠道的时候
                    $wh_field = 'a.pay_id';
                }else{
                    //线下渠道的时候
                    $wh_field = 'a.bank_sk_id';
                }
                $where[] = [$wh_field, '=', $set_or_sk_id];

            }else{
                $where[] = ['a.pay_id','=',$param['pay_type']];
                if( $param['pay_type'] == 0 ){
                    $where[] = ['a.type','=',$param['pay_type']];
                }
            }

        }
        if (isset($param['recharge_type']) && $param['recharge_type'] > 0) {
            if ($param['recharge_type'] == 1) {
                $where[] = ['a.first', '=', 1];
            } else {
                $where[] = ['a.first', '<>', 1];
            }
        }
        if (isset($param['time_type']) && $param['time_type'] != '') {
            if ($param['time_type'] == 1) {
                $time_type = 'a.add_time';
            } else {
                $time_type = 'a.update_time';
            }
        } else {
            $time_type = 'a.add_time';
        }
        if (isset($param['register_time']) && $param['register_time'] !== '') {
            $date = explode(' - ', $param['register_time']);
            $where[] = ['b.add_time', ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ['b.add_time', "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
            $where[] = [$time_type, ">=", Utils::getQueryDateTime($date[0])];
            $where[] = [$time_type, "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[] = ["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[] = ["a.uid", "IN", function ($query) use ($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }

        // 初始化各个渠道的状态值
        $channels = [
            'bank' => ['title' => '银行卡', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => [0,1]],
            'usdt' => ['title' => 'USDT', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => [2,3]],
            'wallet' => ['title' => '电子钱包', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => 4],
            'pix' => ['title' => 'PIX', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => [6,7]],
            'agent' => ['title' => '银商', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => 20]
        ];

        // 遍历各个渠道并查询相应的值
        foreach ($channels as $channelName => $row) {
            $stay = $this->getSum(1, $row['channel'], $where, $whereStr, 'apply_price');
            $no = $this->getSum(2, $row['channel'], $where, $whereStr, 'apply_price');
            $yes = $this->getSum([3, 4], $row['channel'], $where, $whereStr);
            $count = $this->getCount($row['channel'], $where, $whereStr, false);
            $people = $this->getCount($row['channel'], $where, $whereStr);
            // 将查询结果存入各个渠道的状态值中
            $channels[$channelName]['stay'] = $stay ?? 0;
            $channels[$channelName]['no'] = $no ?? 0;
            $channels[$channelName]['yes'] = $yes ?? 0;
            $channels[$channelName]['count'] = $count ?? 0;
            $channels[$channelName]['people'] = $people ?? 0;
            unset($channels[$channelName]['channel']);
            if (array_sum([$stay, $no, $yes]) == 0) {
                unset($channels[$channelName]);
            }
        }

        $data['data'] = $channels;
        $data['code'] = 1;
        return Respons::run($data);
    }

    /**
     * 统计金额
     * @return float
     */
    private function getSum($status, $channel, $where, $whereStr, $field = "arrival_price") {
        return UserOfflineModel::alias('a')
            ->whereIn('status', $status)
            ->whereIn('channel', $channel)
            ->where($where)
            ->where($whereStr)
            ->sum($field);
    }

    /**
     * 统计数量
     * @return float
     */
    private function getCount($channel, $where, $whereStr, $distinct = true) {
        return UserOfflineModel::alias('a')
            ->whereIn('channel', $channel)
            ->where($where)
            ->where($whereStr)
            ->count($distinct ? "distinct uid" : "uid");
    }

    //充值排行榜
    public function leaderboard(\think\Request $request){
        $rsps = (new UserofflineFactory($request->token->uid))->leaderboard();
        return $rsps;
    }

    // 删除充值记录
    public function delete(Request $request){
        $param = $request->param();
        $info=$param['info'];
        if (empty($info['id'])) {
            return Respons::run(0,[],lang('订单不存在'));
        }
        $detail = UserOfflineModel::where('id',$info['id'])->find();
        if (empty($detail)){
            return Respons::run(0,[],lang('记录已删除'));
        }
        $result = UserOfflineModel::where('id',$info['id'])->delete();
        return Respons::run(1,'','',$detail,$info,new UserOfflineModel(),lang('删除充值记录'));
    }


    //设置订单备注
    public function set_remarks(Request $request){
        $param = $request->param();
        if (isset($param['edit']) && $param['edit']) {
            $info=$param['info'];
            $userOfflineModel = new UserOfflineModel();
            $data = $userOfflineModel->where('id',$info['id'])->find();
            $res=$userOfflineModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$userOfflineModel,lang('订单备注修改'));
        }
    }
}
