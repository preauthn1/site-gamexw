<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\LotteryOnlineTaskModel;
use app\common\model\LotteryOnlineTaskRecordModel;
use app\common\model\SignModel;
use app\common\model\SignDayModel;
use app\common\model\SignRecordModel;
use app\common\model\ServiceUrlLogModel;
use app\common\model\ServiceUrlModel;
use app\common\service\ServiceUrlService;
use app\common\service\SystemService;
use app\common\Utils;
use app\common\validate\TaskValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\Request;
class ServiceUrl
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition = [];
        $serviceUrlModel =new ServiceUrlModel();
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['type', '=', $param['type']];
        }
        if( isset($param['mode']) && $param['mode'] !== '' ){
            $condition[] = ['mode', '=', $param['mode']];
        }
        $paginate=$serviceUrlModel
            ->field('*')->where($condition)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach ($paginate as &$v){
            $v['num'] = ServiceUrlLogModel::where('url', '=', $v['url'])->count();
            $v['address_num'] = ServiceUrlLogModel::where('url', '=', $v['url'])->group('address')->count();
            $v['ip_num'] = ServiceUrlLogModel::where('url', '=', $v['url'])->group('ip')->count();
            $v['share_url'] = SystemService::getShareUrlByType($v['type'], $v['mode']);
        }
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $res = ServiceUrlModel::insertGetId($info);
            //清除缓存
            ServiceUrlService::clearCache();
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new ServiceUrlModel(),lang('telegram添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $serviceUrlModel = new ServiceUrlModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $serviceUrlModel->where('id',$info['id'])->find();
            $res=$serviceUrlModel->update($info);
            //清除缓存
            ServiceUrlService::clearCache();
            return Respons::run($res?1:0,'','',$data,$info,$serviceUrlModel,lang('telegram修改'));
        }
        if (isset($param['find'])) {
            $res=$serviceUrlModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $serviceUrlModel =new ServiceUrlModel();
            $res=$serviceUrlModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$serviceUrlModel,lang('telegram删除'));
        }
    }

    //签到记录
    public function statistics(){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition = [];
        $serviceUrlLogModel =new ServiceUrlLogModel();
        if( isset($param['url']) && $param['url'] !== '' ){
            $condition[] = ['url', '=', $param['url']];
        }
        if( isset($param['ip']) && $param['ip'] !== '' ){
            $condition[] = ['ip', '=', $param['ip']];
        }
        if( isset($param['address']) && $param['address'] !== '' ){
            $condition[] = ['address', 'like', '%'.$param['address'].'%'];
        }
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        $paginate=$serviceUrlLogModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}