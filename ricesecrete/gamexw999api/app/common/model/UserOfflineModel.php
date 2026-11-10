<?php
namespace app\common\model;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserModel;
use app\common\service\MemberService;
use DateTime;
use think\facade\Session;
use app\common\Utils;
use think\Model;
class UserOfflineModel extends Model
{
	protected $name = 'user_offline';
	protected $schema = [
        'id'          => 'int',
        'type'  =>  'tinyint',
        'channel'  =>  'tinyint',
        'order_sn'  =>  'varchar',
        'uid'  =>  'int',
        'bank_id'  =>  'int',
        'bank_sk_id'  =>  'int',
        'bankname'  =>  'varchar',
        'bankcrad'  =>  'varchar',
        'realname'  =>  'varchar',
        'paydate'  =>  'varchar',
        'apply_price'  =>  'decimal',
        'arrival_price'  =>  'decimal',
        'pay_id'  =>  'int',
        'pay_currency'  =>  'varchar',
        'arrival_currency'  =>  'varchar',
        'exchange_rate'  =>  'decimal',
        'pay_type' => 'varchar',
        'pay_ip' => 'varchar',
        'othr_number' => 'varchar',
        'remarks'  =>  'varchar',
        'status'  =>  'int',
        'admin_name'  =>  'int',
        'update_time'  =>  'bigint',
        'add_time'  =>  'bigint',
        'tree'  =>  'tinyint',
        'lebal'  =>  'tinyint',
        'first'  =>  'tinyint',
        'receive'  =>  'tinyint',
        'credentials'  =>  'varchar',
        'set_content'  =>  'text',
        'not_auto_action' => 'int',
        'rebate' => 'decimal',
        'user_remarks'  =>  'varchar'
    ];

    public static function recharge_rebate($uid, $money=0, $channel=-1){
        if($money > 0){
            $sys = Utils::sysInfo();
            $recharge_rebate_channel = $sys['recharge_rebate_channel']??0;
            $is_rebate = true;
            if($recharge_rebate_channel == 1 && $channel != 2 && $channel != 3){ // USDT返利
                $is_rebate = false;
            } elseif($recharge_rebate_channel == 2 && ($channel == 2 || $channel == 3)){ // 法币返利
                $is_rebate = false;
            }
            if($is_rebate){
                $recharge_rebate = $sys['recharge_rebate']??0;
                $recharge_rec_rebate = $sys['recharge_rec_rebate']??0;
                $rebate_money = round($money * $recharge_rebate / 100,2);
                $rebate_rec_money = round($money * $recharge_rec_rebate / 100,2);
                if($rebate_money > 0){
                    $mx = UserModel::cz_balance($uid, $rebate_money, "+", true, "充值返利", 12, 1, 1, 0, true, 101);
                    $add = [];
                    $add['uid'] = $uid;
                    $add['a_type'] = 5;
                    $add['mx_id'] = $mx['id'];
                    $add['price'] = $rebate_money;
                    $add['add_time'] = time();
                    $add['lebal']= 1;
                    $add['msg'] = "充值返利";
                    $add['admin_name'] = Session::get("adminInfo.name");
                    ArtificialBalanceModel::insert($add);
                }
                $rec_id = UserModel::where(['id' => $uid])->value('rec_id');
                if($rec_id > 0 && $rebate_rec_money > 0){
                    $mx = UserModel::cz_balance($rec_id, $rebate_rec_money, "+", true, "充值返利", 12, 1, 1, 0, true, 101);
                    $add = [];
                    $add['uid'] = $rec_id;
                    $add['a_type'] = 5;
                    $add['mx_id'] = $mx['id'];
                    $add['price'] = $rebate_rec_money;
                    $add['add_time'] = time();
                    $add['lebal']= 1;
                    $add['msg'] = "充值返利";
                    $add['admin_name'] = Session::get("adminInfo.name");
                    ArtificialBalanceModel::insert($add);
                }
            }
        }
    }
    //查看是否有线下充值
    public static function is_pay($uid){
        return self::where("uid",$uid)->where("status in (3,4)")->find();
    }

    /*
     * 获取充值人数
     */
    public static function getRechargeUserCount($where=[], $where1=[], $where2 = []){
        $offline_price_num = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->where($where2)->group('a.uid')->count();
        return $offline_price_num;
    }

    /*
        * 获取充值人数
        */
    public static function getRechargeUserCountHaving($having,$where=[], $where1=[], $where2 = []){
        $offline_price_num = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->having($having)->where($where)->where($where1)->where($where2)->group('a.uid')->count();
        return $offline_price_num;
    }
    /*
     * 获取充值金额
     */
    public static function getRechargeUserAmount($where=[], $where1=[], $channel=0){
        if($channel == 0){ // 获取总充值金额
            $recharge_amount = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->sum("a.arrival_price"); //全部充值;
        }elseif($channel == 1){//线下金额
            $recharge_amount = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"],["a.channel", 'in', "0,4,6"],['a.type', '=', 0]])->where($where)->where($where1)->sum("a.arrival_price");//线下金额
        }elseif($channel == 2){//线上充值
            $recharge_amount = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"],['a.channel', 'in', "1,5,7"],['a.type', '=', 1]])->where($where)->where($where1)->sum("a.arrival_price"); //线上充值
        }elseif($channel == 3){//USDT充值
            $recharge_amount = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"],["a.channel", 'in',"2,3"]])->where($where)->where($where1)->sum("a.apply_price"); //USDT充值;
        }elseif($channel == 4){//充值笔数
            $recharge_amount = self::alias("a")->join('user b', 'b.id=a.uid')->where([["a.status", 'in',"3,4"]])->where($where)->where($where1)->count(); //充值成功笔数;
        }
        return $recharge_amount;
    }


