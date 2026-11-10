<?php
namespace app\manage\controller;
use app\common\model\JackpotContentModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\Utils;
use think\Request;
class JackpotContent
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $jackpotContentModel =new JackpotContentModel();

        if(isset($param['j_id']) && $param['j_id'] !== ''){
            $where[]=["j_id", "=", $param['j_id']];
        }

        $rsps['data']=$jackpotContentModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = time();
            $info['update_time'] = time();
            $res = JackpotContentModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new JackpotContentModel(),lang('jackpot排行榜内容添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $jackpotContentModel=new JackpotContentModel();
            $data = $jackpotContentModel->where('id',$info['id'])->find();
            $info['update_time'] = time();
            $res=$jackpotContentModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$jackpotContentModel,lang('jackpot排行榜内容修改'));
        }
        if (isset($param['find'])) {
            $jackpotContentModel =new JackpotContentModel();
            $res=$jackpotContentModel->where("id",$param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $jackpotContentModel =new JackpotContentModel();
            $res=$jackpotContentModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$jackpotContentModel,lang('jackpot排行榜内容删除'));
        }
    }
}