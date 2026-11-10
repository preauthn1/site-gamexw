<?php
namespace app\promote\controller;
use app\common\model\AdminModel;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\common\model\ActionApplyModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\Utils;
use think\facade\Session;
use app\common\lib\Authenticator;
use app\common\lib\Util;
use app\common\lib\Respons;
use think\facade\View;
use think\facade\Config;
use think\facade\Request;
use think\facade\Db;
use think\facade\Queue;
use app\common\model\LangModel;
use app\api\redis\ApiRedis;

class Index
{
    public function index(){
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
            return Respons::run($rsps);
        }
        $ApiRedis=new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        View::assign('socketKey',Config::get("custom.socketKey"));
        View::assign('lang_list', LangModel::column('lang', 'title'));
        View::assign('WebSocket',env('websocket_url', ''));
        View::assign('web_name',env('web_name', ''));
        View::assign('is_online_pay',env('is_online_pay', 0));
        View::assign('is_offline_pay',env('is_offline_pay', 0));
        View::assign('is_shaba_open',env('is_shaba_open', 0));
        View::assign('is_third_open',env('is_third_open', 0));
	    View::assign('is_turntable_open',env('is_turntable_open', 0));
        View::assign('is_flyer_open',env('is_flyer_open', 0));
        return View::fetch('index/index');
    }
    
    //谷歌绑定
    public function bind_google(){
        $param=Request::param();
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
        $secret = $Authenticator->createSecret();
        $qrCodeUrl = $Authenticator->getQRCodeGoogleUrl(env("web_name",''),$secret);
        Session::set("google_secret",$secret);//设置新的会话
        View::assign('qrCodeUrl',$qrCodeUrl);
        return View::fetch('index/bind_google');
    }
    
    //工作台
    public function work(){
        $param=Request::param();
        $sys = Utils::sysInfo();
        $tournament_notes = empty($sys['tournament_notes']) ? []:unserialize($sys['tournament_notes']);
        View::assign('tournament_notes',$tournament_notes);
        View::assign('socketKey',Config::get("custom.socketKey"));
        View::assign('WebSocket',env('websocket_url', ''));
    	return View::fetch('index/welcome');
    }
    
    public function getData(){
        $ApiRedis = new ApiRedis();
        $recharge_count = $ApiRedis->get('recharge_count');
        if(empty($recharge_count) && $recharge_count != 0){
            $recharge_count = UserOfflineModel::where("status",1)->count();
            $ApiRedis->set('recharge_count', $recharge_count, 60);
        }
        
        $user_online_count = $ApiRedis->get('user_online_count');
        if(empty($user_online_count) && $user_online_count != 0){
           $user_online_count = getOnlineUids();
           $ApiRedis->set('user_online_count', $user_online_count, 10);
        }
        
        $user_action_count = $ApiRedis->get('user_action_count');
        if(empty($user_action_count) && $user_action_count != 0){
           $user_action_count = ActionApplyModel::where("ps_status",0)->count();
           $ApiRedis->set('user_action_count', $user_action_count, 30);
        }
        
        $user_withdrawal_count = $ApiRedis->get('user_withdrawal_count');
        if(empty($user_withdrawal_count) && $user_withdrawal_count != 0){
           $user_withdrawal_count = UserWithdrawalModel::where("status",1)->count();
           $ApiRedis->set('user_withdrawal_count', $user_withdrawal_count, 10);
        }
        
        $user_agent_count = $ApiRedis->get('user_agent_count');
        if(empty($user_agent_count) && $user_agent_count != 0){
           $user_agent_count = UserAgentModel::where("status",1)->count();
           $ApiRedis->set('user_agent_count', $user_agent_count, 120);
        }
        
        $rdata['agent']= $user_agent_count;
        $rdata['action']= $user_action_count;
        $rdata['offlin'] = $recharge_count;
        $rdata['withdrawal'] = $user_withdrawal_count;
        $rdata['user_ol'] = $user_online_count;
        return $rdata;
    }




}