<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\BangdanModel;
use think\facade\Request;

class BangdanFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $BangdanModel =new BangdanModel();
        if (isset($param['issue_number']) && $param['issue_number']!='') {
            $where[]=["issue_number","like","%".$param['issue_number']."%"];
        }
//            $rsps['count']=$BangdanModel->where($where)->count();
        $rsps['data']=$BangdanModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$val){
            $val['add_time'] = date("Y-m-d H:i:s",$val['add_time']);
            $val['start_date'] = date("Y-m-d H:i:s",$val['start_date']);
            $val['end_date'] = date("Y-m-d H:i:s",$val['end_date']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}