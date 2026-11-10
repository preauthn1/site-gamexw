<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\AdsModel;
use app\common\model\BannerModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\Utils;
use app\manage\factory\BannerFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
class Banner extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function list(){
        $param = $this->request->param();
        $where = [];
        if (isset($param['status']) && $param['status'] != '' && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }
        if (isset($param['show_position']) && in_array($param['show_position'], [1, 2, 3])) {
            $where[] = ['show_position', '=', $param['show_position']];
        }
        if (isset($param['agent_id']) && $param['agent_id']!='') {
            $where[] = ['agent_id', 'FIND IN SET', $param['agent_id']];
        }

        [$data, $total] = $this->getDataList(new BannerModel(), $where, null, 'sort desc,id desc');
        foreach ($data as &$datum) {
            $datum['start_time'] = Util::timeToStr($datum['start_time']);
            $datum['end_time'] = Util::timeToStr($datum['end_time']);
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'where' => $where,
            'url' => Utils::getUrlList(),
            'langs' => Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1])
        ]);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $start_time = $info['start_time'];
            $end_time = $info['end_time'];
            $info['start_time']=strtotime($info['start_time']);
            $info['end_time']=strtotime($info['end_time']);
            unset($info['id']);
            $res = BannerModel::insertGetId($info);
            $info['start_time'] = $start_time;
            $info['end_time'] = $end_time;
            $info['id'] = $res;
            $langs = LangModel::order('id asc')->select();
            $info['langs'] = $langs;

            $cache = new ApiRedis();
            $cache->delete('updateBannerTime');
            return Respons::run($res?1:0,'','','',$info,new BannerModel(),lang('轮播图添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $BannerModel=new BannerModel();
            $info=$param['info'];
            $data = $BannerModel->where('id',$info['id'])->field("*,from_unixtime(start_time,'%Y-%m-%d %H:%i:%s') as start_time,from_unixtime(end_time,'%Y-%m-%d %H:%i:%s') as end_time")->find();
            $start_time = $info['start_time'];
            $end_time = $info['end_time'];
            $info['start_time']=strtotime($info['start_time']);
            $info['end_time']=strtotime($info['end_time']);
            $res=$BannerModel->update($info);
            $info['start_time'] = $start_time;
            $info['end_time'] = $end_time;
            $cache = new ApiRedis();
            $cache->delete('updateBannerTime');
            return Respons::run($res?1:0,'','',$data,$info,$BannerModel,lang('轮播图修改'));

        }
        if (isset($param['find'])) {
            $BannerModel =new BannerModel();
            $res=$BannerModel->where("id",$param['id'])->find();
            $res['start_time']=date("Y-m-d H:i:s",$res['start_time']);
            $res['end_time']=date("Y-m-d H:i:s",$res['end_time']);
            return Respons::run(1,$res);
        }
    }

    //编辑
    public function status_edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $BannerModel=new BannerModel();
            $data = $BannerModel->where('id',$info['id'])->field('id,status')->find();
//            $info['update_time']=time();
//            $info['admin_name']=Session::get("adminInfo.name");
            $res=$BannerModel->update($info);
            $cache = new ApiRedis();
            $cache->delete('updateBannerTime');
            return Respons::run($res?1:0,'','',$data,$info,$BannerModel,lang('轮播图状态修改'));
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $BannerModel =new BannerModel();
            $res=$BannerModel->where("id","in",$id)->delete();
            $cache = new ApiRedis();
            $cache->delete('updateBannerTime');
            return Respons::run($res?1:0,'','',$param,'',$BannerModel,lang('轮播图删除'));
        }
    }
}
