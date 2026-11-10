<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Uploads;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserReportModel;
use app\common\model\UserModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\service\MessageService;
use app\common\service\PixelService;
use app\common\service\UserWithdrawalService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\model\PaySetModel;
use app\common\payment\Entry;
use app\common\redis\CommomRedis;
use app\common\lib\Util;
use app\common\Utils;
use think\facade\Lang;
use think\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use think\facade\Config;
use app\manage\factory\UserWithdrawalFactory;
class UserWithdrawal
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(){
        $channel = Util::getWOpenChannel();
        $param=Request::param();

        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        
        $p_wh['is_withdra'] = 1;
        $p_wh['isdel'] = 0;
        $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        View::assign('pay_type',$pay_type);
        View::assign('uid',$param['uid']);
        View::assign("channel",$channel);
        return View::fetch('user_withdrawal/list');
    }

    public function get_user_data(\think\Request $request)
    {
        $rsps = (new UserWithdrawalFactory($request->token->uid))->get_user_data();
        return $rsps;
    }

    public function user_list(){
        $param=Request::param();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $channel = Util::getWOpenChannel();
        $p_wh['isdel'] = 0;
        $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        View::assign('pay_type',$pay_type);
        View::assign("channel",$channel);
        View::assign('uid',$param['uid']);
        return View::fetch('user_withdrawal/list');
    }
    
    public function verify(Request $request){
        $channel = Util::getWOpenChannel();
        $param = $request->param();
        $limit = $param['limit'];
        $time_type = 'a.add_time';
        $where = [];
        $where[]=["a.status","=", 1];
        $whereStr = '';
        if (isset($param['username']) && $param['username']!='') {
            $uid = UserModel::where([['username','=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uid];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[]=["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[]=["a.id", "=", $param['order_id']];
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=", $param['status']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[]=['w_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[]=['w_price',"<=",$param['max_price']];
        }
        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username",$param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= 'a.uid in ('.$sql .')';
        }
        
        if (isset($param['lebal'])) {
            if ($param['lebal'] != '') $where[]=["a.lebal","=", $param['lebal']];
        }else{
            $where[] = ["a.lebal", "=", 1];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }

        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.method_id','=',$param['pay_type']];
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
        
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }
        
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }

        $rsps['data']= UserWithdrawalModel::alias("a")
            ->where($where)->where($whereStr)
            ->order($order)
            ->field("a.*,"
                    .'(select username from dq_user where id=a.uid limit 1) as username,'
                    .'(select remark from dq_user where id=a.uid limit 1) as remark')
            ->paginate($limit,false,['query' => $param]);
        
        foreach($rsps['data'] as $key => &$val){
            $val['channel_name'] = isset($channel[$val['channel']])?lang($channel[$val['channel']]):"";
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
            $val['update_time'] = $val['update_time']>0?date("Y-m-d H:i:s",$val['update_time']):'';
            if($val['method_id'] > 0){
                $val['pay_name'] = PaySetModel::where(['id'=>$val['method_id']])->value('pay_name');
            }
        }
        $rsps['code']=1;
        return Respons::run($rsps);
        // $p_wh['isdel'] = 0;
        // $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        // View::assign('pay_type',$pay_type);
        // View::assign("channel",$channel);
        // return View::fetch('user_withdrawal/verify');
    }
    
    //编辑
    public function add(Request $request){
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        $sys = Utils::sysInfo();
        $info=$param['info'];
        if (empty($info['username'])) {
            return Respons::run(0,[],lang('请选择用户'));
        }
        
        if ($info['price']<=0 || $info['price']=='') {
            return Respons::run(0,[],lang('请输入出款金额'));
        }
        if (!$CommomRedis->lock()) {
            throw new \Exception(lang("请勿频繁访问"), 0);
        }
        Db::startTrans();
        try {
            $uids = explode(",", $info['username']);
            foreach($uids as $val){
                $user = UserModel::where("id",$val)->find();
                if (!isset($user['id'])) {
                    throw new \Exception(lang("用户不存在"), 1);
                }
                if (!($user['balance']>=$info['price'])) {
                    throw new \Exception("UID:{$user["id"]};" . lang("余额不足"), 0);
                }
                $currency = Util::getPayCurrency(['pay_type' => 0], 1);
                $add['uid'] = $user['id'];
                $add['channel'] = 0;
                $add['bank_id'] = 0;
                $add['status'] = 4;
                $add['w_price'] = $info['price'];
                $add['w_remarks'] = $info['mx_msg'];
                $add['arrival_price'] = round(($info['price'] - ($info['price']*($sys['service_charge'])/100)) * $currency['exchange_rate'], 2);
                $add['arrival_prices'] = round($info['price'] - ($info['price']*($sys['service_charge'])/100), 2);
                $add['current_currency'] = $currency['current_currency'];
                $add['arrival_currency'] = $currency['arrival_currency'];
                $add['exchange_rate'] = $currency['exchange_rate'];
                $add['w_service'] = round($info['price'] * ($sys['service_charge']/100), 2);
                $add['lebal'] = $user['lebal'];
                $add['update_time']=time();
                $add['add_time']=time();
                $add['check_time']=time();
                $id = UserWithdrawalModel::insertGetId($add);
                $upd['id']=$id;
                $upd['order_sn']=date("YmdHi").$id;
                UserWithdrawalModel::update($upd);
                // 提现事件
                PixelService::withdraw($user['id'], $upd['order_sn'], $currency['current_currency'], $add['w_price']);
                UserModel::cz_balance($user['id'], $add['w_price'], "-", true, "余额提现");
                Util::email_notify('with', $user['id']);
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
        $CommomRedis->unlock();
        return Respons::run($rsps,'','','',$info,new UserWithdrawalModel(),lang('手动添加出款'));
    }

    public function edit_detail(Request $request) {
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
        $sys = Utils::sysInfo();
        $res = UserWithdrawalModel::alias("a")
            	->join("dq_user b","a.uid=b.id")
            	->join("dq_user_bank c","a.bank_id=c.id")
                ->where("a.id",$param['id'])
                ->field("a.*,b.username,b.mail,b.phone,b.lebal,c.bank_number,c.bank_open,c.bank_hm,c.bank_name,c.financial")
                ->find();
        $whereChannel = [
            ['is_withdra', '=', 1],
            ['isdel', '=', 0],
        ];
        if($res['channel'] == 3){
            $whereChannel[] = ['pay_type', 'IN', '0,3'];
        }else{
            $whereChannel[] = ['pay_type', '=', $res['channel']];
        }
        $channel = PaySetModel::where($whereChannel)->order("id desc")->select();
        $is_digital_currency_open = 2;

        $channel_list = [];
        if(count($channel) > 0){
            $channelCount = 1;
            foreach ($channel as $key => $value) {
                if($value['pay_sys_sign'] == 'AlphapoPay'){
                    $is_digital_currency_open = 1;
                }
                $value["pay_bank"]=json_decode($value['pay_bank'], true)??[];
                $names = [];
                foreach ($value["pay_bank"] AS $row) {
                    if (strexists($row['bank_name'], "@@")) {
                        continue;
                    }
                    $names[] = strtoupper($row['bank_name']);
                }

                if (!in_array(strtoupper($res['bank_name']), $names) && $res['channel'] == 0) {
                    continue;
                }
                $channel_list[$value['id']] = $value;
            }
        }else{
//            $channel_list[0]["pay_bank"] = [];
            $channelCount = 0;
        }
//        expected_arrival
        //        $pay_set_bank = [];
        $rate = [];
        //Alphapo 数字货币钱包 digital_currency_open
        if(env('digital_currency_open', 0)){
            $pay_sign = 'AlphapoPay';
            $p_w = [];
            $p_w[] = ['pay_status', '=', 1]; //开启代付的时候
            $p_w[] = ['pay_sys_sign', '=', $pay_sign]; //开启代付的时候
            $pay_config = PaySetModel::where($p_w)->where('pay_bank', '<>', '[]')->find();
            $pay_bank = json_decode($pay_config['pay_bank'], true);
            foreach($pay_bank as $key => $val){
                $pay_set_bank[] = $val['bank_name'];
                $pay_set_code[ $val['bank_name']] = $val['bank_code'];
            }
            $pay_set_bank = array_unique($pay_set_bank);

            $sys = (new ApiRedis())->systemInfo();
            $p_config = [];
            $pay_param = json_decode($pay_config['pay_param'],true);
            foreach ($pay_param as $key => $value) {
                $p_config[$value['key_name']]=$value['key_value'];
            }
            $Entry = new Entry($p_config, $sys['domain']);
            $result = $Entry->get_rate($pay_config['pay_sys_sign'], 0);//回调结果
            if($result['code'] == 1){
                $rate = $result['data'];
            }
            PaySetModel::where($p_w)
                ->where('pay_param', '<>', '[]')
                ->filter(function ($data) use(&$fees_arr){
                    $pay_param = json_decode($data['pay_param'],true);
                    $c__arr = [];
                    foreach($pay_param as $ky => $vl){
                        $c__arr[$vl['key_name']] = $vl['key_value'];
                    }
                    if(isset($c__arr['currency'])){
                        $fees_arr[$c__arr['currency']] = $c__arr['fees'];
                    }
                })
                ->select();
            if(in_array($res['bank_name'], $pay_set_bank)){
                $code = $pay_set_code[$res['bank_name']];
                $res['expected_arrival'] =  $res['arrival_price'];
                if(isset($fees_arr[$code]) ){
                    $_withdrawal_rate  = $rate[$res['bank_name']] ?? 1;
                    if($fees_arr[$code] == 0){
//                    if($fees_arr[$res['bank_name']] == 0){
//                        $_withdrawal_rate = $_withdrawal_rate * ((100 - $fees_arr[$res['bank_name']]) / 100);
                    }else{
                        $_withdrawal_rate = $_withdrawal_rate * ((100 - $fees_arr[$code]) / 100);
                    }
//                    if($fees_arr[$res['bank_name']] != 0){
//                        $_withdrawal_rate = $_withdrawal_rate * ((100 - $fees_arr[$res['bank_name']]) / 100);
//                    }
//                    $_withdrawal_rate = $_withdrawal_rate * ((100 - $fees_arr[$res['bank_name']]) / 100);
                    $res['expected_arrival'] =  $this->truncateFloat($res['arrival_price'] * $_withdrawal_rate);
                    $res['exchange_rate'] =  $_withdrawal_rate;
                }
            }
            if($res['submit_third_price'] == 0){
                $res['submit_third_price'] = '';
            }
        }else{
            unset($res['submit_third_price']);
        }
        $withdraw_notes = [
            ["value"=>lang('投注流水不足')],
            ["value"=>lang('银行卡信息有误')],
            ["value"=>lang('持卡人姓名有误.请联系客服更正')],
            ["value"=>lang('提现银行维护中')],
        ];

        if(empty($sys['withdraw_notes'])){
            $w_remarks_arr = $withdraw_notes;
        }else{
            $w_remarks_arr = unserialize($sys['withdraw_notes']);
        }
        $res['w_remarks_arr'] = $w_remarks_arr;
        $res['add_time'] = date("Y-m-d H:i:s",$res['add_time']);
        $res['update_time'] = $res['update_time']>0?date("Y-m-d H:i:s",$res['update_time']):'';
        $res['cutrr_status'] = $res['status'];
        $res['channelCount'] = $channelCount;
        $res['channel_list'] = $channel_list;
        $res['is_digital_currency_open'] = $is_digital_currency_open;
        $res['pay_id'] = '';
        $res['bank_code'] = '';
        if (!is_null($res['mail'])) {
            $res['username'] = $res['mail'];
        } elseif (!is_null($res['phone'])) {
            $res['username'] = $res['phone'];
        }
        $res = Utils::hideUserInfo($res);
        return Respons::run(1,$res);
    }
    
    //编辑
    public function edit(Request $request){
        $info = $request->param();
        return UserWithdrawalService::check($info);
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
        $UserWithdrawalModel =new UserWithdrawalModel();
        $res=$UserWithdrawalModel->where("id","in",$param['id'])->delete();
        return Respons::run($res?1:0,'','',$param,$param,new UserWithdrawalModel(),lang('删除提现记录'));
    }
    
    //导出数据
    public function excel(Request $request){
        $param = $request->param();
        $where=[];
        $whereStr = '';
        $time_type = 'a.add_time';
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[]=["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['status1']) && $param['status1']!='') {
            $where[]=["a.status","=", $param['status1']];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[]=["a.id", "=", $param['order_id']];
        }
        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username|mail|phone",$param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= 'a.uid in ('.$sql .')';
        }

        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.uid", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        } else {
            $where[] = ["a.lebal", "=", 1];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[]=['w_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[]=['w_price',"<=",$param['max_price']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.method_id','=',$param['pay_type']];
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

        return (new UserWithdrawalFactory($request->token->uid))->exportList($where,$whereStr);
//        $UserWithdrawalModel = new UserWithdrawalModel();
//        $res = $UserWithdrawalModel
//            ->alias("a")
//            ->leftjoin("dq_user_bank c","a.bank_id=c.id")
//            ->where($where)->where($whereStr)
//            ->order("a.id desc")
//            ->field("a.*,c.bank_number,c.bank_open,c.bank_hm,c.bank_name,"
//                    .'(select username from dq_user where id=a.uid limit 1) as username')
//            ->select();
//
//        $channel = Util::getOpenChannel();
//        $state = [1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过'),4=>lang('手动入款')];
//
//        foreach($res as $value){
//            $channel_name = isset($channel[$value['channel']])?$channel[$value['channel']]:"";
//            $value['application_time'] = date("Y-m-d H:i:s",$value['add_time']);
//            $value['username'] = $value['username'];
//            $value['lebal'] = ($value['lebal']==1?lang('一般账号'):lang('测试账号'));
//            $value['w_price'] = $value['w_price'];
//            $value['arrival_price'] = $value['arrival_price'];
//            $value['w_service'] = $value['w_service'];
//            $value['w_remarks'] = $value['w_remarks'];
//            if($value['channel'] == 0 ){
//                $bank_info=lang('银行名称')."：".$value['bank_name']." ".lang('银行卡号')."：".$value['bank_number']." ".lang('开户行')."：".$value['bank_open']." ".lang('户名')."：".$value['bank_hm'];
//            }elseif($value['channel'] == 1){
//                $bank_info=lang('链名称')."：".$value['bank_name']." ".lang('收币地址')."：".$value['bank_number'];
//            }elseif($value['channel'] == 2 || $value['channel'] == 3){
//                $bank_info=lang('钱包名称')."：".$value['bank_name']." ".lang('钱包地址')."：".$value['bank_number'];
//            }
//            $value['withdrawal_info'] = $bank_info;
//            $value['status'] = isset($state[$value['status']]) ? $state[$value['status']]:'';
//            $value['channel_name'] = $channel_name;
//            $value['operator'] = $value['operator'];
//            $value['pay_type'] = $value['pay_type'];
//            $value['order_sn'] = $value['order_sn'];
//            $value = Utils::hideUserInfo($value);
//
//            $data[] = [
//                $value['application_time'],
//                $value['uid'],
//                $value['lebal'],
//                $value['w_price'],
//                $value['arrival_price'],
//                $value['w_service'],
//                $value['w_remarks'],
//                $value['withdrawal_info'],
//                $value['status'],
//                $value['channel_name'],
//                $value['operator'],
//                $value['pay_type'],
//                $value['order_sn']
//            ];
//        }
//
//        $temp = [
//            'uid:用户UID',
//            'application_time:申请时间',
//            'lebal:用户标签',
//            'w_price:提现金额',
//            'arrival_price:到账金额',
//            'w_service:手续费',
//            'w_remarks:备注',
//            'withdrawal_info:提现信息',
//            'status:状态',
//            'channel_name:数据来源',
//            'operator:操作人',
//            'pay_type:代付渠道',
//            'order_sn:订单号',
//        ];
//        $header = [
//            lang('申请时间'),
//            lang('用户UID'),
//            lang('用户标签'),
//            lang('提现金额'),
//            lang('到账金额'),
//            lang('手续费'),
//            lang('备注'),
//            lang('提现信息'),
//            lang('状态'),
//            lang('数据来源'),
//            lang('操作人'),
//            lang('代付渠道'),
//            lang('订单号')
//        ];
//
//        $file_name = genCsv($header, $data,lang('会员提现数据').date('YmdHis',time()), false);
//        $s3_url = Uploads::uploadS3(public_path() . $file_name);
//        Utils::addOptLogByHandle('会员提现数据导出');
//        return Respons::run([
//            'code' => 1,
//            'data' => $s3_url ?: toMedia($file_name, '/')
//        ]);
    }


    public function get_count(Request $request){
        $param = $request->param();
        $channel = Util::getWOpenChannel();
        $b_w = []; //银行卡的条件
        $u_w = []; //USDT的条件
        $b_w['a.channel'] = 0;
        $u_w['a.channel'] = 1;
        $w_w['a.channel'] = 2;
        $p_w['a.channel'] = 3;
        $b_w['a.status'] = 1;
        $u_w['a.status'] = 1;
        $w_w['a.status'] = 1;
        $p_w['a.status'] = 1;

        if(isset($param['type']) && $param['type'] == 'verify'){
            $where[] = ['a.status','=',1];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] =['a.uid','=',$param['uid']];
        }

        $whereStr = '';
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[]=["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[]=["a.id", "=", $param['order_id']];
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad']!='') {
            $where[]=["a.bankcrad","=", $param['bankcrad']];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[]=['w_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[]=['w_price',"<=",$param['max_price']];
        }
        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= 'a.uid in ('.$sql .')';
        }

        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.uid", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        } else {
            $where[] = ["a.lebal", "=", 1];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }

        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.method_id','=',$param['pay_type']];
        }
        $time_type = 'a.add_time';
        if(isset($param['time_type']) && $param['time_type'] != ''){
            if($param['time_type'] != 1){
                $time_type = 'a.update_time';
            }
        }

        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }
        if(isset($channel[0])){
            $bank['stay'] = UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $b_w['a.status'] = 2;
            $bank['no'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $b_w['a.status'] = [3,4];
            $bank['yes'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $b_w['a.status'] = 5;
            $bank['ing'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $bank = [];
        }
        
        if(isset($channel[1])){
            $usdt['stay'] = UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $u_w['a.status'] = 2;
            $usdt['no'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $u_w['a.status'] = [3,4];
            $usdt['yes'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $u_w['a.status'] = 5;
            $usdt['ing'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $usdt = [];
        }
        
        if(isset($channel[2])){
            $wallet['stay'] = UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $w_w['a.status'] = 2;
            $wallet['no'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $w_w['a.status'] = [3,4];
            $wallet['yes'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $w_w['a.status'] = 5;
            $wallet['ing'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $wallet = [];
        }
        
        if(isset($channel[3])){
            $pix['stay'] = UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $p_w['a.status'] = 2;
            $pix['no'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $p_w['a.status'] = [3,4];
            $pix['yes'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $p_w['a.status'] = 5;
            $pix['ing'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $pix = [];
        }
        
        $data['data']['bank'] = $bank;
        $data['data']['usdt'] = $usdt;
        $data['data']['wallet'] = $wallet;
        $data['data']['pix'] = $pix;
        $data['code']=1;
        return Respons::run($data);
    }

    //提现排行榜
    public function leaderboard(\think\Request $request){
        $rsps = (new UserWithdrawalFactory($request->token->uid))->leaderboard();
        return $rsps;
    }

    //设置订单备注
    public function set_remarks(Request $request){
        $param = $request->param();
        if (isset($param['edit']) && $param['edit']) {
            $info=$param['info'];
            $userWithdrawalModel = new UserWithdrawalModel();
            $data = $userWithdrawalModel->where('id',$info['id'])->find();
            $res=$userWithdrawalModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$userWithdrawalModel,lang('订单备注修改'));
        }
    }

    //订单锁定/解锁
    public function lockOrUnlock(Request $request){
        $param = $request->param();
        if (isset($param['id']) && $param['id']) {
            $userWithdrawalModel = new UserWithdrawalModel();
            $data = $userWithdrawalModel->where('id', $param['id'])->find();
            $data->is_lock = $param['is_lock'];
            $res = $data->save();
            return Respons::run($res ? 1 : 0, '', '', $data, $param, $userWithdrawalModel, lang('订单锁定/解锁'));
        }
    }



    function truncateFloat($number, $decimals = 6) {
        // 将数字转换为字符串
        $numberStr = (string) $number;

        // 查找小数点的位置
        $decimalPos = strpos($numberStr, '.');

        // 如果小数点存在
        if ($decimalPos !== false) {
            // 获取小数点后的部分
            $decimalPart = substr($numberStr, $decimalPos + 1);

            // 保留指定的小数位数
            $truncatedDecimalPart = substr($decimalPart, 0, $decimals);

            // 重新组装结果
            $result = substr($numberStr, 0, $decimalPos + 1) . $truncatedDecimalPart;

            // 返回处理后的结果
            return (float) $result;
        }
        // 如果没有小数点，直接返回原数值
        return $number;
    }
}