<?php
namespace app\manage\controller;
use app\common\model\RuleModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\manage\factory\RuleFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
class Rule
{	
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $rsps = (new RuleFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
        $type = config('custom.rule_type');
    	if (isset($param['add'])) {
            $info=$param['info'];
            $res = RuleModel::insertGetId($info);
            $info['type'] = isset($type[$info['type']]) ? $type[$info['type']]:'';
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new RuleModel(),lang('规则添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $types = config('custom.rule_type');
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = RuleModel::where('id',$info['id'])->field('id,type,lang')->find();
            $data['type'] = isset($types[$data['type']]) ? $types[$data['type']]:'';
            $res = RuleModel::update($info);
            $info['type'] = isset($types[$info['type']]) ? $types[$info['type']]:'';
            return Respons::run($res?1:0,'','',$data,$info,new RuleModel(),lang('规则修改'));
        }
        if (isset($param['find'])) {
            $res = RuleModel::where("id",$param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            $res['types'] = $types;
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $res = RuleModel::where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',new RuleModel(),lang('规则删除'));
        }
    }
}