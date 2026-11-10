<?php
namespace app\workermans\admin;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\common\model\ActionApplyModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use think\facade\Db;

class FengKong
{	
    //统计
    public function index($data=[]){	
        $UserModel=new UserModel();
        $UserAgentModel=new UserAgentModel();
        $ActionApplyModel=new ActionApplyModel();
        $UserOfflineModel=new UserOfflineModel();
        $UserWithdrawalModel=new UserWithdrawalModel();
        $rdata['agent']=$UserAgentModel->where("status",1)->count();
        $rdata['action']=$ActionApplyModel->where("ps_status",0)->count();
        $rdata['offlin']=$UserOfflineModel->where("status",1)->count();
        $rdata['withdrawal']=$UserWithdrawalModel->where("status",1)->count();
        $rdata['user_ol']=0;
        return $rdata;
    }

}