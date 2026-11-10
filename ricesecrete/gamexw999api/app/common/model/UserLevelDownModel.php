<?php
namespace app\common\model;
use app\common\model\UserModel;
use app\common\model\UserLevelModel;
use app\common\model\UserOfflineModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserLevelDownRecordModel;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use Carbon\Carbon;
use think\facade\Session;
use think\Model;
use think\facade\Db;

class UserLevelDownModel extends Model
{
	protected $name = 'user_level_down';
	protected $schema = [
        'id'          => 'int',
        'level_id' =>  'int',
        'recharge_amount' =>  'decimal',
        'water' => 'decimal',
        'type' => 'tinyint',
        'down_cycle' => 'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'level_id'=>lang('等级id'),
            'recharge_amount'=>lang('充值金额'),
            'water'=>lang('流水金额'),
            'type'=>lang('充值计算方式'),
            'down_cycle'=>lang('计算周期'),
        ];
        $set_key = [
            ['type' => [ 0 =>lang('累计充值'), 1 => lang('单笔充值')]],
            ['down_cycle' => [ 0 =>lang('每日'), 1 => lang('每周'), 2 => lang('每月')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
    
    /*
     * 降级(保级)用户VIP等级
     */

    public static function downgrade_level($uid, $arrival_price = 0, $sys=''){
        $user = UserModel::where('id', $uid)->find();
        if(empty($user)){
            return false;
        }
        if(empty($sys)){
            $CommomRedis = new CommomRedis();
            $sys = $CommomRedis->systemInfo();
        }
        $tg_condition = $sys['tg_condition'];
        if(isset($sys['vip_downgrade'])){
            $vip_downgrade = $sys['vip_downgrade'];
        }else{
            $vip_downgrade = 0;
        }
        if($vip_downgrade == 0){
            $old_level_id = $user['level_id'];
            $next_level = UserLevelModel::where('id', '<', $user['level_id'])->order('id desc')->field("id, type")->limit(1)->find();
            if( !$next_level ) return false;

            //获取当前等级的保级配置
            $currentLevelDownDetail = self::where("level_id", '=', $user['level_id'])->find();
            if( !$currentLevelDownDetail ){
                //没有数据不降级
                return false;
            }

            //保级周期(0每天，1每周，2每月)
            $vipDownCycle = $currentLevelDownDetail->down_cycle;
            if( $vipDownCycle == 0 ){
                //每日
                $add_time = Carbon::now()->addDay(-1)->timestamp;
            }elseif ( $vipDownCycle == 1 ){
                //每周
                $add_time = Carbon::now()->addWeek(-1)->timestamp;
            }else{
                //每月
                $add_time = Carbon::now()->addMonth(-1)->timestamp;
            }
            $su_dt = date("Ymd", $add_time);

            $table = \app\common\service\BetService::getThirdStatsTable();
            $water = Db::name($table['_stats_global'])->where([['su_uid', '=', $uid]])->where([['su_dt', '>', $su_dt]])->sum('su_valid_bet');//及时有效流水
            if ($currentLevelDownDetail->type == 0) {
                $arrival_price = UserOfflineModel::where(["uid"=>$uid])->where([["status", 'in',"3,4"]])->where([["add_time", '>',$add_time]])->sum("arrival_price"); // 累积充值额
            } else {
                $arrival_price = UserOfflineModel::where(["uid" => $uid])->where([["status", 'in', "3,4"]])->where([["add_time", '>',$add_time]])->max("arrival_price");
            }
            $isDown = false;
            if( $currentLevelDownDetail->recharge_amount > 0 && $arrival_price < $currentLevelDownDetail->recharge_amount ){
                //设置保级充值金额且充值金额未达标
                $isDown = true;
            }
            if( $currentLevelDownDetail->water > 0 && $water < $currentLevelDownDetail->water ){
                //设置保级流水金额且流水金额未达标
                $isDown = true;
            }

            //保级成功
            if(!$isDown)return false;
            //保级失败，降级
            self::downgrade_level_record($uid, $old_level_id, $next_level['id'], $user['lebal']);
            return ['old_level_id' => $old_level_id, "level_id" => $next_level['id'], "water" => $water, "arrival_price" => $arrival_price];
        }else{
            //推广会员数
        }
    }
    
    /*
     * 更新用户等级
     */
    public static function downgrade_level_record($uid, $old_level_id, $level_id=0, $lebal=1){
        if($old_level_id > $level_id){
            // 更新用户等级
            UserModel::where(["id"=>$uid])->save(['level_id' => $level_id]); 
            $add['uid'] = $uid;
            $add['level_id'] = $level_id;
            $add['old_level_id'] = $old_level_id;
            $add['time'] = time();
            UserLevelDownRecordModel::insert($add);
        }
    }
}

 