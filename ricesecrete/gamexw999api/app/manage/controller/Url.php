<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\UrlModel;
use app\common\Utils;
use think\facade\Db;
use think\facade\View;
use think\Request;
class Url
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $urlModel =new UrlModel();
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["name","like","%".$param['name']."%"];
        }
        $rsps['data']=$urlModel
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
            $this->clearMenuCache($info);
            Db::startTrans();
            $res = UrlModel::insertGetId($info);
            //判断不能超过两个
            if( UrlModel::where('is_menu', '=', 1)->count() > 2 ){
                Db::rollback();
                return Respons::run(0, [], '自定义菜单不能超过两个');
            }
            Db::commit();
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new UrlModel(),lang('链接添加'));
        }
    }

    //清除接口菜单缓存
    public function clearMenuCache($info){
//        if( isset($info['is_menu']) && $info['is_menu'] ){
            $apiRedis = new ApiRedis();
            $key = 'custom_menu_list';
            //设置一天缓存
            $apiRedis->delete($key);
//        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $urlModel = new UrlModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $urlModel->where('id',$info['id'])->find();
            $this->clearMenuCache($info);
            Db::startTrans();
            $res=$urlModel->update($info);
            //判断不能超过两个
            $menuArr = UrlModel::where('is_menu', '=', 1)->column('id');
            if( count($menuArr) > 2 ){
                Db::rollback();
                return Respons::run(0, [], '自定义菜单不能超过两个');
            }
            Db::commit();
            return Respons::run($res?1:0,'','',$data,$info,$urlModel,lang('链接修改'));
        }
        if (isset($param['find'])) {
            $res=$urlModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $urlModel =new UrlModel();
            $res=$urlModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$urlModel,lang('链接删除'));
        }
    }
}