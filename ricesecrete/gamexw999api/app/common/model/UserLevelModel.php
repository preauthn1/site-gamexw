<?php
namespace app\common\model;
use app\api\redis\ApiRedis;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\ArtificialBalanceModel;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use think\facade\Session;
use think\Model;
use think\facade\Db;

class UserLevelModel extends Model
{
	protected $name = 'user_level';
	protected $schema = [
        'id'          => 'int',
        'level_name' =>  'varchar',
        'recharge_amount' =>  'decimal',
        'transaction_amount' =>  'decimal',
        'min_amount' =>  'decimal',
        'max_amount' =>  'decimal',
        'day_limit' =>  'int',
        'day_free_limit' =>  'int',
        'level_gold' =>  'decimal',
        'remarks' =>  'varchar',
        'image' =>  'varchar',
        'type'  => 'tinyint',
        'first_user' =>  'int',
        'team_user' =>  'int',
        'is_valid' =>  'tinyint',
        'water' => 'decimal',
        'week_water' => 'decimal',
        'month_water' => 'decimal',
        'first_user_level' => 'int',
        'week_reward_amount' => 'decimal',
        'month_reward_amount' => 'decimal',
        'savings' => 'int',
        'day_max_amount' => 'decimal',
    ];

    /*
     * 升级用户VIP等级 同时发放等级佣金
     */
    public static function upgrade_level($uid, $arrival_price = 0, $sys=''){
        $user = UserModel::where('id', $uid)->find();
        if(empty($user)){
            return false;
        }
        if(empty($sys)){
            $CommomRedis = new CommomRedis();
            $sys = $CommomRedis->systemInfo();
        }
        $tg_condition = $sys['tg_condition'];
        if(isset($sys['vip_upgrade'])){
            $vip_upgrade = $sys['vip_upgrade'];
        }else{
            $vip_upgrade = 0;
        }

        if($vip_upgrade == 0){
            $old_level_id = $user['level_id'];
//            $next_level = self::where(['id' => ($user['level_id']+1)])->field("id, type")->find();
            $next_level = self::where('id', '>', $user['level_id'])->order('id asc')->field("id, type")->limit(1)->find();
//            $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_bet'); // 流水
//            $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_validwater'); // 有效流水
            if( !$next_level ) return false;
            //判断用户是否降级。如果降级，按最新降级时间作为起始时间从0计算升级条件
            //最新降级时间
            $downgrade_time = UserLevelDownRecordModel::where('uid', '=', $uid)->order('id desc')->field("time")->limit(1)->find();
            //$downgrade_old_level_id = UserLevelDownRecordModel::where('uid', '=', $uid)->order('old_level_id desc')->field("old_level_id")->limit(1)->find();
            $level_recharge_amount = 0;
            $level_water = 0;
            if($downgrade_time){
                //添加时间条件todo
                $su_dt = date("Ymd",$downgrade_time['time']);
                $add_time = $downgrade_time['time'];
                $level = self::where([['id', '=', $old_level_id]])->field('recharge_amount, water')->select();
                //加上当前等级充值/流水
                $level_recharge_amount =$level[0]['recharge_amount'];
                $level_water =$level[0] ['water'];
                $water_where[] = ['su_dt', '>', $su_dt];
                $arrival_price_where[] = ['add_time', '>', $add_time];
            }
            $table = \app\common\service\BetService::getThirdStatsTable();
            $water_where[] = ['su_uid', '=', $uid];
            $water = Db::name($table['_stats_global'])->where($water_where)->sum('su_valid_bet');//及时有效流水
            $arrival_price_where[] = ["uid",'=',$uid];
            $arrival_price_where[] = ["status", 'in',"3,4"];
            if ($next_level['type'] == 0) {
                $arrival_price = UserOfflineModel::where($arrival_price_where)->sum("arrival_price"); // 累积充值额
            } else {
                //$arrival_price = UserOfflineModel::where(["uid" => $uid])->where([["status", 'in', "3,4"]])->max("arrival_price");
                $arrival_price = UserOfflineModel::where($arrival_price_where)->max("arrival_price");
            }
            $arrival_price  += $level_recharge_amount;
            $water += $level_water;
            $level_id = self::where("recharge_amount <= ".$arrival_price." and water<=".$water)->order('id desc')->value("id");
            self::winnings($uid, $old_level_id, $level_id, $user['lebal']);
            return ['old_level_id' => $old_level_id, "level_id" => $level_id, "water" => $water, "arrival_price" => $arrival_price];
        }else{
            $res = self::upgrade_level2($vip_upgrade, $user, $tg_condition);
            return $res;
        }
    }

