<?php
namespace app\manage\controller;
use app\common\model\AgentSettingModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\manage\factory\AgentSettingFactory;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class AgentSetting
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new AgentSettingFactory($request->token->uid))->list();
        return $rsps;
    }
    
    //添加
    public function add(){
    	$param=Request::param();
    	if (isset($param['add'])) {
            $info = $param['info'];
            if( isset($info['start_time']) && $info['start_time'] ){
                $info['start_time'] = strtotime($info['start_time']);
            }
            $res = AgentSettingModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new AgentSettingModel(),lang('微商设置添加'));
        }
        $stratum = AgentSettingModel::order('stratum desc')->value('stratum');
        if(empty($stratum)){
            $stratum = 1;
        }else{
            $stratum = intval($stratum) + 1;
        }
        View::assign("stratum", $stratum);
        return View::fetch('agent_setting/add');
    }
    
    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            if( isset($info['start_time']) && $info['start_time'] ){
                $info['start_time'] = strtotime($info['start_time']);
            }
             $data = AgentSettingModel::where('id',$info['id'])->find();
            $res = AgentSettingModel::update($info);
            return Respons::run($res?1:0,'','',$data,$info,new AgentSettingModel(),lang('微商设置修改'));
        }
        if (isset($param['find'])) {
            $res = AgentSettingModel::where("id",$param['id'])->find();
            if( $res && $res['start_time'] ){
                $res['start_time'] = date('Y-m-d H:i:s', $res['start_time']);
            }
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('agent_setting/edit');
    }
    
    //删除
    public function delete(){
    	$param=Request::param();
    	if (isset($param['delete'])) {
            $id = $param['id'];
            $res = AgentSettingModel::where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',new AgentSettingModel(),lang('微商设置删除'));

        }
    }
}