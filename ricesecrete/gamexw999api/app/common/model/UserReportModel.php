<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class UserReportModel extends Model
{
	protected $name = 'user_report';
	protected $schema = [
        'id'          => 'int',
        'uid'  =>  'int',
        'start_time' => 'bigint',
        'zd_number' =>  'int',
        'zd_amount' =>  'decimal',
        'sy_amount' =>  'decimal',
        'trans_fee' =>  'decimal',
        'adjust_add' =>  'decimal',
        'adjust_reduce' =>  'decimal',
        'yh_amount' =>  'decimal',
        'agent_commission' =>  'decimal',
        'share_commission' =>  'decimal',
        'recharge_number' =>  'int',
        'recharge' =>  'decimal',
        'withdrawal_number' =>  'int',
        'withdrawal' =>  'decimal',
        'lottery_money' =>  'decimal',
        'order_cancel' =>  'decimal',
        'order_cancel_num' =>  'int',
    ];
    /*
     * 更新用户统计报表
     * $uid 用户Id
     * $type 1 注单 2 会员赢 3 调整加 4 调整减  5 活动优惠 6 代理返佣 7 代理返佣 8 会员输 9 交易手续费
     * $money 金额
     */
    public static function updateReport($uid, $type, $money){
        /* 加统计报表 */
        if($money > 0){
            $start_time = strtotime(date("Y-m-d")." 00:00:00");
            $report = self::where("uid=".$uid." and start_time=".$start_time)->find();
            if(empty($report)){
                $data = [];
                $data['uid'] = $uid;
                $data['start_time'] = $start_time;
                $report['id'] = self::insertGetId($data);
            }

            if(!empty($report)){
                if($type === 1){ // 注单
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("zd_number", 1)->inc("zd_amount", $money)->update();
                }
                if($type === 2){ // 会员赢金额
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("sy_amount", $money)->update();
                }
                if($type === 3){ // 调整加
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("adjust_add", $money)->update();
                }
                if($type === 4){ // 调整减
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("adjust_reduce", $money)->update();
                }
                if($type === 5){ // 活动优惠
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("yh_amount", $money)->update();
                }
                if($type === 6){ // 代理返佣
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("agent_commission", $money)->update();
                }
                if($type === 7){ // 推广返佣
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("share_commission", $money)->update();
                }
                if($type === 8){ // 会员输金额
                    self::where("uid=".$uid." and start_time=".$start_time)->dec("sy_amount", $money)->update();
                }
                if($type === 9){ // 交易手续费
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("trans_fee", $money)->update();
                }
                if($type === 10){ // 充值
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("recharge_number", 1)->inc("recharge", $money)->update();
                }
                if($type === 11){ // 提现
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("withdrawal_number", 1)->inc("withdrawal", $money)->update();
                }
                if($type === 12){ // 提现
                    self::where("uid=".$uid." and start_time=".$start_time)->inc("lottery_money", $money)->update();
                }
            }
        }
    }
}