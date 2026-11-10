<?php
namespace app\common\model;
use app\common\FastGame\Base\Game;
use app\common\model\UserBalanceMxModel;
use app\common\model\AgentSellLogModel;
use app\common\model\UserReportModel;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use app\common\service\WalletService;
use app\model\SysSets;
use think\Model;
use think\facade\Db;
class UserModel extends Model
{
    // 当前有触发器
	protected $name = 'user';
	protected $schema = [
        'id'          => 'int',
        'add_time'          => 'bigint',
        'login_time'          => 'bigint',
        'real_name'          => 'varchar',
        'withdrawal_psd'          => 'varchar',
        'birthday'          => 'varchar',
        'username'          => 'varchar',
        'password'          => 'varchar',
        'head_img'          => 'text',
        'qh'          => 'varchar',
        'phone'          => 'varchar',
        'phone_status'          => 'int',
        'usertokenkey'          => 'varchar',
        'usertoken'          => 'text',
        'tree'          => 'varchar',
        'rec_code'          => 'varchar',
        'rec_click'          => 'int',
        'rec_id'          => 'int',
        'rec_id2'          => 'int',
        'rec_id3'          => 'int',
        'rec_person'          => 'int',
        'rec_level'  => 'tinyint',
        'lebal'          => 'int',
        'is_check_phone'          => 'int',
        'status'          => 'int',
        'reg_ip'          => 'varchar',
        'end_login_ip'          => 'varchar',
        'line_id'          => 'varchar',
        'level_id'          => 'int',
        'balance' =>  'decimal',
        'water_balance' =>  'decimal',
        'sc_balance' =>  'decimal',
        'xc_balance' =>  'decimal',
        'sc_date' =>  'tinyint',
        'xc_date' =>  'int',
        'withdrawal_balance' =>  'decimal',
        'agent_balance' =>  'decimal',
        'tg_balance' =>  'decimal',
        'win_price' =>  'decimal',
        'lose_price' =>  'decimal',
        'zd_price' =>  'decimal',
        'zd_number' =>  'int',
        'ty_balance' =>  'decimal',
        'ty_end_date' =>  'int',
        'is_ol' =>  'int',
        'ol_time' => 'int',
        'mail' =>  'varchar',
        'limit_min' =>  'decimal',
        'limit_max' =>  'decimal',
        'limit_sum_max' =>  'decimal',
        'savings'=> 'decimal',
        'remark'=> 'varchar',
        'set_fund_money'=> 'decimal',
        'live_fund_money'=> 'decimal',
        'integral'=> 'int',
        'free_count'=> 'int',
        'frozen_amount'=> 'decimal',
        'is_usdt'  => 'tinyint',
        'flyer_money'=> 'decimal',
        'login_ip_count'=> 'decimal',
        'ac_id'=> 'int',
        'reg_url'=> 'varchar',
        'update_time' => 'int',
        'original_level_id' => 'int',
        'cjsavings' => 'tinyint',
        'cjbalance' => 'tinyint',
        'mail_bind_status' => 'int',
        'phone_bind_status' => 'int',
        'auto_mail_notify_sta' => 'int',
        'admin_mail_notify_sta' => 'int',
        'salt'=> 'varchar',
    ];


