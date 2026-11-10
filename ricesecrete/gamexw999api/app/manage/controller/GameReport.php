<?php
namespace app\manage\controller;

use app\common\Utils;
use app\api\factory\{FlyerFactory, GameFactory};
use app\common\model\{GameSetModel, GameTxModel, GameReportOperatingModel};
use think\facade\{Config, Session, View};
use app\common\lib\Respons;
use app\common\redis\CommomRedis;
use think\Request;

class GameReport
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    
    //第三方游戏报表
    public function index(Request $request){
        $param = $request->param();
        if (isset($param['select'])) {
            $arr = [];
            $start_time = strtotime(date('Ymd235959'));
            $end_time = $start_time - 7*86400;
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
                $end_time = Utils::getQueryDateTime($date[0]);
                $start_time = Utils::getQueryDateTime($date[1], true);
            }
            /* @var GameReportOperatingModel $GameReportOperatingModel*/
            $GameReportOperatingModel = app()->make(GameReportOperatingModel::class);
            for($i = $start_time;$i >= $end_time;$i-=86400){
                $date = date("Y-m-d", $i);
                $data = $GameReportOperatingModel->where("day_time",$date)
                    ->when(isset($param['game_name'])  && !empty($param['game_name']), function($query) use($param){
                        $query->where(['game_name' => $param['game_name']]);
                    })
                    ->when(isset($param['game_code']) && !empty($param['game_code']), function($query) use($param){
                        $query->where(['game_code' => $param['game_code']]);
                    })
                    ->select();
                $bets_number = 0;
                $bets_total = 0;
                $pay_total = 0;
                $user_total_loss = 0;
                $platform_total_lose = 0;
                foreach($data as $k=>$v){
                    $bets_number += $v['bets_number'];
                    $bets_total += $v['bets_total'];
                    $pay_total += $v['pay_total'];
                    $user_total_loss += $v['user_total_loss'];
                    $platform_total_lose += $v['platform_total_lose'];
                }
                    $arr[$date]['date']         = $date;
                    $arr[$date]['game_name']   = isset($data[0]) ? $data[0]['game_name'] : '';  //游戏名称

                    $arr[$date]['game_code']   = isset($data[0]) ? $data[0]['game_code'] : '';  //游戏代号
                    $arr[$date]['bets_number']   = $this->get_money_2($bets_number);  //投注人数
                    $arr[$date]['bets_total']   =  $this->get_money_2($bets_total);  //投注金额
                    $arr[$date]['pay_total'] = $this->get_money_2($pay_total) ;  //平台支出金额
                    $arr[$date]['user_total_loss'] = $this->get_money_2($user_total_loss);  //用户盈亏
                    $arr[$date]['platform_total_lose'] = $this->get_money_2($platform_total_lose) ;  //平台盈亏
//                if (isset($data[0])){
//                    $arr[$date]['date']         = $date;
//                    $arr[$date]['game_name']   = $data[0]['game_name'];  //游戏
//                    $arr[$date]['bets_number']   = $data[0]['bets_number'];  //投注人数
//                    $arr[$date]['bets_total']   = $data[0]['bets_total'];  //投注金额
//                    $arr[$date]['pay_total']   = $data[0]['pay_total'];  //平台支出金额
//                    $arr[$date]['user_total_loss'] = $data[0]['user_total_loss'];  //用户盈亏
//                    $arr[$date]['platform_total_lose'] = $data[0]['platform_total_lose'];  //平台盈亏
//                }else{
//                    $arr[$date]['date']         = $date;
//                    $arr[$date]['game_name']   = '';  //游戏名称
//                    $arr[$date]['bets_number']   = 0;  //投注人数
//                    $arr[$date]['bets_total']   = 0;  //投注金额
//                    $arr[$date]['pay_total']   = 0;  //平台支出金额
//                    $arr[$date]['user_total_loss'] = 0;  //用户盈亏
//                    $arr[$date]['platform_total_lose'] = 0;  //平台盈亏
//                }

            }

            foreach($arr as  $value){
                if(isset($arr[lang('总计')])){
                    $arr[lang('总计')]['date']             = lang('总计');
                    $arr[lang('总计')]['game_name']      = '';
                    $arr[lang('总计')]['bets_number']      = '';
                    $arr[lang('总计')]['game_code']      = '';
                    $arr[lang('总计')]['bets_total']      += $value['bets_total'];
                    $arr[lang('总计')]['pay_total']      += $value['pay_total'];
                    $arr[lang('总计')]['user_total_loss']    += $value['user_total_loss'];
                    $arr[lang('总计')]['platform_total_lose']     += $value['platform_total_lose'];
                }else{
                    $arr[lang('总计')]['date']            = lang('总计');
                    $arr[lang('总计')]['game_name']      = '';
                    $arr[lang('总计')]['bets_number']      = '';
                    $arr[lang('总计')]['game_code']      = '';
                    $arr[lang('总计')]['bets_total']      = $value['bets_total'];
                    $arr[lang('总计')]['pay_total']      = $value['pay_total'];
                    $arr[lang('总计')]['user_total_loss']    = $value['user_total_loss'];
                    $arr[lang('总计')]['platform_total_lose']     = $value['platform_total_lose'];
                }
                $arr[lang('总计')]['bets_total'] = $this->get_money_2($arr[lang('总计')]['bets_total']);
                $arr[lang('总计')]['pay_total'] = $this->get_money_2($arr[lang('总计')]['pay_total']);
                $arr[lang('总计')]['user_total_loss'] = $this->get_money_2($arr[lang('总计')]['user_total_loss']);
                $arr[lang('总计')]['platform_total_lose'] = $this->get_money_2($arr[lang('总计')]['platform_total_lose']);

            }
            $arr = [0];
            $type = array_diff(config::get('custom.three_game'),$arr);
            $group = config::get('custom.three_game_group');
            $data = [];
            foreach($type as $k => $v){
                $data[$k] = $group[$k];
            }
            $rsps['data'] = $arr;
            $rsps['code']=1;
            $rsps['type'] = $type;
            $rsps['day'] = date("Y-m-d", time());
            return Respons::run($rsps);
        }
    }

    public function get_money_2($num){
        return floor($num * 100) /100; ;
    }
}