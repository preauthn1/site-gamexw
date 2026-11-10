<?php
namespace app\manage\controller;
use app\common\model\UserLevelModel;
use app\common\model\UserLevelDownModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\Request;
use app\manage\factory\UserlevelFactory;
class UserLevel
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

	//列表
    public function list(Request $request){ 
        $rsps = (new UserlevelFactory($request->token->uid))->list();
        return $rsps;
    }
    
    
    //添加
    public function add(Request $request){
    	$param = $request->param();
        $stratum = UserLevelModel::order('id desc')->value('id');
        if(empty($stratum)){
            $stratum = 0;
        }else{
            $stratum = intval($stratum);
        }
        $info = $param['info'];
        $res = UserLevelModel::insertGetId($info);
        $info['id'] = $res;

        //添加保级条件
        $info_down = ['id' => $info['id'], 'level_id' => $info['id'],'recharge_amount' => 0.00, 'water' => 0.00];
        $res_down = UserLevelDownModel::insertGetId($info_down);

        UserLevelModel::getVipGameWaterAndBackRate(true);
        return Respons::run($res?1:0,'','','',$info,new UserLevelModel(),lang('会员等级添加'));
     
        // $level_list = UserLevelModel::order('id asc')->field('id, level_name')->select();
        // $vip_upgrade = Utils::sysInfo('vip_upgrade');
        // View::assign("vip_upgrade",$vip_upgrade);
        // View::assign("stratum", $stratum);
        // View::assign("list", $level_list);
        // return View::fetch('user_level/add');
    }
    
    //编辑
    public function edit(Request $request){
    	$param = $request->param();
        $info=$param['info'];
        try {
            validate(\app\common\validate\UserLevelValidate::class)->scene('edit')->check($info);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            return Respons::run(0,[],$e->getError());
        }
        $data = UserLevelModel::where('id',$info['id'])->find();
        $UserLevelModel =new UserLevelModel();
        $res=$UserLevelModel->update($info);
        UserLevelModel::getVipGameWaterAndBackRate(true);
        return Respons::run($res?1:0,'','',$data,$info,$UserLevelModel,lang('会员等级编辑'));

        // $vip_upgrade = Utils::sysInfo('vip_upgrade');
        // $level_list = UserLevelModel::order('id asc')->field('id, level_name')->select();
        // View::assign("vip_upgrade",$vip_upgrade);
    	// View::assign("id",$param['id']);
        // View::assign("list", $level_list);
        // return View::fetch('user_level/edit');
    }

    //删除
    public function edit_detail(Request $request){
    	$param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
    	$UserLevelModel =new UserLevelModel();
        $res=$UserLevelModel->where("id",$param['id'])->find();
        $res['start_time']=date("Y-m-d H:i:s",$res['start_time']);
        $res['end_time']=date("Y-m-d H:i:s",$res['end_time']);
        return Respons::run(1,$res);
    }

    //删除
    public function delete(Request $request){
    	$param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
        $UserLevelModel =new UserLevelModel();
        $res=$UserLevelModel->where("id","in",$param['id'])->delete();

        //删除保级条件
        $UserLevelDownModel =new UserLevelDownModel();
        $res=$UserLevelDownModel->where("id","in",$param['id'])->delete();

        UserLevelModel::getVipGameWaterAndBackRate(true);
        return Respons::run($res?1:0);
    }
}