    public static function upgrade_level2($vip_upgrade, $user, $tg_condition){
        $res = self::upgrade_level3($vip_upgrade, $user['id'], $tg_condition); // 自己升级

        if($user['rec_id'] > 0){ // 一级代理升级
            self::upgrade_level3($vip_upgrade, $user['rec_id'], $tg_condition);
        }
        if($user['rec_id2'] > 0){ // 二级代理升级
            self::upgrade_level3($vip_upgrade, $user['rec_id2'], $tg_condition);
        }
        if($user['rec_id3'] > 0){ // 三级代理升级
            self::upgrade_level3($vip_upgrade, $user['rec_id3'], $tg_condition);
        }
        return $res;
    }

    public static function upgrade_level3($vip_upgrade, $uid, $tg_condition){
        $user = UserModel::where('id', $uid)->find();
        $price = 0;
//        $next_level = self::where(['id' => ($user['level_id']+1)])->field("id, type, first_user_level")->find();
        $next_level = self::where('id', '>', $user['level_id'])->order('id asc')->field("id, type, first_user_level")->limit(1)->find();
        $where = "";
        //判断用户是否降级。如果降级，按最新降级时间作为起始时间从0计算升级条件
        //最新降级时间
        $downgrade_time = UserLevelDownRecordModel::where('uid', '=', $uid)->order('id desc')->field("time")->limit(1)->find();
        //$downgrade_old_level_id = UserLevelDownRecordModel::where('uid', '=', $uid)->order('old_level_id desc')->field("old_level_id")->limit(1)->find();
        $level_recharge_amount = 0;
        $level_water = 0;
        if($downgrade_time){
            //添加时间条件todo
            $su_dt = date("Ymd",$downgrade_time['time']);
            $add_time = $downgrade_time['time'];
            $level = self::where([['id', '=', $user['level_id']]])->field('recharge_amount, water')->select();
            //加上当前等级充值/流水
            $level_recharge_amount =$level[0]['recharge_amount'];
            $level_water =$level[0]['water'];
            $water_where[] = ['su_dt', '>', $su_dt];
            $arrival_price_where[] = ['add_time', '>', $add_time];
        }

        if($vip_upgrade == 1){
            if ($next_level['first_user_level'] > 0) {
                $first_user = UserModel::where([['rec_id','=', $uid],['sc_balance', '>=', $tg_condition],['level_id', '>=', $next_level['first_user_level']]])->count();
            } else {
                $first_user = UserModel::where([['rec_id','=', $uid],['sc_balance', '>=', $tg_condition]])->count();
            }
            $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid}) and sc_balance>={$tg_condition}")->count();
            $where = "first_user <= ".$first_user." and team_user <= ".$team_user;
        } else if($vip_upgrade == 3){
            if ($next_level['first_user_level'] > 0) {
                $first_user = UserModel::where([['rec_id','=', $uid],['sc_balance', '>=', $tg_condition],['level_id', '>=', $next_level['first_user_level']]])->count();
            } else {
                $first_user = UserModel::where([['rec_id','=', $uid],['sc_balance', '>=', $tg_condition]])->count();
            }
            $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid}) and sc_balance>={$tg_condition}")->count();
            //$price = UserOfflineModel::where(["uid"=>$uid])->where([["status", 'in',"3,4"]])->sum("arrival_price"); // 累积充值额
            $arrival_price_where[] = ["uid",'=',$uid];
            $arrival_price_where[] = ["status", 'in',"3,4"];
            $price = UserOfflineModel::where($arrival_price_where)->sum("arrival_price"); // 累积充值额
            $price += $level_recharge_amount;
            $where = "recharge_amount <= ".$price." and first_user <= ".$first_user." and team_user <= ".$team_user;
        }else{
            if ($next_level['first_user_level'] > 0) {
                $first_user = UserModel::where([['rec_id','=', $uid],['level_id', '>=', $next_level['first_user_level']]])->count();
            } else {
                $first_user = UserModel::where([['rec_id', '=', $uid]])->count();
            }
            $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid})")->count();
            $where = "first_user <= ".$first_user." and team_user <= ".$team_user;
        }
