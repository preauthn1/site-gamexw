<?php


namespace app\common\FastGame;

use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, GamePayLogs, Games, Users, UserThirds};
use app\common\model\UserModel;
use think\facade\Db;

class Notify_wm extends Notify
{
    private $thirds_name = '';

    private $game_code = 'live';

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj,$cfg)
    {
        return false;
    }

    /**
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(int $code = 0, string $msg = '', array $data = []): array
    {
        $arr['errorCode'] = $code;
        $arr['errorMessage'] = $msg;
        $arr['result'] = $data;
        return $arr;
    }

    private function PushLog($action, $msg, $data){
        Utils::L($this->thirds_name, [$action. $msg, $data]);
    }

    public function live($datas, $trdobj, $cfg){
        $param = $datas['param'];
        $action = $param['cmd'];
        $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/log";
        try {
            $signflag = $trdobj->CheckSign($cfg, $datas);
            $info = [];
            $u_id = $param['user'];
            $utuser = UserThirds::where(['ut_trdname' => $u_id])->find();
            $info['uid'] = $utuser->ut_uid;
            $info['rate'] = $cfg['trdcfg']["tc_exchangerate"];//汇率
            $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
            if (isset($param['gtype'])) $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->get_gamecode($param['gtype']), 'tag', 'g_id');
            if($signflag){
                $res = $this->$action($param, $trdobj, $cfg, $info);
            } else {
                $this->PushLog($action, '签名错误', $datas);
                $res['code'] = 1;
            }
            $this->PushLog($action, '执行完成', $res);
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($action, '过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $res['code'] = 1;
        }

        return $res;
    }

    //查询余额
    private function CallBalance($data, $trdobj, $cfg, $info): array
    {
        $msg = '查询余额错误';
        $arr = [];
        try {
            $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
            $balance = Third::transExchangerate($balance,1);
            $arr['money'] = $balance;
            $arr['user'] = $data['user'];
            $arr['responseDate'] = date("Y-m-d H:i:s");
            $code = 0;
            $msg = '';
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['cmd'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //加扣点
    private function PointInout($data, $trdobj, $cfg, $info): array
    {
        $msg = '加扣点错误';
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $gpl_sn = $data['dealid'];//交易单号
            $money = $data['money']; //操作金额
            $code = $data['code']; //加扣点类型
            $category = $data['category']; //注单类型 下注单  小费单
            $gtype = $data['gtype']; //注单类型 下注单  小费单
            $gameno = explode('_', $data['gameno']); //游戏项目_期数_局号
            $aflag = Redis::get("pb_PointInout_" . $info['gid'] . "_" . $gpl_sn);
            if(!$aflag) {
                Redis::setex("pb_PointInout_" . $info['gid'] . "_" . $gpl_sn, 1, 60);//60秒内不接受重复得action
                //0是电子游戏输了平台方会发送0元结算
                //code: 1:加点=派彩
                //code: 2:扣点=下注
                //code: 3:重新加点=重新兑奖后输变成赢  那就是加扣点的金额都是以money为准。重对的时候 注单编号是betId
                // code: 4:重新扣点=重新兑奖后赢变成输  那就是加扣点的金额都是以money为准。重对的时候 注单编号是betId
                //code: 5:重新派彩=因网路原因或其他原因无法正常发送时，我方手动发送重新添加额度或扣除额度（重新发送上次回应失败的请求）
                //可能存在重新加点/扣点的情况 无法用订单号唯一来判断订单。重新加点/扣点 订单号一致 状态改变 金额改变 订单号一致会重新写入
                /* @GamePayLogs $GamePayLogsModel */
                $GamePayLogsModel = app()->make(GamePayLogs::class);
                if($code != 0){
                    $amount = Third::transExchangerate($money,0);
                    $pay_log = [
                        'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                        'gpl_gid' => $info['gid'],
                        'gpl_uid' => $info['uid'],
//                        'gpl_type' => 1, //1加 2减
                        'gpl_price' => $amount,
                        'gpl_sn' => $gpl_sn,
                        'gpl_info' => json_encode($data),
//                        'gpl_text' => $text,
                        'gpl_state' => 1,
                        'gpl_create_time' => date("Y-m-d H:i:s"),
                    ];
                    if($amount > 0){ //1加点 3重新加点 5重新派彩
                        //下注单
                        $text = '';
                        if($code == 1){
                            $text = 'Add';
//                            $text = '加点';
                        }else if($code == 3){
                            $text = 'Again Add';
//                            $text = '重新加点';
                        }else if($code == 5){
                            $text = 'Again Send';
//                            $text = '重新派彩';
                        }
//                        $mx = $cfg['trdcfg']['tc_name'] . "_{$text} _{$this->get_game_code($gtype)}_"."[ {$gameno[1]}_{$gameno[2]} ]";
                        $msg = UserModel::betRemarks($gpl_sn, $cfg['trdcfg']['tc_name'], $info['gid'], $text);
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id']);
                            $pay_log['gpl_type'] = 1;
                            $pay_log['gpl_text'] = $text;
                            $GamePayLogsModel->save($pay_log);
                            $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                            $balance = Third::transExchangerate($balance,1);
                            $res = [
                                'money' => $money,
                                'responseDate' => date("Y-m-d H:i:s"),
                                'dealid' => $gpl_sn,
                                'cash' => $balance,
                            ];
                            $this->PushLog($data['cmd'], "{$text}成功", $data);
                            $code = 0;
                            $msg = '';
                            Db::commit();

                    }else{ //2扣点 4重新扣点 5重新派彩
                        $amount = abs($amount); //绝对值转换
                        if($category == 1){
                            //下注单
                            $text = '';
                            if($code == 2){
                                $text = 'Bet';
//                                $text = '下注扣点';
                            }else if($code == 4){
//                                $text = '重新扣点';
                                $text = 'Again Bet';
                            }else if($code == 5){
                                $text = 'Again Send';
//                                $text = '重新派彩';
                            }
                        }else{
                            //小费单
                            $text = '_Give a tip';
//                            $text = '_打赏小费';
//                            $mx = $cfg['trdcfg']['tc_name'] . $text;
                        }
//                        $mx = $cfg['trdcfg']['tc_name'] . "_{$text} _{$this->get_game_code($gtype)}_"."[{$gameno[1]}_{$gameno[2]}]";
                        $msg = UserModel::betRemarks($gpl_sn, $cfg['trdcfg']['tc_name'], $info['gid'], $text);

                        if ($amount < $uinfo[Meta::UFIELD['ubalance']]) {
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '-', 1, $msg);
                                $pay_log['gpl_type'] = 2;
                                $pay_log['gpl_text'] = $text;
                                $GamePayLogsModel->save($pay_log);
                                $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                                $balance = Third::transExchangerate($balance,1);
                                $res = [
                                    'money' => $money,
                                    'responseDate' => date("Y-m-d H:i:s"),
                                    'dealid' => $gpl_sn,
                                    'cash' => $balance,
                                ];
                                $this->PushLog($data['cmd'], "{$text}成功", $data);
                                $code = 0;
                                $msg = '';
                                Db::commit();
                        }else{
                            $this->PushLog($data['cmd'], "{$text}失败,余额不足", $data);
                            $code = 10805;
                            $msg = '转账失败，该账号余额不足';
                            Db::rollback();
                        }
                    }
                }else{
                    //没有开启电子游戏 不会发送code = 0的通知
                    $code = 0;
                    Db::commit();
                }
            }else{
                $code = 1;
                $this->PushLog($data['cmd'], '订单重复请求', $data);
                Db::rollback();
            }

        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['cmd'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $res);
    }

    //回滚
    private function TimeoutBetReturn($data, $trdobj, $cfg, $info): array
    {
        $msg = '回滚错误';
        $res = [];
        Db::startTrans();
        try {
            $uinfo = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->lock(true)->field(Meta::UFIELD['ubalance'] . "," . Meta::UFIELD['uid'])->find();
            $gpl_sn = $data['dealid'];//交易单号
            $money = $data['money']; //操作金额
            $code = $data['code']; //加扣点类型
            $category = $data['category']; //注单类型 下注单  小费单
            $gtype = $data['gtype']; //注单类型 下注单  小费单
            $gameno = explode('_', $data['gameno']); //游戏项目_期数_局号
            $info['pay'] = $data;
            $aflag = Redis::get("pb_TimeoutBetReturn_" . $info['gid'] . "_" . $gpl_sn);
            if(!$aflag) {
                Redis::setex("pb_TimeoutBetReturn_" . $info['gid'] . "_" . $gpl_sn, 1, 60);//60秒内不接受重复得action
                /* @GamePayLogs $GamePayLogsModel */
                $GamePayLogsModel = app()->make(GamePayLogs::class);
                $GamePayLogs = $GamePayLogsModel->where(['gpl_sn' => $gpl_sn])->find();
                $amount = Third::transExchangerate($money,0);
                $amount = abs($amount); //绝对值转换
                if(isset($GamePayLogs['gpl_id'])) {
                    if($GamePayLogs['gpl_state'] == 1){
                        if($code == 1){
                            //如果加点已经存在了 再次发送的加点回滚 就拒绝
                            $code = 1;
                            $this->PushLog($data['cmd'], '加点拒绝回滚', $data);
                            Db::rollback();
                        }else{
                            //code 为2  扣点存在的时候 才能回滚 增加点数
                            if($category == 1){ //下注单
                                $text = 'Bet roll back';
//                                $text = '下注回滚';
                            }else{ //小费单
                                $text = 'Tip roll back';
//                                $text = '打赏小费回滚';
//                                $mx = $cfg['trdcfg']['tc_name'] . "_{$text}";
                            }
//                            $mx = $cfg['trdcfg']['tc_name'] . "_{$text} _{$this->get_game_code($gtype)}_"."[ {$gameno[1]}_{$gameno[2]} ]";
                            $msg = UserModel::betRemarks($gpl_sn, $cfg['trdcfg']['tc_name'], $info['gid']??0, $text);
                            $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id']);
                            $upd['gpl_update_time'] = date("Y-m-d H:i:s");
                            $upd['gpl_state'] = 2;
                            $upd['gpl_info'] = json_encode($data);
                            $GamePayLogs->save($upd);
                            $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                            $balance = Third::transExchangerate($balance,1);
                            $res = [
                                'money' => $money,
                                'responseDate' => date("Y-m-d H:i:s"),
                                'dealid' => $gpl_sn,
                                'cash' => $balance,
                            ];
                            $this->PushLog($data['cmd'], "{$text}成功", $data);
                            $code = 0;
                            $msg = '';
                            Db::commit();
                        }
                    }else{
                        //已经是无效注单了
                        $code = 1;
                        $this->PushLog($data['cmd'], '订单已处理', $data);
                        Db::rollback();
                    }
                }else{
                    if($code == 2){
                        //如果扣点不存在 发送了回滚请求就拒绝
                        $code = 1;
                        $this->PushLog($data['cmd'], '扣点拒绝回滚', $data);
                        Db::rollback();
                    }else{
                        //code 为1  派彩不存在的时候 才能回滚 就是直接加点
                        $text = 'Again Send';
//                        $text = '重新派彩';
//                        $mx = $cfg['trdcfg']['tc_name'] . "_{$text} _{$this->get_game_code($gtype)}_"."[ {$gameno[1]}_{$gameno[2]} ]";
                        $msg = UserModel::betRemarks($gpl_sn, $cfg['trdcfg']['tc_name'], $info['gid']??0, $text);
                        $rlt = User::accountChange($uinfo[Meta::UFIELD['uid']], $amount, '+', 3, $msg, true, $cfg['trdcfg']['tc_id']);
                        $pay_log = [
                            'gpl_tcid' => $cfg['trdcfg']['tc_id'],
                            'gpl_gid' => $info['gid'],
                            'gpl_uid' => $info['uid'],
                            'gpl_type' => 2, //1加 2减
                            'gpl_price' => $amount,
                            'gpl_sn' => $gpl_sn,
                            'gpl_info' => json_encode($data),
                            'gpl_text' => $text,
                            'gpl_state' => 2, //已经是回滚的了。
                            'gpl_create_time' => date("Y-m-d H:i:s"),
                        ];
                        $GamePayLogsModel->save($pay_log);
                        $balance = UserModel::where(Meta::UFIELD['uid'], $info['uid'])->value(Meta::UFIELD['ubalance']);
                        $balance = Third::transExchangerate($balance,1);
                        $res = [
                            'money' => $money,
                            'responseDate' => date("Y-m-d H:i:s"),
                            'dealid' => $gpl_sn,
                            'cash' => $balance,
                        ];
                        $this->PushLog($data['cmd'], "{$text}成功", $data);
                        $code = 0;
                        $msg = '';
                        Db::commit();
                    }
                }
            }else{
                $code = 1;
                $this->PushLog($data['cmd'], '订单重复请求', $data);
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $this->thirds_name = $cfg['trdcfg']['tc_id'] . "/error";
            $this->PushLog($data['cmd'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
            $code = 1;
            Db::rollback();
        }
        return $this->ReturnData($code, $msg, $res);
    }

    protected function get_game_code($key): string
    {
//        101:百家乐,102:龙虎,103:轮盘,104:骰宝,105:牛牛,107:番摊,108:色碟,110:鱼虾蟹,128:安達巴哈
        $arr = [
            '101' => '百家乐',
            '102' => '龙虎',
            '103' => '轮盘',
            '104' => '骰宝',
            '105' => '牛牛',
            '107' => '番摊',
            '108' => '色碟',
            '110' => '鱼虾蟹',
            '128' => '安達巴哈',
        ];
        return $arr[$key];
    }

    protected function get_gamecode($key): string
    {
//        101:百家乐,102:龙虎,103:轮盘,104:骰宝,105:牛牛,107:番摊,108:色碟,110:鱼虾蟹,128:安達巴哈
        $arr = [
            '101' => 'onlybac',
            '102' => 'onlydgtg',
            '103' => 'onlyrou',
            '104' => 'onlysicbo',
            '105' => 'onlyniuniu',
            '107' => 'onlyfantan',
            '108' => 'onlysedie',
            '110' => 'onlyfishshrimpcrab',
            '128' => 'onlyandarbahar',
        ];
        return $arr[$key];
    }
}
