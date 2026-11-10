<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\MusicModel;
use think\facade\Db;
use think\Request;
class Music
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $musicModel =new MusicModel();
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["name","like","%".$param['name']."%"];
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["status","=",$param['status']];
        }
        $rsps['data']=$musicModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $res = MusicModel::insertGetId($info);
            $this->clearCache();
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new MusicModel(),lang('音乐添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $musicModel = new MusicModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $musicModel->where('id',$info['id'])->find();
            $res=$musicModel->update($info);
            $this->clearCache();
            return Respons::run($res?1:0,'','',$data,$info,$musicModel,lang('音乐修改'));
        }
        if (isset($param['find'])) {
            $res=$musicModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $musicModel =new MusicModel();
            $res=$musicModel->where("id","in",$id)->delete();
            $this->clearCache();
            return Respons::run($res?1:0,'','',$param,'',$musicModel,lang('音乐删除'));
        }
    }

    //清除缓存
    private function clearCache(){
        $apiRedis = new ApiRedis();
        $apiRedis->delete('music_list_v1');
        //清除缓存后重新生成缓存
        (new \app\api\controller\Music())->index();
    }
}