//        $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_bet'); // 流水
//        $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_validwater'); // 有效流水
        $table = \app\common\service\BetService::getThirdStatsTable();
        $water_where[] = ['su_uid', '=', $uid];
        $water = Db::name($table["_stats_global"])->where($water_where)->sum('su_valid_bet');//及时有效流水
        //$water = Db::name($table["_stats_global"])->where([['su_uid', '=', $uid]])->sum('su_valid_bet');//及时有效流水
        $water += $level_water;
	$level_id = self::where($where." and water<=".$water." and id = ".$next_level['id'])->order('id desc')->value("id");
        $old_level_id = $user['level_id'];
        self::winnings($uid, $old_level_id, $level_id ?? 0, $user['lebal']);
        return ['old_level_id' => $old_level_id, "level_id" => $level_id ?? 0, "water" => $water, "first_user" => $first_user, "team_user" => $team_user, 'price' => $price];
    }

    /*
     * 更新用户等级 派送升级彩金
     */
    public static function winnings($uid, $old_level_id, $level_id=0, $lebal=1){
        if($old_level_id < $level_id){
            UserModel::where(["id"=>$uid])->save(['level_id' => $level_id]); // 更新用户等级
            $level = self::where("id > ".$old_level_id." and id <=".$level_id)->select();
            if(!empty($level)){
               foreach($level as $key => $val){
                    if($val['level_gold'] > 0){
                        $mx = UserModel::cz_balance($uid, $val['level_gold'], "+", true, "VIP等级彩金", 12, $val['savings'], 1, $val['id'], true, 101);

                        $add['admin_name'] = Session::get("adminInfo.name");
                        $add['uid'] = $uid;
                        $add['water'] = $val['savings'];
                        $add['a_type'] = 4;
                        $add['mx_id'] = $mx['id'];
                        $add['price'] = $val['level_gold'];
                        $add['add_time'] = time();
                        $add['lebal']= $lebal;
                        $add['msg'] = "VIP等级彩金";
                        $_id = (new ArtificialBalanceModel())->insertGetId($add);
                    }
                }
            }
        }
    }
    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'level_name'=>lang('等级名称'),
            'recharge_amount'=>lang('累积充值金额'),
            'min_amount'=>lang('最低出款金额'),
            'max_amount'=>lang('最高出款金额'),
            'day_limit'=>lang('每日出款限制'),
            'day_free_limit'=>lang('每日出款免费额度'),
            'level_gold'=>lang('彩金'),
            'remarks'=>lang('备注'),
            'water'=>lang('流水'),
            'week_water'=>lang('周流水限制'),
            'month_water'=>lang('月流水限制'),
            'first_user'=>lang('直推会员'),
            'team_user'=>lang('团队会员'),
            'reward_type'=>lang('奖励类型'),
            'reward_amount'=>lang('奖励金额'),
            'day_max_amount'=>lang('每日总出款限制'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }


    public static function getnext_level_rate($uid,$level_id,$next_level){
        $CommomRedis = new CommomRedis();
        $sys = $CommomRedis->systemInfo();
        $tg_condition = $sys['tg_condition'];
        if(isset($sys['vip_upgrade'])){
            $vip_upgrade = $sys['vip_upgrade'];
        }else{
            $vip_upgrade = 0;
        }
        $rate = $CommomRedis->get('next_level_rate_'.$uid.'_level_id_'.$level_id);
        if(!$rate){
            //判断用户是否降级。如果降级，按最新降级时间作为起始时间从0计算升级条件
            //最新降级时间
            $downgrade_time = UserLevelDownRecordModel::where('uid', '=', $uid)->order('id desc')->field("time")->limit(1)->find();
            //$downgrade_old_level_id = UserLevelDownRecordModel::where('uid', '=', $uid)->order('old_level_id desc')->field("old_level_id")->limit(1)->find();
            $level_recharge_amount = 0;
            $level_water = 0;
            if($downgrade_time){
                //添加时间条件todo
                $su_dt = date("Y-m-d",$downgrade_time['time']);
                $add_time = $downgrade_time['time'];
                $level = self::where([['id', '=', $level_id]])->field('recharge_amount, water')->select();
                //加上当前等级充值/流水
                $level_recharge_amount =$level[0]['recharge_amount'];
                $level_water =$level[0]['water'];
                $water_where[] = ['su_dt', '>', $su_dt];
                $arrival_price_where[] = ['add_time', '>', $add_time];
            }   
            $arrival_price_where[] = ["uid",'=',$uid];
            $arrival_price_where[] = ["status", 'in',"3,4"];
            if ($vip_upgrade == 0) {   //累积充值额
                if( $next_level['recharge_amount'] > 0 ){
                    if ($next_level['type'] == 0) {
                        //$rate = UserOfflineModel::where(["uid" => $uid])->where([["status", 'in', "3,4"]])->sum("arrival_price") / $next_level['recharge_amount'] * 100;
			$rate = UserOfflineModel::where($arrival_price_where)->sum("arrival_price")+$level_recharge_amount / $next_level['recharge_amount'] * 100;

                    } else {
                        //$rate = UserOfflineModel::where(["uid" => $uid])->where([["status", 'in', "3,4"]])->max("arrival_price") / $next_level['recharge_amount'] * 100;
			$rate = UserOfflineModel::where($arrival_price_where)->max("arrival_price")+$level_recharge_amount / $next_level['recharge_amount'] * 100;

                    }
                }else{
                    $rate = 0;
                }
            } else if ($vip_upgrade == 1) {  //有效推广会员数
                if ($next_level['first_user_level'] > 0) {
                    $first_user = UserModel::where([['rec_id', '=', $uid], ['sc_balance', '>=', $tg_condition], ['level_id', '>=', $next_level['first_user_level']]])->count();
                } else {
                    $first_user = UserModel::where([['rec_id', '=', $uid], ['sc_balance', '>=', $tg_condition]])->count();
                }
                $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid}) and sc_balance>={$tg_condition}")->count();
                $rate = ($first_user + $team_user) / ($next_level['first_user'] + $next_level['team_user']) * 100;
            } else if ($vip_upgrade == 3) { //有效推广会员数+充值金额
                if ($next_level['first_user_level'] > 0) {
                    $first_user = UserModel::where([['rec_id', '=', $uid], ['sc_balance', '>=', $tg_condition], ['level_id', '>=', $next_level['first_user_level']]])->count();
                } else {
                    $first_user = UserModel::where([['rec_id', '=', $uid], ['sc_balance', '>=', $tg_condition]])->count();
                }
                $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid}) and sc_balance>={$tg_condition}")->count();
              	//$price = UserOfflineModel::where(["uid" => $uid])->where([["status", 'in', "3,4"]])->sum("arrival_price"); // 累积充值额
                $price = UserOfflineModel::where($arrival_price_where)->sum("arrival_price"); // 累积充值额
                $price += $level_recharge_amount;
                $res1 = ($first_user + $team_user) / ($next_level['first_user'] + $next_level['team_user']) * 100;
                $res2 = ($next_level['recharge_amount']) ? $price / $next_level['recharge_amount'] * 100 : 0;
                $rate = ($res1 + $res2) / 200 * 100;
            } else {  //推广会员数
                if ($next_level['first_user_level'] > 0) {
                    $first_user = UserModel::where([['rec_id', '=', $uid], ['level_id', '>=', $next_level['first_user_level']]])->count();
                } else {
                    $first_user = UserModel::where([['rec_id', '=', $uid]])->count();
                }
                $team_user = UserModel::where("(rec_id={$uid} or rec_id2={$uid} or rec_id3={$uid})")->count();
                $rate = ($first_user + $team_user) / ($next_level['first_user'] + $next_level['team_user']) * 100;
            }
