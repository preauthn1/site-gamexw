<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\model\PddPhoneModel;
use app\common\model\WuFuCountModel;
use app\common\model\WuFuModel;
use app\common\model\WuFuWithdrawalModel;
use app\common\Utils;
use app\manage\factory\WufuCardsRecordFactory;
use app\manage\factory\WufuWithdrawalRecordFactory;
use think\Request;
class Wufu
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $wufuModel =new WuFuModel();
        $condition = [];
        if( isset($param['lang']) && $param['lang'] !== '' ){
            $condition[] = ['lang', '=', $param['lang']];
        }
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        if( isset($param['name']) && $param['name'] !== '' ){
            $condition[] = ['name', 'like', '%'.$param['name'].'%'];
        }
        $paginate=$wufuModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach ($paginate as &$v){
            $v['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
            $v['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
        }
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
            $info = $this->handleData($info);
            $info['create_time'] = $info['update_time'] = time();
            $res = WuFuModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new WuFuModel(),lang('五福添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $wufuModel = new WuFuModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $info = $this->handleData($info);
            $data = $wufuModel->where('id',$info['id'])->find();
            $res=$wufuModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$wufuModel,lang('五福修改'));
        }
        if (isset($param['find'])) {
            $res=$wufuModel->where("id",$param['id'])->find();
            $res->start_time = date('Y-m-d H:i:s', $res->start_time);
            $res->end_time = date('Y-m-d H:i:s', $res->end_time);
            return Respons::run(1,$res);
        }
    }

    //处理数据
    private function handleData($info){
        $info['start_time'] = strtotime($info['start_time']);
        $info['end_time'] = strtotime($info['end_time']);
        return $info;
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $wufuModel =new WuFuModel();
            $res=$wufuModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$wufuModel,lang('五福删除'));
        }
    }

    //卡片记录
    public function cardRecord(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $wuFuCountModel =new WuFuCountModel();
        $condition = [];
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['a.uid', '=', $param['uid']];
        }
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['a.type', '=', $param['type']];
        }
        if( isset($param['w_name']) && $param['w_name'] !== '' ){
            $condition[] = ['b.name', 'like', '%'.$param['w_name'].'%'];
        }
        if( isset($param['c_name']) && $param['c_name'] !== '' ){
            $condition[] = ['c.name', 'like', '%'.$param['c_name'].'%'];
        }
        if (isset($param['export']) && $param['export'] == 1) {
            return (new WufuCardsRecordFactory($request->token->uid))->exportList($condition,'');
        }
        $paginate=$wuFuCountModel->alias('a')
            ->join('wufu b', 'b.id=a.w_id')
            ->join('wufu_cards c', 'c.id=a.card_id', 'left')
            ->where($condition)
            ->field('a.*,b.name as w_name,c.name as c_name')
            ->order("a.id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach ($paginate as &$v){
            if( !$v['c_name'] ){
                if( $v['card_id'] == -1 ){
                    $v['c_name'] = '未中奖';
                }elseif ( $v['card_id'] == 0 ){
                    $v['c_name'] = '未抽奖';
                }else{
                    $v['c_name'] = '已删除';
                }
            }
        }
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //卡片记录
    public function withdrawalRecord(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $wuFuWithdrawalModel =new WuFuWithdrawalModel();
        $condition = [];
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['a.uid', '=', $param['uid']];
        }
        if( isset($param['w_name']) && $param['w_name'] !== '' ){
            $condition[] = ['b.name', 'like', '%'.$param['w_name'].'%'];
        }
        if( isset($param['c_name']) && $param['c_name'] !== '' ){
            $condition[] = ['c.name', 'like', '%'.$param['c_name'].'%'];
        }
        if (isset($param['export']) && $param['export'] == 1) {
            return (new WufuWithdrawalRecordFactory($request->token->uid))->exportList($condition,'');
        }
        $paginate=$wuFuWithdrawalModel->alias('a')
            ->join('wufu b', 'b.id=a.w_id')
            ->join('wufu_cards c', 'c.id=a.card_id', 'left')
            ->where($condition)
            ->field('a.*,b.name as w_name,c.name as c_name')
            ->order("a.id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach ($paginate as &$v){
            if( !$v['c_name'] ){
                if( $v['card_id'] == 0 ){
                    $v['c_name'] = '集齐所有兑换';
                }else{
                    $v['c_name'] = '已删除';
                }
            }
        }
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}