    //存日志记录字段
    public function getField(){
        $field = [
            'uid'=>lang('用户id'),
            'price'=>lang('入单金额'),
            'status'=>lang('审核状态'),
            'remarks'=>lang('备注'),
            'mx_msg'=>lang('备注'),
            'pay_type'=>lang('支付渠道'),
        ];
        $set_key = [
            ['status'=>[1=>lang('待审核'),2=>lang('拒绝'),3=>lang('已通过'),4=>lang('通过')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //获取合计
    public static function get_count($wh = [],$wh1 = [],$wh2 = [],$group=''){
        return self::alias('a')->where($wh)->where($wh1)->where($wh2)->group($group)->count();
    }

    //获取某个字段总计
    public static function get_sum($wh = [],$wh1 = [],$wh2 = [],$field = 'arrival_price'){
        return self::alias('a')->where($wh)->where($wh1)->where($wh2)->sum($field);
    }

    public static function   payset_rebate($pay_id, $uid, $pay_set_id, $money){
        if($money > 0){
            $payset = PaySetModel::where(['id' => $pay_set_id])->find();
            $w[] = ['ps_id' ,'=', $pay_set_id];
            $w[] = ['min' ,'<=', $money];
            $w[] = ['max' ,'>', $money];
            //查询有没有设置返利
            $rebate = RebateSetModel::where($w)->find();
            $is_consist = $payset['is_consist'];
            //判断代理线 、 用户标签 、返利次数
            $true = true;
            if (isset($payset['rebate_agent_id']) && !empty($payset['rebate_agent_id']) && $payset['rebate_agent_id'] > 0){
                //获取用户数据
                $userDetail = UserModel::uinfo($uid);
                $tree = explode("/", $userDetail->tree);
                if($is_consist == 2){
                    //只有设定的代理线才能参加
                    //如果上级为空或者代理id不相等的时候 不参加返利活动
                    if (!isset($tree[1]) || ($tree[1] != $payset['rebate_agent_id'])) {
                        $true = false;
                    }
                }else{
                    //除了设定的代理线都能参加
                    //如果一级代理存在 并且代理id相等的时候 不参加返利活动
                    if (isset($tree[1]) && ($tree[1] == $payset['rebate_agent_id'])) {
                        $true = false;
                    }
                }
            }
            if (isset($payset['tag_id']) && !empty($payset['tag_id']) && $payset['tag_id'] > 0 ) {
                $user_tags = MemberService::getUserTagId($uid);
                if($is_consist == 2) {
                    //只有设定的标签才能参加
                    if (!in_array($payset['tag_id'], $user_tags)) {
                        $true = false;
                    }
                }else{
                    //只有设定的标签不能参加
                    if (in_array($payset['tag_id'], $user_tags)) {
                        $true = false;
                    }
                }
            }
            //有次数限制的时候
            if(isset($rebate['num_limit']) && $rebate['num_limit'] > 0){
                $A_w = [];
                $A_w[] = ['uid', '=', $uid];
                $A_w[] = ['a_type', '=', 5];
                $A_w[] = ['rebate_id', '=', $rebate['id']];
                //并且有天数限制的时候
                if(isset($rebate['day']) && $rebate['day'] > 0){
                    $days = $rebate['day'];
                    $now = new DateTime();
                    if ($days == 1) {
                        // 获取今天的 0 点到 24 点
                        $start = (clone $now)->setTime(0, 0);  // 今天0点
                        $end = (clone $now)->setTime(23, 59, 59);  // 今天23:59:59（即今天的24点）
                    } else {
                        // 其他情况下可以根据需求设置
                        // 这里以 3 为例，可以根据实际需求扩展
                        $start = (clone $now)->modify("-{$days} days")->setTime(0, 0);  // 三天前的 0 点
                        $end = (clone $now)->setTime(23, 59, 59);  // 今天的 24 点
                    }
                    $A_w[] = ['add_time', '>=', strtotime($start->format('Y-m-d H:i:s'))];
                    $A_w[] = ['add_time', '<=', strtotime($end->format('Y-m-d H:i:s'))];
                }
                $A_id = ArtificialBalanceModel::where($A_w)->count();
                if($A_id >= $rebate['num_limit']){
                    $true = false;
                }
            }
            if(isset($rebate['proportion']) && $rebate['proportion'] > 0 && $true){
                //充值表 存储返利金额
                $re['rebate'] = round(($money * $rebate['proportion']) / 100,2);
                //1.账变记录  充值成功后 增加一条返利的账变记录
                //2.返利记录 记录一条返利记录
                UserOfflineModel::where("id", $pay_id)->update($re);
                $mx = UserModel::cz_balance($uid, $re['rebate'], "+", true, "充值返利", 40, $rebate['water']);
                $add = [];
                $add['uid'] = $uid;
                $add['a_type'] = 5;
                $add['mx_id'] = $mx['id'];
                $add['price'] = $re['rebate'];
                $add['add_time'] = time();
                $add['lebal']= 1;
                $add['rebate_id']= $rebate['id'];
                $add['msg'] = "充值返利";
                $add['water'] = $rebate['water'];
                $add['admin_name'] = Session::get("adminInfo.name");
                ArtificialBalanceModel::insert($add);
            }
        }
    }

    public static function bank_sk_rebate($pay_id, $uid, $pay_set_id, $money){
        if($money > 0){
            $payset = BankSkModel::where(['id' => $pay_set_id])->find();
            $w[] = ['bank_sk_id' ,'=', $pay_set_id];
            $w[] = ['min' ,'<=', $money];
            $w[] = ['max' ,'>', $money];
            //查询有没有设置返利
            $rebate = BankSkRebateSetModel::where($w)->find();
            $is_consist = $payset['is_consist'];
            //判断代理线 、 用户标签 、返利次数
            $true = true;
        if (isset($payset['rebate_agent_id']) && !empty($payset['rebate_agent_id']) && $payset['rebate_agent_id'] > 0){
            //获取用户数据
            $userDetail = UserModel::uinfo($uid);
            $tree = explode("/", $userDetail->tree);
            if($is_consist == 2){
                //只有设定的代理线才能参加
                //如果上级为空或者代理id不相等的时候 不参加返利活动
                if (!isset($tree[1]) || ($tree[1] != $payset['rebate_agent_id'])) {
                    $true = false;
                }
            }else{
                //除了设定的代理线都能参加
                //如果一级代理存在 并且代理id相等的时候 不参加返利活动
                if (isset($tree[1]) && ($tree[1] == $payset['rebate_agent_id'])) {
                    $true = false;
                }
            }

        }
        if (isset($payset['tag_id']) && !empty($payset['tag_id']) && $payset['tag_id'] > 0 ) {
            $user_tags = MemberService::getUserTagId($uid);
            if($is_consist == 2) {
                //只有设定的标签才能参加
                if (!in_array($payset['tag_id'], $user_tags)) {
                    $true = false;
                }
            }else{
                //只有设定的标签不能参加
                if (in_array($payset['tag_id'], $user_tags)) {
                    $true = false;
                }
            }

        }
            //有次数限制的时候
            if($rebate['num_limit'] > 0){
                $A_w = [];
                $A_w[] = ['uid', '=', $uid];
                $A_w[] = ['a_type', '=', 5];
                $A_w[] = ['bank_sk_id', '=', $rebate['id']];
                //并且有天数限制的时候
                if(isset($rebate['day']) && $rebate['day'] > 0){
                    $days = $rebate['day'];
                    $now = new DateTime();
                    if ($days == 1) {
                        // 获取今天的 0 点到 24 点
                        $start = (clone $now)->setTime(0, 0);  // 今天0点
                        $end = (clone $now)->setTime(23, 59, 59);  // 今天23:59:59（即今天的24点）
                    } else {
                        // 其他情况下可以根据需求设置
                        // 这里以 3 为例，可以根据实际需求扩展
                        $start = (clone $now)->modify("-{$days} days")->setTime(0, 0);  // 三天前的 0 点
                        $end = (clone $now)->setTime(23, 59, 59);  // 今天的 24 点
                    }
                    $A_w[] = ['add_time', '>=', strtotime($start->format('Y-m-d H:i:s'))];
                    $A_w[] = ['add_time', '<=', strtotime($end->format('Y-m-d H:i:s'))];
                }
                $A_id = ArtificialBalanceModel::where($A_w)->count();
                if($A_id >= $rebate['num_limit']){
                    $true = false;
                }
            }

            if($rebate && $true){
                //充值表 存储返利金额
                $re['rebate'] = round(($money * $rebate['proportion']) / 100,2);
                //1.账变记录  充值成功后 增加一条返利的账变记录
                //2.返利记录 记录一条返利记录
                UserOfflineModel::where("id", $pay_id)->update($re);
                $mx = UserModel::cz_balance($uid, $re['rebate'], "+", true, "充值返利", 40, $rebate['water']);
                $add = [];
                $add['uid'] = $uid;
                $add['a_type'] = 5;
                $add['mx_id'] = $mx['id'];
                $add['price'] = $re['rebate'];
                $add['add_time'] = time();
                $add['lebal']= 1;
                $add['msg'] = "充值返利";
                $add['water'] = $rebate['water'];
                $add['admin_name'] = Session::get("adminInfo.name");
                $add['bank_sk_id']= $rebate['id'];
                ArtificialBalanceModel::insert($add);
            }
        }
    }
}