    /** 操作用户彩金钱包
     * @param $uid 用户ID
     * @param $price 操作金额 统一正数
     * @param $cz 操作符 + -
     * @param $msg 明细信息
     * @param $savingsrate 流水倍数，注意：仅当奖励彩金需要增加流水要求时才使用此字段，或者扣减钱包同时需要扣减流水需求的时候才需要此字段
     * @param int $origin_id 来源ID
     * @param $type 99注单 100注单赢 101彩金变更（奖励或扣减） 其中 99 和 100 调用此方法时一般不使用（在注单处理中已封装）
     * @param int $savingsPrice 流水金额，若设置该值，加减流水时就会按照这个金额计算流水，反之按照操作余额金额计算流水
     * @remarks TODO 后面加了$savingsPrice参数，处理流水时重新赋值了，余额相关处理必须在这个参数赋值前，若后续有余额相关操作，需要注意
     * @return mixed
     */
    public static function cz_cjbalance($uid,$price,$cz,$msg="",$savingsrate=0,$origin_id=0,$type=101, $savingsPrice = 0){
        $balance=self::where(['id' => $uid])->value('cjbalance');
        if ($cz=="+"){
            //增加彩金钱包
            UserModel::where(['id' => $uid])->inc("cjbalance", $price)->update();
            //写彩金日志
            UserCjBalanceMxModel::cz_mx($uid,$msg,$balance,$price,$cz,$origin_id);
            //写彩金流水日志
            if ($savingsrate>0){
                if( $savingsPrice > 0 ){
                    $price = $savingsPrice;
                }
                //增加彩金流水
                UserModel::where(['id' => $uid])->inc("cjsavings", abs($price*$savingsrate))->update();
                MemberService::addUserWaterDetail($uid,abs($price*$savingsrate),$type,$msg,$origin_id,$price, $savingsrate);
            }
        }else if ($cz=="-"){
            if ($price>$balance){
                throw new \Exception("用户彩金钱包错误-余额不足",0);
            }else {
                //扣减彩金钱包
                UserModel::where(['id' => $uid])->dec("cjbalance", $price)->update();
                //写彩金日志
                UserCjBalanceMxModel::cz_mx($uid, $msg, $balance, $price, $cz, $origin_id);
                //写彩金流水日志
                if ($savingsrate>0){
                    if( $savingsPrice > 0 ){
                        $price = $savingsPrice;
                    }
                    //增加彩金流水
                    UserModel::where(['id' => $uid])->where('cjsavings', '>=', abs($price*$savingsrate))->inc("cjsavings", abs($price*$savingsrate)*-1)->update();
                    MemberService::addUserWaterDetail($uid,abs($price*$savingsrate)*-1,$type,$msg,$origin_id,$price, $savingsrate);
                }
            }
        }else{
            throw new \Exception("用户彩金钱包错误-操作符错误",0);
        }
        return true;
    }

    /** 清空用户彩金钱包及彩金流水要求
     * @param $uid 用户ID
     * @param $msg 备注信息
     * @param $lock 是否锁定用户记录操作
     */
    public static function clearCjBalance($uid,$msg,$lock=true){
        if ($lock){
            $user = self::where(['id' => $uid])->field('cjbalance,cjsavings')->lock(true)->find();
        }else {
            $user = self::where(['id' => $uid])->field( 'cjbalance,cjsavings')->find();
        }
        if ($user) {
            //写彩金钱包日志
            UserCjBalanceMxModel::cz_mx($uid,$msg,$user->cjbalance,$user->cjbalance,'-');
            //写彩金流水日志
            MemberService::addUserWaterDetail($uid,$user->cjsavings*-1,99,$msg,0,$user->cjsavings,1);
            //清空彩金钱包
            $user->cjbalance=0;
            $user->cjsavings=0;
            $user->save();
        }else{
            throw new \Exception("清除用户彩金钱包错误-用户未找到",0);
        }
    }

    /** 清空(为满足流水的)用户彩金钱包及彩金流水要求
     * @param $uid 用户ID
     * @param $msg 备注信息
     */
    public static function clearCjBalanceUnmet($uid,$msg){
        $user = self::where(['id' => $uid])->field('cjbalance,cjsavings')->find();
        if ($user) {
//            if ($savings){
//                if($user['cjsavings']!=0){
//                    //写彩金钱包日志
//                    UserCjBalanceMxModel::cz_mx($uid,$msg,$user->cjbalance,$user->cjbalance,'-');
//                    //清空彩金钱包
//                    $user->cjbalance=0;
//                    $user->cjsavings=0;
//                    $user->save();
//                }
//            }else{
                //写彩金钱包日志
                UserCjBalanceMxModel::cz_mx($uid,$msg,$user->cjbalance,$user->cjbalance,'-');
                //清空彩金钱包
                $user->cjbalance=0;
                $user->cjsavings=0;
                $user->save();
//            }
        }else{
            throw new \Exception("清除用户彩金钱包错误-用户未找到",0);
        }
    }


