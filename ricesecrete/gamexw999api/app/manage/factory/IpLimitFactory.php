<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\IpLimitModel;
use think\facade\Request;

class IpLimitFactory
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
        $IpLimitModel =new IpLimitModel();
        if (isset($param['ip']) && $param['ip']!='') {
            $where[]=["ip","like","%".$param['ip']."%"];
        }
        $rsps['data']=$IpLimitModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['update_time'] = date("Y-m-d H:i:s",$value['update_time']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}