//            $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_bet'); // 流水
//            $water = Db::name("third_stats_users")->where([['su_uid', '=', $uid]])->sum('su_validwater'); // 有效流水
            $table = \app\common\service\BetService::getThirdStatsTable();
            //$water = Db::name($table['_stats_global'])->where([['su_uid', '=', $uid]])->sum('su_valid_bet');//及时有效流水
		$water_where[] = ['su_uid', '=', $uid];
            $water = Db::name($table['_stats_global'])->where($water_where)->sum('su_valid_bet');//及时有效流水
            $water += $level_water; 
            if ($next_level['water'] > 0) {
                $waters = ($water / $next_level['water']) * 100;
                $rate1 = $rate > 100 ? 100:$rate;
                $waters1 = $waters > 100 ? 100:$waters;
                $rate = ($rate1 + $waters1) / 200 * 100;
            }
            $CommomRedis->set('next_level_rate_'.$uid.'_level_id_'.$level_id,$rate,300);
        }
        return $rate > 100 ? 100 : intval($rate);
    }


    //获取VIP对应的游戏流水和返利比例
    public static function getVipGameWaterAndBackRate($isDatabase = false){
        $apiRedis = new ApiRedis();
        $vipRedisKey = 'vip_data_key';
        if( !$apiRedis->has($vipRedisKey) || $isDatabase ){
            $vipData = self::order('id asc')->field('id')->select();
            $apiRedis->set($vipRedisKey, json_encode($vipData), 86400 * 2);
        }else{
            $vipData = json_decode($apiRedis->get($vipRedisKey), true);
        }

        //获取VIP对应的游戏流水和返利比例
        $vipGameWaterRedisKey = 'vip_game_water_key';
        if( !$apiRedis->has($vipGameWaterRedisKey) || $isDatabase ) {
            $vipGameWater = [];
            foreach ($vipData as $value) {
                $vipGameWater[$value['id']] = GameBackConfigModel::alias('gbc')
                    ->join('game_type gt', 'gt.id=gbc.gbc_gtid')
                    ->where('gbc.gbc_viplvl', '=', $value['id'])
                    ->field('gbc.gbc_rate as rate,gt.name')
                    ->select()->toArray();
            }
        }else{
            $vipGameWater = json_decode($apiRedis->get($vipGameWaterRedisKey), true);
        }
        return $vipGameWater;
    }
}