    /** 用户双钱包获取余额（可用于判定余额是否充足）（支持双钱包开关的设置），返回值可以参与注单和钱包金额计算
     * @param $uid 用户ID
     * @param $tcid 三方平台ID,为0为获取余额
     * @param $gid 三方游戏ID,为0为获取余额
     * @param $money 待扣减金额（不实际扣减）,为0为获取余额
     * @param $lock 是否锁定行记录，一般true 特殊情况下根据业务可以设置为false
     * @return array|float [
     * balance ,用户钱包当前余额
     * cjbalance,用户彩金钱包当前余额
     * paybalance, 余额满足要求情况下的现金钱包扣减金额，不满足的情况下此值无效
     * paycjbalance, 余额满足要求情况下的彩金钱包扣减金额，不满足的情况下此值无效
     * vban, 余额满足要求情况下的扣减后余额，不满足的情况下此值无效
     * ok  true 表示余额满足要求 false表示余额不满足要求
     * ]
     * 当$money=0时返回值为当前余额
     */
    public static function getBalanceForDoubleWalletByGid($uid,$tcid = 0,$gid = 0,$money = 0,$lock=false){
        $cjset=SysSets::getSetByCode("sys_cj_support");
        $cjset=($cjset==1);//是否支持彩金
        if ($lock){
            $user = self::where(['id' => $uid])->field('balance, cjbalance')->lock(true)->find();
        }else {
            $user = self::where(['id' => $uid])->field('balance, cjbalance')->find();
        }
        if ($user) {
            if( $money == 0 ){
                return round($cjset ? $user->balance + $user->cjbalance : $user->balance, 2);
            }
            $cj = Game::getGamesInfoBy($tcid,$gid,'id','g_cj');
            if ($cj !==null) {
                switch ($cj) {
                    case 0:
                        $paybalance = ($user->balance >= $money) ? $money : $user->balance;//优先现金投注额
                        $paycjbalance = $money - $paybalance;//彩金投注额
                        $ok = ($user->balance + $user->cjbalance) >= $money;
                        $vban = $ok?($user->balance - $paybalance+$user->cjbalance-$paycjbalance):0;
                        break;
                        break;
                    case 1:
                        $paycjbalance = 0;
                        $paybalance = $user->balance >= $money ? $money : 0;
                        $ok = ($paybalance == $money);
                        $vban = $ok?($user->balance - $paybalance):0;
                        break;
                    case 2://仅支持彩金
                        $paybalance = 0;
                        $paycjbalance = $user->cjbalance >= $money ? $money : 0;
                        $ok = ($paycjbalance == $money);
                        $vban = $ok?($user->cjbalance - $paycjbalance):0;
                        break;
                }
                return [
                    'balance' => $user->balance,
                    'cjbalance' => $user->cjbalance,
                    'paybalance' => $paybalance,
                    'paycjbalance' => $paycjbalance,
                    'vban'=>$vban,
                    'ok' => $ok
                ];
            }else{
                throw new \Exception("调用余额参数错误-游戏信息缓存未命中",0);
            }
        }else{
            throw new \Exception("调用余额参数错误-用户未找到",0);
        }
    }

    /**
     * 用户单流水更新
     * @param $uid
     * @param $money
     * @param $type 1 减流水 2 加流水
     **/
    public static function updateWater($uid, $money, $type=1) {
        if($type == 2){
            self::where([['id','=',$uid]])->inc("savings", $money)->update();
        }else{
            $savings = self::where(['id' => $uid])->value('savings');
            if($savings){
                if($savings > $money){
                    self::where([['id','=',$uid]])->dec("savings", $money)->update();  //储蓄字段 注单减少
                }else{
                    self::where([['id','=',$uid]])->dec("savings", $savings)->update();  //储蓄字段 注单减少
                }
            }
        }
    }

