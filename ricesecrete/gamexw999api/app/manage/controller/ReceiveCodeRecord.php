<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\TagModel;
use app\common\Utils;

class ReceiveCodeRecord
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];

        //领取码筛选
        if( isset($param['code']) && $param['code'] !== '' ){
            $condition[] = ['code', '=', $param['code']];
        }
        if( isset($param['ip']) && $param['ip'] !== '' ){
            $condition[] = ['ip', '=', $param['ip']];
        }
        //时间筛选
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        $receiveCodeRecordModel =new ReceiveCodeRecordModel();
        $rsps['data']=$receiveCodeRecordModel
            ->where($condition)
            ->order("id desc")
            ->filter(function ($data){
                $w = [];
                $w[] = ['id', 'in', $data['tag_list']];
                $data['tag_list'] = TagModel::where($w)->column('name, color');
            })
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        $rsps['statistics']['receive_amount'] = $receiveCodeRecordModel->where($condition)->sum('reward_amount');
        return Respons::run($rsps);
    }

}