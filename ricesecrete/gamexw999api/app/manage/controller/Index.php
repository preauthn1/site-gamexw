<?php
namespace app\manage\controller;

use app\common\model\{AdminModel,
    UserModel,
    UserAgentModel,
    ActionApplyModel,
    UserOfflineModel,
    UserVerifyModel,
    UserWithdrawalModel,
    LangModel};
use app\common\lib\{Authenticator, Respons};
use think\facade\{Session, Config};
use app\common\Utils;
use app\api\redis\ApiRedis;
use think\Request;

class Index extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    public function index(Request $request){
        
        $param=Request::param();
        if (isset($param['find'])) {
            $UserModel=new UserModel();
            $UserAgentModel=new UserAgentModel();
            $ActionApplyModel=new ActionApplyModel();
            $UserOfflineModel=new UserOfflineModel();
            $UserWithdrawalModel=new UserWithdrawalModel();
            $data['agent']=$UserAgentModel->where("status",1)->count();
            $data['action']=$ActionApplyModel->where("ps_status",0)->count();
            $data['offlin']=$UserOfflineModel->where("status",1)->count();
            $data['withdrawal']=$UserWithdrawalModel->where("status",1)->count();
            $data['user_ol']= count(getOnlineUids());

            $rsps['data']=$data;
            $rsps['code']=1;
            $ApiRedis = new ApiRedis();
            $sys = $ApiRedis->systemInfo();

            $defa_state = env('default_country', '');
            if(empty($defa_state)){
                $defa_state = LangModel::where(['is_default'=>1])->value('lang') ?? 'CN';
            }
            $date_format = Config::get("custom.date")[$defa_state] ?? 'Y-m-d';
            $time_format = Config::get("custom.time")[$defa_state] ?? 'H:i:s';
            $date_time = date("{$date_format} {$time_format}");
            $is_test = env('is_google', 0);
            $zh_name = Config::get("custom.country")[$defa_state] ?? '中国';

            $arr = [0];
            $type = array_diff(config::get('custom.three_game'),$arr);
            $therr_game_state = count($type);

            $rsps['date_time'] = $date_time;
            $rsps['is_test'] = $is_test;
            $rsps['zh_name'] = $zh_name;
            $rsps['socketKey'] = Config::get("custom.socketKey");
            $rsps['lang_list'] = LangModel::column('lang', 'title');
            $rsps['WebSocket'] = env('websocket_url', '');
            $rsps['web_name'] = env('web_name', '');
            $rsps['is_online_pay'] = env('is_online_pay', 0);
            $rsps['is_offline_pay'] = env('is_offline_pay', 0);
            $rsps['is_shaba_open'] = !!$therr_game_state;
            $rsps['is_third_open'] = env('is_third_open', 0);
            $rsps['is_turntable_open'] = env('is_turntable_open', 0);
            $rsps['is_flyer_open'] = env('is_flyer_open', 0);
            $rsps['adminMenu'] = Session::get('adminMenu');
            return Respons::run($rsps);
        }

    }
    
    //谷歌绑定
    public function bind_google(Request $request){
        $param = $request->param();
        $admin=Session::get("adminInfo");
        $Authenticator= new Authenticator();
        if (isset($param['bind'])) {
            $info=$param['info'];
            $google_secret = Session::get("google_secret");
            $checkResult = $Authenticator->verifyCode($google_secret,$info['code'],1);
            if ($checkResult) {
                $upd_admin['google_secret'] = $google_secret;
                $upd_admin['goodsle_bind']=1;//已绑定
                AdminModel::where("id",$admin['id'])->update($upd_admin);
                $admin['goodsle_bind'] = 1;//验证成功
                Session::set("adminInfo",$admin);//设置新的会话
                Session::delete("google_secret");
                return Respons::run(1,[],lang('验证成功'));
            } else {
                return Respons::run(0,[],lang('验证失败'));
            }            
        }
        if (isset($param['find'])) {
            $secret = $Authenticator->createSecret();
            $qrCodeUrl = $Authenticator->getQRCodeGoogleUrl(env("web_name",''),$secret);
            Session::set("google_secret",$secret);//设置新的会话
            return Respons::run(1,$qrCodeUrl);
        }
    }
    
    //工作台
    public function work(Request $request){
        $param = $request->param();
        $sys = Utils::sysInfo();
        $tournament_notes = empty($sys['tournament_notes']) ? []:unserialize($sys['tournament_notes']);
        $data['tournament_notes'] = $tournament_notes;
        $data['socketKey'] = Config::get("custom.socketKey");
        $data['WebSocket'] = env('websocket_url', '');
        return Respons::run(1,$data);
    }
    
    public function getData(Request $request){
        $ApiRedis = new ApiRedis();
        // 待审核线下充值
        $recharge_count = $ApiRedis->get('recharge_count')?:0;
        if(!$ApiRedis->has('recharge_count')){
            $recharge_count = UserOfflineModel::where(["status" =>1, "type" => 0])->count();
            $ApiRedis->set('recharge_count', $recharge_count, 30);
        }

        // 待审核活动申请
        $user_action_count = $ApiRedis->get('user_action_count')?:0;
        if(!$ApiRedis->has('user_action_count')){
           $user_action_count = ActionApplyModel::where("ps_status=0 and uid>0")->count();
           $ApiRedis->set('user_action_count', $user_action_count, 30);
        }

        // 待审核提现
        $user_withdrawal_count = $ApiRedis->get('user_withdrawal_count')?:0;
        if(!$ApiRedis->has('user_withdrawal_count')){
           $user_withdrawal_count = UserWithdrawalModel::where("status",1)->count();
           $ApiRedis->set('user_withdrawal_count', $user_withdrawal_count, 30);
        }

        // 待审核实名认证
        $user_verify_count = $ApiRedis->get('user_verify_count')?:0;
        if(!$ApiRedis->has('user_verify_count')){
            $user_verify_count = UserVerifyModel::where("status",1)->count();
           $ApiRedis->set('user_verify_count', $user_verify_count, 30);
        }

        $ismsg = 0;
        $wmd5 = md5($user_withdrawal_count.$recharge_count.$user_action_count.$user_verify_count);
        $rwmd5 = $ApiRedis->get('user_withdrawal_count_md5_'.$this->user['id']);
        $ApiRedis->set('user_withdrawal_count_md5_'.$this->user['id'], $wmd5);
        if($wmd5 != $rwmd5){
            $ismsg = 1;
        }

        $rdata['ismsg'] = $ismsg;
        $rdata['action']= $user_action_count;
        $rdata['offlin'] = $recharge_count;
        $rdata['withdrawal'] = $user_withdrawal_count;
        $rdata['user_verify'] = $user_verify_count;
        return Respons::run(1,$rdata);
    }
}