    /**
     * 操作余额
     * @param $uid
     * @param $price
     * @param $cz
     * @param $is_cz_mx 是否操作明细
     * @param $mx_msg 明细名称
     * @param $type 1 注单 2 会员赢 3 调整加 4 调整减  5 活动优惠 6 代理返佣 7 推广返佣 8 会员输 9 交易手续费 10线下入款 11 任务奖励 12 等级奖励 13 领取码奖励 14 签到奖励 15 pdd转盘奖励 16 在线时长奖励 17 五福奖励 18彩金钱包转入余额钱包 19火箭活动奖励 20红包雨奖励 31 活期基金转入  32活期基金转出 33 定期基金转入 34 定期基金转出 35抽奖金额 36中奖金额 37赠金派发 40渠道充值返利 41游戏排行榜奖金结算 100 充值到第三方平台 101 从第三方平台提现
     * @param int $savings 流水倍数
     * @param int $priceType 余额类型（0 余额，1 彩金，2 彩金加余额）2未实现 只有余额增加时才生效
     * @param int $originId 来源id（用户增加彩金钱包）
     * @param boolean $isAddCjSavings 是否增加彩金流水（该字段用于判断是普通彩金还是派彩奖金，派彩奖金不需要增加流水）
     * @param int $cjType 彩金类型（99注单 100注单赢 101彩金变更（奖励或扣减））暂时为用到注单
     **/
    public static function cz_balance($uid, $price, $cz, $is_cz_mx=false, $mx_msg="", $type=0, $savings = 0, $priceType = 0, $originId = 0, $isAddCjSavings = false, $cjType = 100){
        $balance = self::where(['id' => $uid])->value('balance');
        if ($cz=="+") {
            $balancePrice = 0;
            $cjBalance = 0;
            $walletSystemData = WalletService::getWalletData();
            if( $walletSystemData['wallet_type'] == 0 || $priceType == 0 ){
                //单钱包或双钱包仅彩金
                $balancePrice = $price;
            }else{
                //双钱包只有彩金
                $cjBalance = $price;
            }

            $CommomRedis = new CommomRedis();
            $price = 0;
            $fee = 0;#该字段用来兼容下方任务流水部分
            if( $balancePrice ){
                $price = $balancePrice;#该操作为了兼容原来的
                self::where(['id' => $uid])->inc("balance", $price)->update();
                if( $savings ){
                    $fee = $savings * $price;
                    //普通流水
                    self::where(['id' => $uid])->inc("savings", $fee)->update();
                }
                if($type === 10){
                    //线下入款
                    $sys = $CommomRedis->systemInfo();
                    $fee = sprintf("%.2f",$price*$sys['w_water']);
                    if ($fee > 0) {
                        self::where(['id' => $uid])->inc("savings", $fee)->inc("sc_balance", $price)->update();  //储蓄字段 充值增加
                        MemberService::addUserWaterDetail($uid, $fee, $type, $mx_msg, $originId, $price, $sys['w_water']);
                    }
                }else if($type === 6){
                    //代理返佣
                    $sys = $CommomRedis->systemInfo();
                    if(isset($sys['commission_water']) && $sys['commission_water'] > 0){
                        $water =  $sys['commission_water'] * $price;
                        if ($water > 0) {
                            self::where(['id'=>$uid])->inc("savings", $water)->update();
                            MemberService::addUserWaterDetail($uid, $fee, $type, $mx_msg, $originId, $price, $sys['commission_water']);
                        }
                    }
                }else{
                    //其他的统一处理
                    $fee = round($price * $savings, 2);
                    if ($fee > 0) {
                        MemberService::addUserWaterDetail($uid, $fee, $type, $mx_msg, $originId, $price, $savings);
                    }
                }
            }
            if( $cjBalance ){
                self::cz_cjbalance($uid, $cjBalance, $cz, $mx_msg, $isAddCjSavings?$savings:0, $originId, $cjType);
            }
        }elseif($cz=="-"){
              self::where(['id' => $uid])->dec("balance", $price)->update();
        }else{
            throw new \Exception("余额操作错误", 0);
        }
        self::where(['id' => $uid])->update(['update_time' => time()]);
        if ($is_cz_mx) {
            //操作明细
            return UserBalanceMxModel::cz_mx($uid, $mx_msg, $balance, $price, $cz);
        }
        return true;
    }

    /**
     * 设置用户是否在线
     * @param $uid
     * @param $is_ol 1 or 0
    **/
    public static function is_ol($uid,$is_ol){
        $upd['id']=$uid;
        $upd['is_ol']=$is_ol;
        $upd['ol_time']=time();
        self::update($upd);
        return true;
    }

