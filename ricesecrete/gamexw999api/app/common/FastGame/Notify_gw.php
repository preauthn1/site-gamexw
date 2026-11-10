<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use think\facade\Db;

class Notify_gw extends Notify
{
    private $thirds_name = '';

    private $game_code = 'gw_game';

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj, $cfg)
    {
        return false;
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(string $code = '999', string $msg = '', array $data = []): array
    {
        $arr['message'] = $msg;
        $arr['result'] = $code;
        $arr['data'] = $data;
        // $arr = array_filter($arr);
        // Utils::L($this->thirds_name, [$arr]);
        return $arr; //清除空值
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action . $msg, $data]);
    }

    public function lottery($datas, $trdobj, $cfg)
    {
        $param = $datas['param'];
        try {
            $action_type = ['login', 'recharge', 'withdraw'];
            $action = $param['action'];
            //允许请求的方法
            if(in_array($action, $action_type)){
                $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/log";
                $param['action'] = $action;
                $signflag = $trdobj->CheckSign($cfg, $param);
                if ($signflag) {
                    $info['gid'] = Game::getGamesInfoBy($cfg['trdcfg']['tc_id'], $this->game_code, 'tag', 'g_id');
                    $res = $this->$action($param, $trdobj, $cfg, $info);
                } else {
                    $this->PushLog($action, '签名错误', $datas);
                    $res['code'] = '991';
                }
                $this->PushLog($action, '执行完成', $res);
            }else{
                $this->PushLog($action, '参数不合法', $datas);
                $res['code'] = '992';
            }
        } catch (\Throwable $e) {
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($param['s'], '主过程错误', [$e->getMessage(), $e->getTraceAsString()]);
            $res['code'] = '993';
        }

        return $res;
    }

    //登录验证
    private function login($data, $trdobj, $cfg, $info): array
    {
        $arr = [];
        $msg = '';
        try {
            $info = [];
            $utuser = UserThirds::where(['ut_token' => $data['token']])->find();
            if($utuser->ut_uid){
                $info['u_ip'] = UserModel::where(['id' => $utuser->ut_uid])->value('end_login_ip');
                $info['trdid'] = $utuser->ut_trdid;
                $info['currency'] = $cfg['trdcfg']['tc_currency'];//货币
                $arr['thirdpartyUid'] = $info['trdid'];
                $arr['nickname'] = $info['trdid'];
                $arr['currency'] = $this->get_curreny($info['currency']);
                $arr['userLang'] = $this->get_lang($info['currency']);
                $arr['country'] =$this->get_country($info['currency']);
                $arr['lastIp'] = $info['u_ip'];
                $arr['isTester'] = 0; //测试用户  对接完要改成0
//                $msg = 'success';
                $code = 0;
                // $utuser->ut_token = '';
                // $utuser->save();
            }else{
                $this->PushLog($data['action'], '用户不存在', $data);
                $code = '990';
            }

        } catch (\Throwable $e) {
            $msg = 'invalid parameter';
            $code = '994';
            $this->PushLog($data['action'], $msg, $e->getMessage());
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //充值验证
    private function recharge($data, $trdobj, $cfg, $info): array
    {
        $arr = [];
        try {
            $key = "gw_recharge_" . $cfg['trdcfg']['tc_id'] . "_" . $data['token'];
            $aflag = Redis::get($key);
            if($aflag) {
                Redis::del($key);
                $msg = 'success';
                $code = 0;
            }else{
//                Redis::setex($key, $data['token']);
                $msg = 'token fail';
                $code = 995;
            }

        } catch (\Throwable $e) {
            $msg = 'invalid parameter';
            $code = '994';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //提现验证
    private function withdraw($data, $trdobj, $cfg, $info): array
    {
        $arr = [];
        try {
            $key = "gw_withdraw_" . $cfg['trdcfg']['tc_id'] . "_" . $data['token'];
            $aflag = Redis::get($key);
            if($aflag) {
                Redis::del($key);
                $msg = 'success';
                $code = 0;
            }else{
//                Redis::setex($key, $data['token']);
                $msg = 'token fail';
                $code = 995;
            }

        } catch (\Throwable $e) {
            $msg = 'invalid parameter';
            $code = '994';
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog($data['action'], $msg, [$e->getMessage(), $e->getTraceAsString()]);
        }
        return $this->ReturnData($code, $msg, $arr);
    }

    //获取对应币种
    private function get_curreny($key): string
    {
        $arr = ['CNY', 'USD', 'MYR', 'THB','JPY', 'KRW', 'VND', 'IDR', 'INR'];
        return $arr[$key] ?? 'VND';
    }

    //获取对应语言
    private function get_lang($key){
        $arr = [
            'CNY' => 'cn',
            'USD' => 'us',
            'THB' => 'th',
            'VND' => 'vn',
            'JPY' => 'jp'
        ];
        return $arr[$key] ?? $arr['VND'];
    }

    //获取对应国别
    private function get_country($key){
        $arr = [
            'CNY' => 'CN',
            'USD' => 'US',
            'THB' => 'TH',
            'VND' => 'VN',
            'JPY' => 'JP'
        ];
        return $arr[$key] ?? $arr['VND'];
    }

    private function getUserBalance($uid){
        $balance = UserModel::getBalanceForDoubleWalletByGid($uid);
        $balance = Third::transExchangerate($balance,1);
        return $balance;
    }
}
