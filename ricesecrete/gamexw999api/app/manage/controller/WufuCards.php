<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\model\PddPhoneModel;
use app\common\model\WuFuCardsModel;
use app\common\model\WuFuModel;
use app\common\Utils;
use think\Request;
class WufuCards
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $wufuCardsModel =new WuFuCardsModel();
        $condition = [];
        if( isset($param['lang']) && $param['lang'] !== '' ){
            $condition[] = ['lang', '=', $param['lang']];
        }
        if( isset($param['w_id']) && $param['w_id'] !== '' ){
            $condition[] = ['w_id', '=', $param['w_id']];
        }
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        if( isset($param['name']) && $param['name'] !== '' ){
            $condition[] = ['name', 'like', '%'.$param['name'].'%'];
        }
        $paginate=$wufuCardsModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            if( $this->judgeOdds($info['w_id'], $info['odds']) ){
                return Respons::run(0, [], '几率不能超过100');
            }
            $res = WuFuCardsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new WuFuCardsModel(),lang('五福卡片添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $wuFuCardsModel = new WuFuCardsModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            if( $this->judgeOdds($info['w_id'], $info['odds'], $info['id']) ){
                return Respons::run(0, [], '几率不能超过100');
            }
            $data = $wuFuCardsModel->where('id',$info['id'])->find();
            $res=$wuFuCardsModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$wuFuCardsModel,lang('五福卡片修改'));
        }
        if (isset($param['find'])) {
            $res=$wuFuCardsModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //判断几率是否超过一百
    private function judgeOdds($wId, $odds = 0, $id = 0){
        $condition = [];
        if( $id ){
            $condition[] = ['id', '<>', $id];
        }
        $condition[] = ['w_id', '=', $wId];
        if( WuFuCardsModel::where($condition)->sum('odds') + $odds > 100 ){
            return true;
        }
        return false;
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $wuFuCardsModel =new WuFuCardsModel();
            $res=$wuFuCardsModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$wuFuCardsModel,lang('五福卡片删除'));
        }
    }

}