    /**
     * 推荐人数加一
     * @param $uid
     * @param $tg_condition 推广条件
    **/
    public static function rec_person_add($uid, $tg_condition=0){
        //后面需求有变更，推广要求为1，不能每个都自动生成记录，不需要自动生成记录
        return true;
        $people = 0;
        if($tg_condition > 0){
            $recU = UserModel::alias("a")->join("dq_user_offline b","a.id=b.uid")->field('a.id,sum(arrival_price) as sum')->where(["a.rec_id" => $uid])->where("b.status in(3,4)")->group('b.uid')->select();
            foreach($recU as $v){
                if($v['sum'] >= $tg_condition){
                    $people++;
                }
            }
        }else{
            $recU = UserModel::alias("a")->field('a.id')->where(["a.rec_id" => $uid])->select();
            foreach($recU as $v){
                $people++;
            }
        }
        $CommomRedis = new CommomRedis();
        if ($CommomRedis->lock("AgentSellLog".$uid)) {
            AgentSellLogModel::if_reward($uid,$people);
        }
        return true;
    }

    /**
     * 会员基础信息
     * @param $uid
     * @param $field 其他字段
    **/
    public static function uinfo($uid,$field=""){
        $userDetail = self::alias("a")
            ->join("dq_user_level b","a.level_id=b.id", 'left')
            ->join("dq_user_extra c","a.id=c.uid", 'left')
            ->where("a.id",$uid)
            ->field("a.id,a.username,a.add_time,a.head_img,a.rec_id,a.balance,a.cjbalance,a.cjsavings,a.lebal,b.id as level_id,b.level_name,b.image as level_image,a.limit_min,a.limit_max,a.limit_sum_max,a.tree,a.savings".$field)
            ->find();
        if( $userDetail && $userDetail->level_id === null ){
            $userDetail->level_id = 0;
        }
        return $userDetail;
    }
    /**
     * 获取推广用户的上两级用户id
     * @param $agent_id 上级id
    **/
    public static function get_agent_id($agent_id){
        $agent2 = $agent3 = 0;
        $agent1 = $agent_id;
        if($agent1 > 0){
            $agent2 = self::where(['id' => $agent1])->value('rec_id');
            if(!empty($agent2)){
                $agent3 = self::where(['id' => $agent2])->value('rec_id');
            }
        }
        return [$agent1, $agent2, $agent3];
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'username'=>lang('用户名'),
            'ids'=>lang('用户ID'),
            'mail'=>lang('邮箱'),
            'new_password'=>lang('登陆密码'),
            'withdrawal_pwd'=>lang('提现密码'),
            'real_name'=>lang('真实姓名'),
            'limit_min'=>lang('最低下注'),
            'limit_max'=>lang('最高下注'),
            'limit_sum_max'=>lang('单场最高下注'),
            'level_id'=>lang('会员等级'),
            'top_code'=>lang('上级推广码'),
            'is_agent'=>lang('开通代理'),
            'lebal'=>lang('用户标签'),
            'status'=>lang('用户状态'),
            'phone'=>lang('手机号'),
            'is_check_phone'=>lang('手机验证'),
            'reg_ip'=>lang('注册ip'),
            'rec_id'=>lang('上级id'),
            'integral'=>lang('积分'),
            'remark'=>lang('备注'),
            'original_level_id'=>lang('原等级'),
        ];
        $set_key = [
            ['is_agent'=>[1=>lang('开通'),2=>lang('不开通')]],
            ['lebal'=>[1=>lang('一般用户'),2=>lang('测试用户')]],
            ['status'=>[0=>lang('禁用'),1=>lang('开启')]],
            ['is_check_phone'=>[0=>lang('不需要'),1=>lang('需要')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }


    public function current(): \think\model\relation\BelongsTo
    {
      return  $this->belongsTo(UserLevelModel::class,'level_id','id');
    }

    //关联登录相同ip数量
    public function userip(){
        return $this->hasMany(self::class, 'end_login_ip', 'end_login_ip');
    }

    //关联充值
    public function offline(){
        return $this->hasMany(UserOfflineModel::class, 'uid', 'id');
    }

    //关联下注
    public function global(){
        return $this->hasMany(ThirdStatsGloBalModel::class, 'su_uid', 'id');
    }

    //统一备注
    public static function betRemarks($orderNumber, $thirdName = '', $gid = 0, $remarks = '下注'){
        return $thirdName.'_['.$gid.']_'.$remarks.'_'.$orderNumber;
    }
}
